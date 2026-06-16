<?php
// code95.php
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

// --- AUTO-SETUP VOOR CODE 95 STATUS TABEL ---
$pdo->exec("CREATE TABLE IF NOT EXISTS code95_status (
    user_id INT PRIMARY KEY,
    expiry_date DATE NOT NULL,
    note TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;");

// --- BERICHTEN OPVANGEN (PRG Patroon) ---
$success_msg = '';
$error_msg = '';
if (isset($_SESSION['success_msg'])) { $success_msg = $_SESSION['success_msg']; unset($_SESSION['success_msg']); }
if (isset($_SESSION['error_msg'])) { $error_msg = $_SESSION['error_msg']; unset($_SESSION['error_msg']); }

// --- FORMULIER VERWERKEN (Vervaldatum updaten) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_c95') {
    $emp_id = $_POST['user_id'];
    $expiry = $_POST['expiry_date'];
    $note = trim($_POST['note'] ?? '');

    if (empty($emp_id) || empty($expiry)) {
        $_SESSION['error_msg'] = "Selecteer een medewerker en vul een datum in.";
    } else {
        // Gebruik INSERT ON DUPLICATE KEY UPDATE (Dit maakt het aan als het niet bestaat, of updatet het als het wel bestaat)
        $stmt = $pdo->prepare("INSERT INTO code95_status (user_id, expiry_date, note) VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE expiry_date = VALUES(expiry_date), note = VALUES(note)");
        $stmt->execute([$emp_id, $expiry, $note]);
        $_SESSION['success_msg'] = "Vervaldatum succesvol bijgewerkt!";
    }
    header("Location: code95.php");
    exit;
}

// --- DATA OPHALEN ---
$employees = $pdo->query("SELECT id, first_name, last_name, function_title FROM users WHERE role = 'employee' ORDER BY first_name ASC")->fetchAll();

// Bouw een array met alle data per medewerker
$c95_data = [];
$today = date('Y-m-d');

