<?php
// verlof_beheer.php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Haal huidige gebruiker op
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

// Alleen admins en managers mogen hier in
if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'manager') {
    die("Je hebt geen toegang tot deze pagina.");
}

$success_msg = '';

// --- VERWERK BEOORDELING (Goedkeuren / Afwijzen) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $admin_note = trim($_POST['admin_note'] ?? '');
    $new_status = '';

    if ($_POST['action'] === 'approve') {
        $new_status = 'approved';
        $success_msg = "Aanvraag is succesvol goedgekeurd!";
    } elseif ($_POST['action'] === 'reject') {
        $new_status = 'rejected';
        $success_msg = "Aanvraag is afgewezen.";
    }

    if ($new_status) {
        $stmt = $pdo->prepare("UPDATE requests SET status = ?, admin_note = ? WHERE id = ?");
        $stmt->execute([$new_status, $admin_note, $request_id]);
    }
}

// --- HAAL ALLE AANVRAGEN OP (Inclusief medewerker gegevens) ---
$query = "SELECT r.*, u.first_name, u.last_name, u.pnr 
          FROM requests r 
          JOIN users u ON r.user_id = u.id 
          ORDER BY r.created_at DESC";
$all_requests = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verlofaanvragen Beheren — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* Jouw vertrouwde V3 CSS basis */
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
.btn-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;} .btn-danger:hover{background:#f5c6c3;}
.btn-sm{padding:5px 11px;font-size:12px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:500;}
.badge-pending{background:#FEF5E7;color:#B7770D;}
.badge-approved{background:var(--green-light);color:var(--green);}
.badge-rejected{background:var(--danger-light);color:var(--danger);}
.tag{display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;background:var(--surface2);color:var(--text2);border:1px solid var(--border);}
.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-success{background:var(--green-light);color:var(--green);}

/* Modal Styling */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:100;opacity:0;pointer-events:none;transition:opacity .2s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border-radius:14px;width:100%;max-width:540px;max-height:92vh;overflow-y:auto;padding:26px 26px 22px;transform:translateY(12px);transition:transform .2s;}
.modal-overlay.open .modal{transform:translateY(0);}
.modal h3{font-size:17px;font-weight:600;margin-bottom:18px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field textarea{width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:var(--radius);font-family:var(--font);font-size:14px;outline:none;}
.field textarea:focus{border-color:var(--accent);}
.modal-actions{display:flex;gap:9px;justify-content:flex-end;margin-top:20px;}
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
      <a href="verlof_beheer.php" class="nav-item active">Verlofaanvragen</a>
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
        <h1>Alle verlofaanvragen</h1>
        <p>Overzicht en beoordeling van ingediende aanvragen</p>
      </div>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= $success_msg ?></div><?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Medewerker</th>
            <th>Type</th>
            <th>Van</th>
            <th>Tot</th>
            <th>Reden</th>
            <th>Status</th>
            <th>Actie</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($all_requests as $req): ?>
          <tr>
            <td>
                <strong><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></strong><br>
                <span style="font-size:11px;color:var(--text3)">Pnr: <?= htmlspecialchars($req['pnr'] ?: '—') ?></span>
            </td>
            <td><span class="tag"><?= htmlspecialchars($req['type']) ?></span></td>
            <td><?= date('d-m-Y', strtotime($req['from_date'])) ?><br><span style="font-size:11px;color:var(--blue)"><?= $req['time_from'] ? date('H:i', strtotime($req['time_from'])) : '' ?></span></td>
            <td><?= date('d-m-Y', strtotime($req['to_date'])) ?><br><span style="font-size:11px;color:var(--blue)"><?= $req['time_to'] ? date('H:i', strtotime($req['time_to'])) : '' ?></span></td>
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
            </td>
            <td>
                <?php if($req['status'] === 'pending'): ?>
                    <button class="btn btn-primary btn-sm" onclick="openReview(
                        '<?= $req['id'] ?>', 
                        '<?= addslashes(htmlspecialchars($req['first_name'] . ' ' . $req['last_name'])) ?>', 
                        '<?= htmlspecialchars($req['type']) ?>', 
                        '<?= date('d-m-Y', strtotime($req['from_date'])) ?>', 
                        '<?= date('d-m-Y', strtotime($req['to_date'])) ?>', 
                        '<?= addslashes(htmlspecialchars($req['reason'])) ?>'
                    )">Beoordelen</button>
                <?php else: ?>
                    <span style="font-size:12px;color:var(--text3)">Beoordeeld</span>
                <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          
          <?php if(count($all_requests) === 0): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text3);padding:30px;">Er zijn nog geen aanvragen ingediend in het portaal.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <div class="modal-overlay" id="modal-review">
    <div class="modal">
      <h3>Aanvraag beoordelen</h3>
      
      <div id="review-detail" style="background:var(--surface2);border-radius:8px;padding:13px;margin-bottom:14px;font-size:13.5px;line-height:1.9">
          </div>

      <form method="POST" action="verlof_beheer.php">
          <input type="hidden" name="request_id" id="hidden_request_id" value="">
          <input type="hidden" name="action" id="hidden_action" value="">

          <div class="field">
              <label>Opmerking voor medewerker (optioneel)</label>
              <textarea name="admin_note" rows="2" placeholder="Bijv. Akkoord, geniet ervan! Of: Helaas, dan is het te druk."></textarea>
          </div>
          
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-review')">Annuleren</button>
            <button type="submit" class="btn btn-danger" onclick="document.getElementById('hidden_action').value='reject'">✕ Weigeren</button>
            <button type="submit" class="btn btn-primary" onclick="document.getElementById('hidden_action').value='approve'">✓ Goedkeuren</button>
          </div>
      </form>
    </div>
  </div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openReview(id, name, type, from, to, reason) {
    document.getElementById('hidden_request_id').value = id;
    
    // Zet de gegevens in het grijze vak in de pop-up
    document.getElementById('review-detail').innerHTML = `
        <strong>${name}</strong><br>
        Type: ${type}<br>
        Periode: ${from} – ${to}<br>
        ${reason ? `Reden: ${reason}` : ''}
    `;
    
    openModal('modal-review');
}

// Sluit bij klikken buiten de pop-up
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>