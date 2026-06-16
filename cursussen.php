<?php
// cursussen.php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Haal huidige gebruiker op
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

// Alleen admins en managers
if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'manager') {
    die("Je hebt geen toegang tot deze pagina.");
}

// --- BERICHTEN OPVANGEN UIT SESSIE (PRG Patroon) ---
$success_msg = '';
$error_msg = '';

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// --- FORMULIER VERWERKEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Nieuwe cursus toevoegen
    if ($_POST['action'] === 'new_course') {
        $name = trim($_POST['name'] ?? '');
        $course_date = $_POST['course_date'] ?? '';
        $hours = (int)($_POST['hours'] ?? 0);
        $type = $_POST['type'] ?? '';
        $provider = trim($_POST['provider'] ?? '');
        $participants = $_POST['participants'] ?? []; // Array van user_id's

        if (empty($name) || empty($course_date) || empty($hours) || empty($type) || empty($participants)) {
            $_SESSION['error_msg'] = "Vul alle verplichte velden in en selecteer minimaal één deelnemer.";
        } else {
            try {
                $pdo->beginTransaction();

                // Sla de cursus op
                $stmt = $pdo->prepare("INSERT INTO courses (name, course_date, hours, type, provider) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $course_date, $hours, $type, $provider]);
                $course_id = $pdo->lastInsertId();

                // Koppel de geselecteerde medewerkers aan deze cursus
                $stmtPart = $pdo->prepare("INSERT INTO course_participants (course_id, user_id) VALUES (?, ?)");
                foreach ($participants as $user_id) {
                    $stmtPart->execute([$course_id, $user_id]);
                }

                $pdo->commit();
                $_SESSION['success_msg'] = "Cursus opgeslagen en " . count($participants) . " deelnemer(s) gekoppeld!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error_msg'] = "Fout bij opslaan: " . $e->getMessage();
            }
        }
        header("Location: cursussen.php");
        exit;
    }

    // 2. Cursus verwijderen
    if ($_POST['action'] === 'delete_course') {
        $course_id = $_POST['course_id'];
        // Omdat we in phpMyAdmin 'ON DELETE CASCADE' hebben ingesteld, 
        // verwijdert MySQL automatisch de bijbehorende deelnemers uit de koppeltabel!
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        
        $_SESSION['success_msg'] = "Cursus succesvol verwijderd.";
        header("Location: cursussen.php");
        exit;
    }
}

// --- DATA OPHALEN ---
// Haal alle cursussen op, gesorteerd op datum (nieuwste eerst)
$courses = $pdo->query("SELECT * FROM courses ORDER BY course_date DESC")->fetchAll();

// Haal voor elke cursus de deelnemers op
$course_data = [];
foreach ($courses as $c) {
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name 
        FROM course_participants cp 
        JOIN users u ON cp.user_id = u.id 
        WHERE cp.course_id = ?
    ");
    $stmt->execute([$c['id']]);
    $parts = $stmt->fetchAll();
    
    // Maak er een mooie komma-gescheiden string van
    $names = array_map(function($p) { return $p['first_name'] . ' ' . $p['last_name']; }, $parts);
    
    $c['participants_list'] = implode(', ', $names);
    $c['participants_count'] = count($parts);
    $course_data[] = $c;
}

