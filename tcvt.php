<?php
// tcvt.php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Haal huidige gebruiker op
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'manager') {
    die("Je hebt geen toegang tot deze pagina.");
}

// --- BERICHTEN OPVANGEN (PRG Patroon) ---
$success_msg = '';
$error_msg = '';
if (isset($_SESSION['success_msg'])) { $success_msg = $_SESSION['success_msg']; unset($_SESSION['success_msg']); }
if (isset($_SESSION['error_msg'])) { $error_msg = $_SESSION['error_msg']; unset($_SESSION['error_msg']); }

// --- FORMULIER VERWERKEN (TCVT Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tcvt') {
    $emp_id = $_POST['user_id'];
    $expiry = $_POST['expiry_date'];
    $recert = $_POST['recert_date'] ?: null;
    $hercursus = trim($_POST['hercursus'] ?? '');
    $note = trim($_POST['note'] ?? '');
    
    // Modules samenvoegen tot een string, bijv: "A,B,C"
    $modules_array = $_POST['modules'] ?? [];
    $modules_string = implode(',', $modules_array);

    if (empty($emp_id) || empty($expiry)) {
        $_SESSION['error_msg'] = "Selecteer een medewerker en vul de verplichte vervaldatum in.";
    } else {
        try {
            // INSERT ON DUPLICATE KEY UPDATE zorgt ervoor dat er per medewerker altijd maar 1 TCVT record bestaat.
            $stmt = $pdo->prepare("
                INSERT INTO tcvt_registrations (user_id, modules, expiry_date, recert_date, hercursus, note) 
                VALUES (?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                modules = VALUES(modules), expiry_date = VALUES(expiry_date), recert_date = VALUES(recert_date), hercursus = VALUES(hercursus), note = VALUES(note)
            ");
            $stmt->execute([$emp_id, $modules_string, $expiry, $recert, $hercursus, $note]);
            $_SESSION['success_msg'] = "TCVT registratie succesvol opgeslagen!";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Fout bij opslaan: " . $e->getMessage();
        }
    }
    header("Location: tcvt.php");
    exit;
}

// --- DATA OPHALEN ---
// We halen alle medewerkers op en koppelen (LEFT JOIN) hun TCVT data als die bestaat
$query = "
    SELECT u.id, u.first_name, u.last_name, u.function_title, u.email, 
           t.modules, t.expiry_date, t.recert_date, t.hercursus, t.note 
    FROM users u 
    LEFT JOIN tcvt_registrations t ON u.id = t.user_id 
    WHERE u.role = 'employee' 
    ORDER BY u.first_name ASC
";
$tcvt_data = $pdo->query($query)->fetchAll();

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TCVT — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* Basis CSS */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --blue:#1A5EA8;--radius:10px;--font:'DM Sans',sans-serif;
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
.btn-sm{padding:5px 11px;font-size:12px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:500;}
.badge-ok{background:var(--green-light);color:var(--green);}
.badge-warning{background:#FEF5E7;color:#B7770D;}
.badge-expired{background:var(--danger-light);color:var(--danger);}
.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-info{background:var(--blue-light);color:var(--blue);}
.alert-success{background:var(--green-light);color:var(--green);}
.alert-danger{background:var(--danger-light);color:var(--danger);}

/* Modal TCVT Specifiek */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:100;opacity:0;pointer-events:none;transition:opacity .2s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border-radius:14px;width:100%;max-width:600px;max-height:92vh;overflow-y:auto;padding:26px 26px 22px;transform:translateY(12px);transition:transform .2s;}
.modal-overlay.open .modal{transform:translateY(0);}
.modal h3{font-size:17px;font-weight:600;margin-bottom:18px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field input, .field select, .field textarea{width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:14px;outline:none;}
.field input:focus, .field select:focus{border-color:var(--accent);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.modal-actions{display:flex;gap:9px;justify-content:flex-end;margin-top:20px;}

.module-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:6px;}
.module-box{border:2px solid var(--border);border-radius:8px;padding:10px 8px;text-align:center;cursor:pointer;transition:all .15s;user-select:none; background:var(--surface2);}
.module-box:hover{border-color:var(--blue);}
.module-box.checked{border-color:var(--green);background:var(--green-light);}
.module-box input{display:none;}
.module-box-label{font-size:18px;font-weight:700;display:block;}
.module-box-sub{font-size:10px;color:var(--text3);margin-top:2px;}
</style>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-logo-wrap">
      <img src="https://mstlogistics.nl/assets/images/logo/logo.svg" alt="MST Logistics">
    </div>

    <div class="nav-section">
      <span class="nav-label">Overzicht</span>
      <a href="dashboard.php" class="nav-item">Dashboard</a>
    </div>

    <div class="nav-section">
      <span class="nav-label">HR & Verlof</span>
      <a href="medewerkers.php" class="nav-item">Medewerkers</a>
      <a href="verlof_beheer.php" class="nav-item">Verlofaanvragen</a>
    </div>

    <div class="nav-section">
      <span class="nav-label">Certificering</span>
      <a href="code95.php" class="nav-item">Code 95</a>
      <a href="cursussen.php" class="nav-item">Cursussen</a>
      <a href="tcvt.php" class="nav-item active">TCVT</a>
    </div>

    <div class="sidebar-bottom">
      <a href="logout.php" class="user-chip">
        <div class="avatar"><?= htmlspecialchars(substr($currentUser['first_name'], 0, 1)) ?></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;font-weight:500;"><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></div>
          <div style="font-size:11px;color:var(--text3);"><?= htmlspecialchars(ucfirst($currentUser['role'])) ?></div>
        </div>
      </a>
    </div>
  </aside>

  <main class="main">
    <div class="page-header">
      <div class="page-header-text">
        <h1>TCVT Registraties</h1>
        <p>Technische Commissie Vakbekwaamheid Transport — modules A, B, C, D</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-tcvt')">+ TCVT registratie toevoegen</button>
      </div>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div class="alert alert-info">Alle 4 modules (A, B, C en D) moeten afgerond zijn voordat een medewerker succesvol kan verlengen.</div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Medewerker</th>
            <th>Modules</th>
            <th>Gecertificeerd tot</th>
            <th>Hercertificering</th>
            <th>Status</th>
            <th>Actie</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($tcvt_data as $row): 
              $has_tcvt = !empty($row['expiry_date']);
              $modules_achieved = $has_tcvt && $row['modules'] ? explode(',', $row['modules']) : [];
              $all_done = count($modules_achieved) === 4;
              
              // Status bepalen
              if (!$has_tcvt) {
                  $badge = '<span class="badge" style="background:var(--surface2);color:var(--text3)">Niet geregistreerd</span>';
              } elseif ($row['expiry_date'] < $today) {
                  $badge = '<span class="badge badge-expired">Verlopen</span>';
              } elseif ($all_done) {
                  $days_left = (strtotime($row['expiry_date']) - strtotime($today)) / 86400;
                  $badge = $days_left <= 90 ? '<span class="badge badge-warning">Verloopt binnenkort</span>' : '<span class="badge badge-ok">Gecertificeerd ✓</span>';
              } else {
                  $badge = '<span class="badge badge-warning">Modules incompleet</span>';
              }
          ?>
          <tr>
            <td>
                <strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong><br>
                <span style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($row['function_title'] ?: '—') ?></span>
            </td>
            <td>
                <?php if($has_tcvt): ?>
                    <div style="display:flex;gap:4px">
                    <?php foreach(['A','B','C','D'] as $m): ?>
                        <?php $checked = in_array($m, $modules_achieved); ?>
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;font-size:11px;font-weight:700;<?= $checked ? 'background:var(--green-light);color:var(--green);' : 'background:var(--surface2);color:var(--text3);border:1px solid var(--border);' ?>"><?= $m ?></span>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <span style="color:var(--text3);font-size:12px">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if($has_tcvt): ?>
                    <?= date('d-m-Y', strtotime($row['expiry_date'])) ?><br>
                    <span style="font-size:11px;color:<?= ($row['expiry_date'] < $today) ? 'var(--danger)' : 'var(--text3)' ?>">
                        <?= ($row['expiry_date'] < $today) ? 'Verlopen' : round((strtotime($row['expiry_date']) - strtotime($today))/86400) . ' dagen' ?>
                    </span>
                <?php else: ?>
                    <span style="color:var(--text3);font-size:12px">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if($has_tcvt && $row['recert_date']): ?>
                    <?= date('d-m-Y', strtotime($row['recert_date'])) ?>
                    <?php if($row['recert_date'] <= $today): ?>
                        <span style="font-size:10px;color:var(--danger)"> ⚠ nu vereist</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color:var(--text3);font-size:12px">—</span>
                <?php endif; ?>
            </td>
            <td><?= $badge ?></td>
            <td>
                <button class="btn btn-secondary btn-sm" onclick="openTcvtModal(
                    <?= $row['id'] ?>, 
                    '<?= $row['expiry_date'] ?? '' ?>', 
                    '<?= $row['recert_date'] ?? '' ?>', 
                    '<?= addslashes(htmlspecialchars($row['hercursus'] ?? '')) ?>', 
                    '<?= addslashes(htmlspecialchars($row['note'] ?? '')) ?>', 
                    '<?= $row['modules'] ?? '' ?>'
                )"><?= $has_tcvt ? 'Bewerken' : '+ Toevoegen' ?></button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <div class="modal-overlay" id="modal-tcvt">
    <div class="modal" style="max-width:600px">
      <h3>TCVT registratie</h3>
      <form method="POST" action="tcvt.php">
          <input type="hidden" name="action" value="update_tcvt">
          
          <div class="form-row">
            <div class="field">
                <label>Medewerker *</label>
                <select name="user_id" id="tcvt-emp-select" required>
                    <?php foreach($tcvt_data as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Gecertificeerd tot *</label><input type="date" name="expiry_date" id="tcvt-expiry" required></div>
          </div>
          
          <div class="field"><label>Hercertificering vereist per</label><input type="date" name="recert_date" id="tcvt-recert"></div>
          
          <div class="field">
            <label>Modules afgerond</label>
            <div class="module-grid">
              <label class="module-box" id="mod-box-A" onclick="toggleModule('A')"><input type="checkbox" name="modules[]" value="A" id="mod-A"><span class="module-box-label">A</span><span class="module-box-sub">Techniek</span></label>
              <label class="module-box" id="mod-box-B" onclick="toggleModule('B')"><input type="checkbox" name="modules[]" value="B" id="mod-B"><span class="module-box-label">B</span><span class="module-box-sub">Rijvaardigheid</span></label>
              <label class="module-box" id="mod-box-C" onclick="toggleModule('C')"><input type="checkbox" name="modules[]" value="C" id="mod-C"><span class="module-box-label">C</span><span class="module-box-sub">Gevaarlijke stoffen</span></label>
              <label class="module-box" id="mod-box-D" onclick="toggleModule('D')"><input type="checkbox" name="modules[]" value="D" id="mod-D"><span class="module-box-label">D</span><span class="module-box-sub">Ladingzekering</span></label>
            </div>
          </div>
          
          <div class="field"><label>Herhalingsmodules / cursus (optioneel)</label><input type="text" name="hercursus" id="tcvt-hercursus" placeholder="Bijv. Hercursus rijvaardigheid 2024"></div>
          <div class="field"><label>Notitie (optioneel)</label><textarea name="note" id="tcvt-note" rows="2" placeholder="Optionele toelichting..."></textarea></div>
          
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-tcvt')">Annuleren</button>
            <button type="submit" class="btn btn-primary">Opslaan</button>
          </div>
      </form>
    </div>
  </div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function toggleModule(m) { 
    const cb = document.getElementById('mod-' + m); 
    // Omdat een label een click-event doorgeeft aan de checkbox, hoeven we alleen de UI class te veranderen
    setTimeout(() => {
        document.getElementById('mod-box-' + m).className = 'module-box' + (cb.checked ? ' checked' : ''); 
    }, 10);
}

function openTcvtModal(userId, expiry, recert, hercursus, note, modulesStr) {
    document.getElementById('tcvt-emp-select').value = userId;
    document.getElementById('tcvt-expiry').value = expiry || '';
    document.getElementById('tcvt-recert').value = recert || '';
    document.getElementById('tcvt-hercursus').value = hercursus || '';
    document.getElementById('tcvt-note').value = note || '';
    
    // Checkboxes resetten
    ['A','B','C','D'].forEach(m => {
        const cb = document.getElementById('mod-' + m);
        const isChecked = modulesStr.includes(m);
        cb.checked = isChecked;
        document.getElementById('mod-box-' + m).className = 'module-box' + (isChecked ? ' checked' : '');
    });
    
    openModal('modal-tcvt');
}

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>