foreach ($employees as $emp) {
    $uid = $emp['id'];
    
    // 1. Haal uren op
    $stmtHours = $pdo->prepare("
        SELECT c.type, SUM(c.hours) as total_hours 
        FROM courses c 
        JOIN course_participants cp ON c.id = cp.course_id 
        WHERE cp.user_id = ? 
        GROUP BY c.type
    ");
    $stmtHours->execute([$uid]);
    $hours = $stmtHours->fetchAll(PDO::FETCH_KEY_PAIR); // Geeft array: ['theorie' => 14, 'praktijk' => 7]
    
    $theorie = (int)($hours['theorie'] ?? 0);
    $praktijk = (int)($hours['praktijk'] ?? 0);
    $totaal = $theorie + $praktijk;
    $has_praktijk = $praktijk > 0;
    
    // 2. Haal vervaldatum op
    $stmtExp = $pdo->prepare("SELECT expiry_date, note FROM code95_status WHERE user_id = ?");
    $stmtExp->execute([$uid]);
    $status = $stmtExp->fetch();
    
    $expiry = $status ? $status['expiry_date'] : null;
    $note = $status ? $status['note'] : '';
    
    // 3. Bereken statussen
    $pct = min(100, round(($totaal / 35) * 100));
    $is_expired = $expiry && $expiry < $today;
    
    $days_until = null;
    if ($expiry) {
        $diff = strtotime($expiry) - strtotime($today);
        $days_until = round($diff / 86400);
    }
    
    $warn = $expiry && !$is_expired && $days_until <= 90;

    $c95_data[] = [
        'id' => $uid,
        'name' => $emp['first_name'] . ' ' . $emp['last_name'],
        'function' => $emp['function_title'],
        'theorie' => $theorie,
        'praktijk' => $praktijk,
        'totaal' => $totaal,
        'has_praktijk' => $has_praktijk,
        'expiry' => $expiry,
        'note' => $note,
        'pct' => $pct,
        'is_expired' => $is_expired,
        'days_until' => $days_until,
        'warn' => $warn
    ];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Code 95 — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --accent2:#C8873A;--accent2-light:#FAF0E4;
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
.btn-sm{padding:5px 11px;font-size:12px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:500;}
.badge-ok{background:var(--green-light);color:var(--green);}
.badge-warning{background:#FEF5E7;color:#B7770D;}
.badge-expired{background:var(--danger-light);color:var(--danger);}
.bar-wrap{background:var(--surface2);border-radius:4px;height:6px;flex:1;min-width:50px;}
.bar{height:6px;border-radius:4px;transition:width .3s;}
.bar.green{background:var(--green);}
.bar.amber{background:var(--accent2);}
.bar.red{background:var(--danger);}
.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-success{background:var(--green-light);color:var(--green);}
.alert-danger{background:var(--danger-light);color:var(--danger);}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:100;opacity:0;pointer-events:none;transition:opacity .2s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border-radius:14px;width:100%;max-width:400px;max-height:92vh;overflow-y:auto;padding:26px 26px 22px;transform:translateY(12px);transition:transform .2s;}
.modal-overlay.open .modal{transform:translateY(0);}
.modal h3{font-size:17px;font-weight:600;margin-bottom:18px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field input, .field select, .field textarea{width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:14px;outline:none;}
.field input:focus, .field select:focus{border-color:var(--accent);}
.modal-actions{display:flex;gap:9px;justify-content:flex-end;margin-top:20px;}
</style>
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main">
    <div class="page-header">
      <div class="page-header-text">
        <h1>Code 95 Overzicht</h1>
        <p>Automatisch berekende bijscholingsverplichting (35 uur per 5 jaar, min. 1 praktijkcursus)</p>
      </div>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Medewerker</th>
            <th>Vervaldatum</th>
            <th>📖 Theorie</th>
            <th>🚛 Praktijk</th>
            <th>Totaal</th>
            <th>Voortgang</th>
            <th>Status</th>
            <th>Actie</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($c95_data as $c): ?>
            <?php 
                // Bepaal de kleuren voor de progress bar en badges
                $bar_color = $c['is_expired'] ? 'red' : ($c['warn'] ? 'amber' : 'green');
                
                if (!$c['expiry']) {
                    $badge = '<span class="badge" style="background:var(--surface2);color:var(--text3)">Geen datum</span>';
                } elseif ($c['is_expired']) {
                    $badge = '<span class="badge badge-expired">Verlopen</span>';
                } elseif (!$c['has_praktijk'] && $c['totaal'] > 0) {
                    $badge = '<span class="badge badge-warning">⚠ Geen praktijk</span>';
                } elseif ($c['totaal'] >= 35 && $c['has_praktijk']) {
                    $badge = '<span class="badge badge-ok">Compleet ✓</span>';
                } elseif ($c['warn']) {
                    $badge = '<span class="badge badge-warning">Bijna verlopen</span>';
                } else {
                    $badge = '<span class="badge badge-ok">In orde</span>';
                }
            ?>
          <tr>
            <td>
                <strong><?= htmlspecialchars($c['name']) ?></strong><br>
                <span style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($c['function'] ?: '—') ?></span>
            </td>
            <td>
                <?php if($c['expiry']): ?>
                    <?= date('d-m-Y', strtotime($c['expiry'])) ?><br>
                    <span style="font-size:11px;color:<?= $c['is_expired'] ? 'var(--danger)' : 'var(--text3)' ?>">
                        <?= $c['is_expired'] ? 'Verlopen' : $c['days_until'] . ' dagen' ?>
                    </span>
                <?php else: ?>
                    <span style="color:var(--text3)">Niet ingesteld</span>
                <?php endif; ?>
            </td>
            <td style="color:var(--purple);font-weight:500"><?= $c['theorie'] ?> u</td>
            <td style="color:var(--blue);font-weight:500">
                <?= $c['praktijk'] ?> u <?= $c['has_praktijk'] ? '✓' : '<span style="color:var(--danger);font-size:10px">vereist</span>' ?>
            </td>
            <td><strong><?= $c['totaal'] ?></strong>/35</td>
            <td style="min-width:100px">
                <div style="display:flex;align-items:center;gap:6px">
                    <div class="bar-wrap"><div class="bar <?= $bar_color ?>" style="width:<?= $c['pct'] ?>%"></div></div>
                    <span style="font-size:11px;color:var(--text3)"><?= $c['pct'] ?>%</span>
                </div>
            </td>
            <td><?= $badge ?></td>
            <td>
                <button class="btn btn-secondary btn-sm" onclick="openUpdateModal(<?= $c['id'] ?>, '<?= $c['expiry'] ?>', '<?= htmlspecialchars($c['note']) ?>')">Vervaldatum</button>
            </td>
          </tr>
          <?php endforeach; ?>
          
          <?php if(count($c95_data) === 0): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text3);padding:30px;">Geen medewerkers gevonden.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <div class="modal-overlay" id="modal-c95">
    <div class="modal">
      <h3>Vervaldatum Code 95</h3>
      <form method="POST" action="code95.php">
          <input type="hidden" name="action" value="update_c95">
          
          <div class="field">
              <label>Medewerker</label>
              <select name="user_id" id="modal-emp-select" required>
                  <?php foreach($employees as $emp): ?>
                      <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                  <?php endforeach; ?>
              </select>
          </div>
          
          <div class="field">
              <label>Nieuwe vervaldatum *</label>
              <input type="date" name="expiry_date" id="modal-expiry" required>
          </div>
          
          <div class="field">
              <label>Opmerking (optioneel)</label>
              <textarea name="note" id="modal-note" rows="2" placeholder="Bijv. nieuwe cyclus gestart..."></textarea>
          </div>
          
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-c95')">Annuleren</button>
            <button type="submit" class="btn btn-primary">Opslaan</button>
          </div>
      </form>
    </div>
  </div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openUpdateModal(userId, expiry, note) {
    document.getElementById('modal-emp-select').value = userId;
    document.getElementById('modal-expiry').value = expiry || '';
    document.getElementById('modal-note').value = note || '';
    openModal('modal-c95');
}

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>