<?php
// mijn_verlof.php
require 'config.php';

// Controleer of gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Haal huidige gebruiker op voor de zijbalk
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();

$success_msg = '';
$error_msg = '';

// --- FORMULIER VERWERKEN (Nieuwe aanvraag) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_request') {
    $type = $_POST['type'] ?? '';
    $from_date = $_POST['from_date'] ?? '';
    $to_date = $_POST['to_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    // Tijden (als 'gedeeltelijke dag' is aangevinkt)
    $time_from = !empty($_POST['time_from']) ? $_POST['time_from'] : null;
    $time_to = !empty($_POST['time_to']) ? $_POST['time_to'] : null;

    if (empty($type) || empty($from_date) || empty($to_date)) {
        $error_msg = "Vul alle verplichte velden in (Type, Van en Tot).";
    } elseif ($to_date < $from_date) {
        $error_msg = "De einddatum kan niet voor de begindatum liggen.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO requests (user_id, type, from_date, to_date, time_from, time_to, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $type, $from_date, $to_date, $time_from, $time_to, $reason]);
            $success_msg = "Je verlofaanvraag is succesvol ingediend!";
        } catch (PDOException $e) {
            $error_msg = "Er ging iets mis bij het opslaan: " . $e->getMessage();
        }
    }
}

// --- FORMULIER VERWERKEN (Aanvraag annuleren - alleen als deze nog 'pending' is) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_request') {
    $request_id = $_POST['request_id'];
    // We checken expliciet of deze van de huidige gebruiker is én nog pending is
    $stmt = $pdo->prepare("DELETE FROM requests WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$request_id, $user_id]);
    $success_msg = "De aanvraag is geannuleerd.";
}

