<?php
// dashboard.php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Haal huidige gebruiker op
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();

$isAdmin = ($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager');
$today = date('Y-m-d');
$in_90_days = date('Y-m-d', strtotime('+90 days'));

// ==========================================
// 1. DATA VOOR BEHEERDERS DASHBOARD
// ==========================================
if ($isAdmin) {
    // Aantal medewerkers
    $emp_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
    
    // Openstaande aanvragen
    $pending_reqs = $pdo->query("
        SELECT r.*, u.first_name, u.last_name 
        FROM requests r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.status = 'pending' 
        ORDER BY r.created_at ASC
    ")->fetchAll();

    // Code 95 waarschuwingen (verlopen of < 90 dagen)
    // LET OP: Hiervoor moet de tabel 'code95_status' wel bestaan (die hebben we bij code95.php aangemaakt)
    $c95_alerts = [];
    try {
        $c95_alerts = $pdo->query("
            SELECT c.*, u.first_name, u.last_name 
            FROM code95_status c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.expiry_date <= '$in_90_days'
            ORDER BY c.expiry_date ASC
        ")->fetchAll();
    } catch (PDOException $e) { /* Tabel bestaat nog niet of is leeg */ }

    // TCVT waarschuwingen
    $tcvt_alerts = [];
    try {
        $tcvt_alerts = $pdo->query("
            SELECT t.*, u.first_name, u.last_name 
            FROM tcvt_registrations t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.expiry_date <= '$in_90_days'
            ORDER BY t.expiry_date ASC
        ")->fetchAll();
    } catch (PDOException $e) { /* Tabel bestaat nog niet of is leeg */ }

    // Opleidingen waarschuwingen
    $edu_alerts = [];
    try {
        $edu_alerts = $pdo->query("
            SELECT ue.*, u.first_name, u.last_name, et.name as edu_name 
            FROM user_educations ue 
            JOIN users u ON ue.user_id = u.id 
            JOIN education_types et ON ue.type_id = et.id 
            WHERE ue.expiry_date IS NOT NULL AND ue.expiry_date <= '$in_90_days'
            ORDER BY ue.expiry_date ASC
        ")->fetchAll();
    } catch (PDOException $e) { /* Tabel bestaat nog niet of is leeg */ }
}

// ==========================================
// 2. DATA VOOR MEDEWERKERS DASHBOARD
// ==========================================
if (!$isAdmin) {
    // Mijn verlofaanvragen
    $stmtMyReqs = $pdo->prepare("SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmtMyReqs->execute([$user_id]);
    $my_reqs = $stmtMyReqs->fetchAll();
    
    $my_pending = count(array_filter($my_reqs, function($r) { return $r['status'] === 'pending'; }));
    $my_total_reqs = $pdo->prepare("SELECT COUNT(*) FROM requests WHERE user_id = ?");
    $my_total_reqs->execute([$user_id]);
    $my_reqs_count = $my_total_reqs->fetchColumn();

    // Mijn Code 95 Uren
    $c95_hours = 0;
    try {
        $stmtC95 = $pdo->prepare("SELECT SUM(c.hours) FROM courses c JOIN course_participants cp ON c.id = cp.course_id WHERE cp.user_id = ?");
        $stmtC95->execute([$user_id]);
        $c95_hours = $stmtC95->fetchColumn() ?: 0;
    } catch (PDOException $e) {}

    // Mijn TCVT Modules
    $tcvt_mod_count = 0;
    try {
        $stmtTcvt = $pdo->prepare("SELECT modules FROM tcvt_registrations WHERE user_id = ?");
        $stmtTcvt->execute([$user_id]);
        $my_tcvt = $stmtTcvt->fetchColumn();
        if ($my_tcvt) {
            $tcvt_mod_count = count(explode(',', $my_tcvt));
        }
    } catch (PDOException $e) {}

    // Mijn Opleidingen (voor samenvatting)
    $my_edus = [];
    try {
        $stmtEdu = $pdo->prepare("SELECT ue.*, et.name as edu_name FROM user_educations ue JOIN education_types et ON ue.type_id = et.id WHERE ue.user_id = ? ORDER BY ue.expiry_date ASC LIMIT 4");
        $stmtEdu->execute([$user_id]);
        $my_edus = $stmtEdu->fetchAll();
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* CSS Styling */
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

/* Dashboard Stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:24px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;}
.stat-card-label{font-size:11px;color:var(--text3);font-weight:500;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.stat-card-value{font-size:26px;font-weight:600;letter-spacing:-1px;}
.stat-card-value.green{color:var(--green);}
.stat-card-value.amber{color:var(--accent2);}
.stat-card-value.red{color:var(--danger);}

/* Alerts */
.alert{padding:11px 15px;border-radius:var(--radius);font-size:13px;margin-bottom:14px;line-height:1.5;}
.alert-warning{background:var(--accent2-light);color:#7A4F10;border:1px solid #F5D9B0;}
.alert-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;}

/* Tables & Cards */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:18px;overflow:hidden;}
.card-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--surface2);}
.card-header h3{font-size:14px;font-weight:600;}
.card-body-pad{padding:18px 20px;}
table{width:100%;border-collapse:collapse;}
th{font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);padding:10px 18px;text-align:left;background:var(--surface2);}
td{padding:11px 18px;font-size:13.5px;border-top:1px solid var(--border);vertical-align:middle;}
tr:hover td{background:#FAFAF8;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:6px 12px;border-radius:8px;border:none;font-family:var(--font);font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;}
.btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{opacity:.88;}
.tag{display:inline-block;padding:2px 8px;border-radius:5px;font-size:11px;background:var(--surface2);color:var(--text2);border:1px solid var(--border);}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:500;}
.badge-pending{background:#FEF5E7;color:#B7770D;}
.badge-approved{background:var(--green-light);color:var(--green);}
.badge-rejected{background:var(--danger-light);color:var(--danger);}
.badge-expired{background:var(--danger-light);color:var(--danger);}
.badge-ok{background:var(--green-light);color:var(--green);}
</style>
</head>
<body>

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo-wrap">
      <img src="https://mstlogistics.nl/assets/images/logo/logo.svg" alt="MST Logistics">
    </div>

    <!-- Als medewerker inlogt -->
    <?php if(!$isAdmin): ?>
    <div class="nav-section">
      <span class="nav-label">Mijn Portaal</span>
      <a href="dashboard.php" class="nav-item active">Dashboard</a>
      <a href="mijn_verlof.php" class="nav-item">Mijn verlofaanvragen</a>
    </div>
    <?php endif; ?>

    <!-- Als admin/manager inlogt -->
    <?php if($isAdmin): ?>
    <div class="nav-section">
      <span class="nav-label">Overzicht</span>
      <a href="dashboard.php" class="nav-item active">Dashboard</a>
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
      <a href="tcvt.php" class="nav-item">TCVT</a>
      <a href="opleidingen.php" class="nav-item">Opleidingen</a>
    </div>

    <div class="nav-section" style="margin-top:20px;">
      <span class="nav-label">Beheer</span>
      <a href="onboarding.php" class="nav-item">Onboarding</a>
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

  <!-- MAIN CONTENT -->
  <main class="main">
    <div class="page-header">
      <div class="page-header-text">
        <h1>Welkom, <?= htmlspecialchars($currentUser['first_name']) ?></h1>
        <p><?= $isAdmin ? 'Overzicht MST Logistics personeelsportaal' : 'Jouw persoonlijk overzicht' ?></p>
      </div>
    </div>

    <?php if($isAdmin): ?>
        <!-- ==================================================== -->
        <!-- VIEW: ADMIN DASHBOARD -->
        <!-- ==================================================== -->
        
        <div class="stats-grid">
          <div class="stat-card"><div class="stat-card-label">Medewerkers</div><div class="stat-card-value"><?= $emp_count ?></div></div>
          <div class="stat-card"><div class="stat-card-label">Open aanvragen</div><div class="stat-card-value <?= count($pending_reqs) > 0 ? 'amber' : 'green' ?>"><?= count($pending_reqs) ?></div></div>
          <div class="stat-card"><div class="stat-card-label">Code 95 aandacht</div><div class="stat-card-value <?= count($c95_alerts) > 0 ? 'red' : 'green' ?>"><?= count($c95_alerts) ?></div></div>
          <div class="stat-card"><div class="stat-card-label">TCVT aandacht</div><div class="stat-card-value <?= count($tcvt_alerts) > 0 ? 'amber' : 'green' ?>"><?= count($tcvt_alerts) ?></div></div>
        </div>

        <div id="admin-warnings">
            <?php if(count($c95_alerts) > 0): ?>
                <div class="alert alert-danger">
                    ⚠️ <strong>Code 95 verlopen of verloopt binnenkort:</strong> 
                    <?= implode(', ', array_map(function($a) { return $a['first_name'] . ' ' . $a['last_name']; }, $c95_alerts)) ?>
                </div>
            <?php endif; ?>
            
            <?php if(count($tcvt_alerts) > 0): ?>
                <div class="alert alert-warning">
                    🕐 <strong>TCVT aandacht vereist:</strong> 
                    <?= implode(', ', array_map(function($a) { return $a['first_name'] . ' ' . $a['last_name']; }, $tcvt_alerts)) ?>
                </div>
            <?php endif; ?>

            <?php if(count($edu_alerts) > 0): ?>
                <div class="alert alert-warning">
                    📋 <strong>Opleidingen verlopen/bijna verlopen:</strong> 
                    <?= implode(', ', array_map(function($a) { return $a['first_name'] . ' ' . $a['last_name'] . ' (' . $a['edu_name'] . ')'; }, $edu_alerts)) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
          <div class="card-header"><h3>Openstaande verlofaanvragen</h3></div>
          <table>
            <thead><tr><th>Medewerker</th><th>Type</th><th>Van</th><th>Tot</th><th>Ingediend</th><th>Actie</th></tr></thead>
            <tbody>
              <?php foreach($pending_reqs as $req): ?>
              <tr>
                <td><strong><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></strong></td>
                <td><span class="tag"><?= htmlspecialchars($req['type']) ?></span></td>
                <td><?= date('d-m-Y', strtotime($req['from_date'])) ?></td>
                <td><?= date('d-m-Y', strtotime($req['to_date'])) ?></td>
                <td style="font-size:12px;color:var(--text3)"><?= date('d-m-Y', strtotime($req['created_at'])) ?></td>
                <td><a href="verlof_beheer.php" class="btn btn-primary">Bekijken →</a></td>
              </tr>
              <?php endforeach; ?>
              <?php if(count($pending_reqs) === 0): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text3);padding:20px;">Alle aanvragen zijn netjes weggewerkt!</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

    <?php else: ?>
        <!-- ==================================================== -->
        <!-- VIEW: EMPLOYEE DASHBOARD -->
        <!-- ==================================================== -->
        
        <div class="stats-grid">
          <div class="stat-card"><div class="stat-card-label">Aanvragen totaal</div><div class="stat-card-value"><?= $my_reqs_count ?></div></div>
          <div class="stat-card"><div class="stat-card-label">In behandeling</div><div class="stat-card-value <?= $my_pending > 0 ? 'amber' : 'green' ?>"><?= $my_pending ?></div></div>
          <div class="stat-card"><div class="stat-card-label">Code 95 uren</div><div class="stat-card-value <?= $c95_hours >= 35 ? 'green' : 'amber' ?>"><?= $c95_hours ?>/35</div></div>
          <div class="stat-card"><div class="stat-card-label">TCVT modules</div><div class="stat-card-value <?= $tcvt_mod_count == 4 ? 'green' : 'amber' ?>"><?= $tcvt_mod_count ?>/4</div></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
          <div class="card" style="margin:0">
            <div class="card-header"><h3>Recente aanvragen</h3></div>
            <table>
              <thead><tr><th>Type</th><th>Van</th><th>Tot</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach($my_reqs as $req): ?>
                <tr>
                  <td><span class="tag"><?= htmlspecialchars($req['type']) ?></span></td>
                  <td><?= date('d-m-Y', strtotime($req['from_date'])) ?></td>
                  <td><?= date('d-m-Y', strtotime($req['to_date'])) ?></td>
                  <td>
                    <?php if($req['status'] === 'pending'): ?>
                        <span class="badge badge-pending">In behandeling</span>
                    <?php elseif($req['status'] === 'approved'): ?>
                        <span class="badge badge-approved">Goedgekeurd</span>
                    <?php elseif($req['status'] === 'rejected'): ?>
                        <span class="badge badge-rejected">Geweigerd</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($my_reqs) === 0): ?>
                  <tr><td colspan="4" style="text-align:center;color:var(--text3);padding:20px;">Nog geen aanvragen ingediend.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          
          <div class="card" style="margin:0">
            <div class="card-header"><h3>Mijn certificeringen (Samenvatting)</h3></div>
            <div class="card-body-pad">
              <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
                  <span style="font-size:13px;font-weight:500;">Code 95</span>
                  <?php if($c95_hours >= 35): ?>
                      <span class="badge badge-ok">Compleet</span>
                  <?php else: ?>
                      <span class="badge badge-warning">Nog <?= 35 - $c95_hours ?> uur nodig</span>
                  <?php endif; ?>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
                  <span style="font-size:13px;font-weight:500;">TCVT</span>
                  <?php if($tcvt_mod_count == 4): ?>
                      <span class="badge badge-ok">Compleet</span>
                  <?php else: ?>
                      <span class="badge badge-warning"><?= $tcvt_mod_count ?>/4 modules</span>
                  <?php endif; ?>
              </div>
              
              <?php foreach($my_edus as $edu): ?>
                  <?php $exp = $edu['expiry_date'] && $edu['expiry_date'] < $today; ?>
                  <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
                      <span style="font-size:13px;font-weight:500;"><?= htmlspecialchars($edu['edu_name']) ?></span>
                      <?= $exp ? '<span class="badge badge-expired">Verlopen</span>' : '<span class="badge badge-ok">Geldig</span>' ?>
                  </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

    <?php endif; ?>
  </main>

</body>
</html>