// Haal alle actieve medewerkers op voor de checkboxen in de pop-up
$employees = $pdo->query("SELECT id, first_name, last_name, function_title FROM users WHERE role = 'employee' ORDER BY first_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cursussen — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* CSS uit jouw V3 ontwerp */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --blue:#1A5EA8;--blue-light:#EBF3FB;
  --purple:#6B21A8;--purple-light:#F3E8FD;
  --radius:10px;--font:'DM Sans',sans-serif;
}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;font-size:15px;}
.sidebar{position:fixed;top:0;left:0;width:228px;height:100vh;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:0 0 16px;z-index:10;}
.sidebar-logo-wrap{padding:18px 16px 14px;border-bottom:1px solid var(--border);margin-bottom:12px;}
.sidebar-logo-wrap img{height:32px;width:auto;object-fit:contain;}
.nav-section{padding:0 10px;margin-bottom:2px;}
.nav-label{font-size:10px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--text3);padding:6px 8px 3px;display:block;}
.nav-item{display:block;padding:8px 10px;border-radius:8px;text-decoration:none;font-size:13.5px;color:var(--text2);margin-bottom:1px;}
.nav-item:hover{background:var(--surface2);color:var(--text);}
.nav-item.active{background:var(--accent-light);color:var(--accent);font-weight:500;}
.sidebar-bottom{margin-top:auto;padding:0 10px;}
.user-chip{display:flex;align-items:center;gap:9px;padding:9px;border-radius:8px;border:1px solid var(--border);text-decoration:none;color:var(--text);}
.user-chip:hover{background:var(--surface2);}
.avatar{width:28px;height:28px;border-radius:50%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--accent);}
.main{margin-left:228px;padding:28px 32px;}
.page-header{margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.page-header-text h1{font-size:22px;font-weight:600;}
.page-header-text p{color:var(--text2);font-size:13px;margin-top:3px;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:18px;}
table{width:100%;border-collapse:collapse;}
th{font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);padding:10px 18px;text-align:left;background:var(--surface2);}
td{padding:11px 18px;font-size:13.5px;border-top:1px solid var(--border);vertical-align:middle;}
tr:hover td{background:#FAFAF8;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 17px;border-radius:8px;border:none;font-family:var(--font);font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;}
.btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{opacity:.88;}
.btn-secondary{background:var(--surface2);color:var(--text);border:1px solid var(--border);} .btn-secondary:hover{background:var(--border);}
.btn-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;} .btn-danger:hover{background:#f5c6c3;}
.btn-sm{padding:5px 11px;font-size:12px;}
.badge-praktijk{background:var(--blue-light);color:var(--blue);border:1px solid #C0D4F5; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:500;}
.badge-theorie{background:var(--purple-light);color:var(--purple);border:1px solid #DBBCF5; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:500;}
.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-success{background:var(--green-light);color:var(--green);}
.alert-danger{background:var(--danger-light);color:var(--danger);}

/* Modal & Form Styling */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:100;opacity:0;pointer-events:none;transition:opacity .2s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border-radius:14px;width:100%;max-width:600px;max-height:92vh;overflow-y:auto;padding:26px 26px 22px;transform:translateY(12px);transition:transform .2s;}
.modal-overlay.open .modal{transform:translateY(0);}
.modal h3{font-size:17px;font-weight:600;margin-bottom:18px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field input, .field select{width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:14px;outline:none;}
.field input:focus{border-color:var(--accent);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.modal-actions{display:flex;gap:9px;justify-content:flex-end;margin-top:20px;}

/* Custom toggle for Theorie/Praktijk */
.type-toggle{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:4px;}
.type-toggle-btn{padding:9px;border-radius:8px;border:2px solid var(--border);background:var(--surface);cursor:pointer;text-align:center;font-family:var(--font);font-size:12px;font-weight:500;transition:all .15s;}
.type-toggle-btn.active-praktijk{border-color:var(--blue);background:var(--blue-light);color:var(--blue);}
.type-toggle-btn.active-theorie{border-color:var(--purple);background:var(--purple-light);color:var(--purple);}

/* Employee Picker */
.emp-picker{border:1px solid var(--border);border-radius:8px;max-height:200px;overflow-y:auto;}
.emp-picker-item{display:flex;align-items:center;gap:10px;padding:8px 13px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s;}
.emp-picker-item:last-child{border-bottom:none;}
.emp-picker-item:hover{background:var(--surface2);}
.emp-picker-item input[type=checkbox]{width:14px;height:14px;accent-color:var(--accent);flex-shrink:0;}
.emp-picker-controls{display:flex;gap:7px;margin-bottom:7px;}
</style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

  <main class="main">
    <div class="page-header">
      <div class="page-header-text">
        <h1>Cursussen</h1>
        <p>Code 95 nascholing registreren per medewerker of groep</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-course')">+ Cursus registreren</button>
      </div>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Datum</th>
            <th>Cursus</th>
            <th>Type</th>
            <th>Uren</th>
            <th>Deelnemers</th>
            <th>Actie</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($course_data as $c): ?>
          <tr>
            <td><?= date('d-m-Y', strtotime($c['course_date'])) ?></td>
            <td>
                <strong><?= htmlspecialchars($c['name']) ?></strong>
                <?php if($c['provider']): ?>
                    <br><span style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($c['provider']) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php if($c['type'] === 'praktijk'): ?>
                    <span class="badge-praktijk">🚛 Praktijk</span>
                <?php else: ?>
                    <span class="badge-theorie">📖 Theorie</span>
                <?php endif; ?>
            </td>
            <td><strong><?= $c['hours'] ?></strong> uur</td>
            <td style="font-size:12px;color:var(--text2);max-width:200px;" title="<?= htmlspecialchars($c['participants_list']) ?>">
                <?= $c['participants_count'] ?> deelnemer(s)
            </td>
            <td>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je deze cursus en de behaalde uren voor alle deelnemers wilt verwijderen?');">
                    <input type="hidden" name="action" value="delete_course">
                    <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">✕ Verwijderen</button>
                </form>
            </td>
          </tr>
          <?php endforeach; ?>
          
          <?php if(count($course_data) === 0): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text3);padding:30px;">Nog geen cursussen geregistreerd.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <div class="modal-overlay" id="modal-course">
    <div class="modal" style="max-width:600px">
      <h3>Cursus registreren</h3>
      <form method="POST" action="cursussen.php">
          <input type="hidden" name="action" value="new_course">
          
          <div class="form-row">
            <div class="field"><label>Cursusnaam *</label><input type="text" name="name" required placeholder="Bijv. Verkeersregels 2024"></div>
            <div class="field"><label>Datum *</label><input type="date" name="course_date" required value="<?= date('Y-m-d') ?>"></div>
          </div>
          
          <div class="form-row">
            <div class="field"><label>Uren *</label><input type="number" name="hours" min="1" max="35" required placeholder="7"></div>
            <div class="field"><label>Instelling (optioneel)</label><input type="text" name="provider" placeholder="CBR, IBKI..."></div>
          </div>
          
          <div class="field">
            <label>Type cursus *</label>
            <div class="type-toggle">
              <button type="button" class="type-toggle-btn" id="btn-type-theorie" onclick="setCourseType('theorie')">📖 Theorie<br><span style="font-size:10px;font-weight:400;opacity:.8">Klassikaal / online</span></button>
              <button type="button" class="type-toggle-btn" id="btn-type-praktijk" onclick="setCourseType('praktijk')">🚛 Praktijk<br><span style="font-size:10px;font-weight:400;opacity:.8">Rijvaardigheidstraining</span></button>
            </div>
            <input type="hidden" name="type" id="course-type" value="" required>
          </div>
          
          <div class="field">
            <label>Deelnemers selecteren *</label>
            <div class="emp-picker-controls">
              <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll(true)">Alles selecteren</button>
              <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll(false)">Niets selecteren</button>
            </div>
            <div class="emp-picker">
                <?php foreach($employees as $emp): ?>
                    <label class="emp-picker-item">
                        <input type="checkbox" name="participants[]" value="<?= $emp['id'] ?>" class="emp-checkbox">
                        <div>
                            <div style="font-size:13.5px;font-weight:500"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                            <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($emp['function_title'] ?: 'Medewerker') ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
          </div>
          
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-course')">Annuleren</button>
            <button type="submit" class="btn btn-primary" onclick="return checkType()">Opslaan</button>
          </div>
      </form>
    </div>
  </div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Script voor de theorie/praktijk knoppen
function setCourseType(type) { 
    document.getElementById('course-type').value = type; 
    document.getElementById('btn-type-theorie').className = 'type-toggle-btn' + (type === 'theorie' ? ' active-theorie' : ''); 
    document.getElementById('btn-type-praktijk').className = 'type-toggle-btn' + (type === 'praktijk' ? ' active-praktijk' : ''); 
}

// Zorg dat er een type is gekozen voor submit
function checkType() {
    if(document.getElementById('course-type').value === '') {
        alert("Kies eerst of het een Theorie of Praktijk cursus is!");
        return false;
    }
    return true;
}

// Checkboxes selecteren/deselecteren
function selectAll(check) {
    document.querySelectorAll('.emp-checkbox').forEach(cb => cb.checked = check);
}

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>