// Haal alle aanvragen van deze specifieke gebruiker op
$stmtRequests = $pdo->prepare("SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC");
$stmtRequests->execute([$user_id]);
$my_requests = $stmtRequests->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mijn Verlof — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* Exacte CSS uit jouw V3 ontwerp */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --blue:#1A5EA8;--blue-light:#EBF3FB;
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
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:500;}
.badge-pending{background:#FEF5E7;color:#B7770D;}
.badge-approved{background:var(--green-light);color:var(--green);}
.badge-rejected{background:var(--danger-light);color:var(--danger);}
.tag{display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;background:var(--surface2);color:var(--text2);border:1px solid var(--border);}
.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-success{background:var(--green-light);color:var(--green);}
.alert-danger{background:var(--danger-light);color:var(--danger);}

/* Modals */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:100;opacity:0;pointer-events:none;transition:opacity .2s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border-radius:14px;width:100%;max-width:540px;max-height:92vh;overflow-y:auto;padding:26px 26px 22px;transform:translateY(12px);transition:transform .2s;}
.modal-overlay.open .modal{transform:translateY(0);}
.modal h3{font-size:17px;font-weight:600;margin-bottom:18px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field input, .field select, .field textarea{width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:var(--radius);font-family:var(--font);font-size:14px;outline:none;}
.field input:focus, .field select:focus, .field textarea:focus{border-color:var(--accent);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.modal-actions{display:flex;gap:9px;justify-content:flex-end;margin-top:20px;}
</style>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-logo-wrap">
      <img src="https://mstlogistics.nl/assets/images/logo/logo.svg" alt="MST Logistics">
    </div>

    <div class="nav-section">
      <span class="nav-label">Mijn Portaal</span>
      <a href="dashboard.php" class="nav-item">Dashboard</a>
      <a href="mijn_verlof.php" class="nav-item active">Mijn verlofaanvragen</a>
    </div>

    <?php if($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager'): ?>
    <div class="nav-section" style="margin-top:20px;">
      <span class="nav-label">Beheerders Menu</span>
      <a href="medewerkers.php" class="nav-item">Medewerkers (HR)</a>
    </div>
    <?php endif; ?>

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
        <h1>Mijn verlofaanvragen</h1>
        <p>Overzicht van al jouw ingediende aanvragen</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-primary" onclick="openModal('modal-request')">+ Nieuwe aanvraag</button>
      </div>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= $success_msg ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-danger"><?= $error_msg ?></div><?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Type</th>
            <th>Van</th>
            <th>Tot</th>
            <th>Tijd</th>
            <th>Reden</th>
            <th>Status</th>
            <th>Actie</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($my_requests as $req): ?>
          <tr>
            <td><span class="tag"><?= htmlspecialchars($req['type']) ?></span></td>
            <td><?= date('d-m-Y', strtotime($req['from_date'])) ?></td>
            <td><?= date('d-m-Y', strtotime($req['to_date'])) ?></td>
            <td style="font-size:12px;color:var(--blue)">
                <?= $req['time_from'] ? date('H:i', strtotime($req['time_from'])) . ' - ' . ($req['time_to'] ? date('H:i', strtotime($req['time_to'])) : '?') : '—' ?>
            </td>
            <td style="color:var(--text2);font-size:12px;max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= htmlspecialchars($req['reason'] ?: '—') ?>
            </td>
            <td>
                <?php if($req['status'] === 'pending'): ?>
                    <span class="badge badge-pending">⏳ In behandeling</span>
                <?php elseif($req['status'] === 'approved'): ?>
                    <span class="badge badge-approved">✓ Goedgekeurd</span>
                <?php elseif($req['status'] === 'rejected'): ?>
                    <span class="badge badge-rejected">✕ Geweigerd</span>
                <?php endif; ?>
                
                <?php if($req['admin_note']): ?>
                    <br><span style="font-size:11px;color:var(--text2)"><?= htmlspecialchars($req['admin_note']) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <?php if($req['status'] === 'pending'): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je deze aanvraag wilt annuleren?');">
                        <input type="hidden" name="action" value="cancel_request">
                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Annuleren</button>
                    </form>
                <?php else: ?>
                    <span style="font-size:12px;color:var(--text3)">Beoordeeld</span>
                <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          
          <?php if(count($my_requests) === 0): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text3);padding:30px;">Nog geen aanvragen ingediend.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <div class="modal-overlay" id="modal-request">
    <div class="modal">
      <h3>Nieuwe verlofaanvraag</h3>
      <form method="POST" action="mijn_verlof.php">
          <input type="hidden" name="action" value="new_request">
          
          <div class="field">
              <label>Type verlof</label>
              <select name="type" required>
                  <option value="Jaarlijks verlof">Jaarlijks verlof (Snipperdagen)</option>
                  <option value="ATV">ATV</option>
                  <option value="Zorgverlof">Zorgverlof</option>
                  <option value="Onbetaald verlof">Onbetaald verlof</option>
                  <option value="Anders">Anders</option>
              </select>
          </div>
          
          <div class="form-row">
            <div class="field"><label>Van datum *</label><input type="date" name="from_date" required></div>
            <div class="field"><label>Tot en met datum *</label><input type="date" name="to_date" required></div>
          </div>
          
          <div style="margin-bottom:14px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--text2)">
              <input type="checkbox" id="req-partial-day" onchange="document.getElementById('req-time-wrap').style.display=this.checked?'block':'none'" style="accent-color:var(--accent)">
              Gedeeltelijke dag (specifieke tijden opgeven)
            </label>
          </div>

          <div id="req-time-wrap" style="display:none; background:var(--surface2); padding:12px; border-radius:8px; margin-bottom:14px;">
            <div class="form-row">
              <div class="field" style="margin-bottom:0;"><label>Van tijd</label><input type="time" name="time_from"></div>
              <div class="field" style="margin-bottom:0;"><label>Tot tijd</label><input type="time" name="time_to"></div>
            </div>
          </div>

          <div class="field">
              <label>Reden (optioneel, handig voor beoordeling)</label>
              <textarea name="reason" rows="3" placeholder="Toelichting..."></textarea>
          </div>
          
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-request')">Annuleren</button>
            <button type="submit" class="btn btn-primary">Indienen</button>
          </div>
      </form>
    </div>
  </div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Zorg dat modal sluit als je buiten het witte vlak klikt
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>