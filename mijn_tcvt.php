<?php
// mijn_tcvt.php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$currentUser = $stmt->fetch();

// Haal TCVT gegevens op
$stmtTcvt = $pdo->prepare("SELECT * FROM tcvt_registrations WHERE user_id = ?");
$stmtTcvt->execute([$user_id]);
$tcvt = $stmtTcvt->fetch();

$today = date('Y-m-d');
$modules = $tcvt && $tcvt['modules'] ? explode(',', $tcvt['modules']) : [];
$all_done = count($modules) === 4;
$expiry = $tcvt ? $tcvt['expiry_date'] : null;
$is_expired = $expiry && $expiry < $today;
$days_until = $expiry ? round((strtotime($expiry) - strtotime($today)) / 86400) : null;
$warn = $expiry && !$is_expired && $days_until <= 90;

$module_names = [
    'A' => 'Techniek',
    'B' => 'Rijvaardigheid',
    'C' => 'Gevaarlijke stoffen',
    'D' => 'Ladingzekering'
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mijn TCVT — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* Zelfde styling basis */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --accent2:#C8873A;--accent2-light:#FAF0E4;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
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
.page-header{margin-bottom:24px;}
.page-header h1{font-size:22px;font-weight:600;}
.page-header p{color:var(--text2);font-size:13px;margin-top:3px;}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:24px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;}
.stat-card-label{font-size:11px;color:var(--text3);font-weight:500;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.stat-card-value{font-size:26px;font-weight:600;letter-spacing:-1px;}
.stat-card-value.green{color:var(--green);}
.stat-card-value.amber{color:var(--accent2);}
.stat-card-value.red{color:var(--danger);}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:18px;overflow:hidden;}
.card-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--surface2);}
.card-header h3{font-size:14px;font-weight:600;}
.card-body-pad{padding:18px 20px;}
.alert{padding:11px 15px;border-radius:var(--radius);font-size:13px;margin-bottom:14px;line-height:1.5;}
.alert-warning{background:var(--accent2-light);color:#7A4F10;border:1px solid #F5D9B0;}
.alert-info{background:var(--blue-light);color:#1A4F7A;border:1px solid #B8D3EF;}
.alert-success{background:var(--green-light);color:#1F5E37;border:1px solid #B0D9C0;}
.alert-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;}
.badge-ok{background:var(--green-light);color:var(--green);padding:2px 9px;border-radius:20px;font-size:11px;font-weight:500;}
.badge-none{background:var(--surface2);color:var(--text3);padding:2px 9px;border-radius:20px;font-size:11px;font-weight:500;}
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
      <a href="mijn_verlof.php" class="nav-item">Mijn verlofaanvragen</a>
      <a href="mijn_code95.php" class="nav-item">Mijn Code 95</a>
      <a href="mijn_tcvt.php" class="nav-item active">Mijn TCVT</a>
    </div>

    <?php if($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager'): ?>
    <div class="nav-section" style="margin-top:20px;">
      <span class="nav-label">Beheerders Menu</span>
      <a href="tcvt.php" class="nav-item">← Terug naar beheer</a>
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
      <h1>Mijn TCVT</h1>
      <p>Vakbekwaamheid certificering laad- en loskranen</p>
    </div>

    <?php if(!$tcvt): ?>
        <div class="alert alert-info">Geen TCVT-registratie gevonden in het systeem. Mocht dit niet kloppen, neem dan contact op met de beheerder.</div>
    <?php else: ?>

        <?php if($is_expired): ?>
            <div class="alert alert-danger">⚠️ Je TCVT-certificering is verlopen. Neem direct contact op.</div>
        <?php elseif(!$all_done): ?>
            <div class="alert alert-warning">⚠️ Je hebt nog niet alle TCVT-modules afgerond (<?= count($modules) ?>/4). Let hierop voor je volgende hercertificering.</div>
        <?php elseif($warn): ?>
            <div class="alert alert-warning">🔔 Let op: Je TCVT-certificering verloopt over <?= $days_until ?> dagen.</div>
        <?php else: ?>
            <div class="alert alert-success">✓ Je TCVT is volledig gecertificeerd. Alle modules zijn succesvol afgerond.</div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-card-label">Modules afgerond</div><div class="stat-card-value <?= $all_done ? 'green' : 'amber' ?>"><?= count($modules) ?>/4</div></div>
            <div class="stat-card"><div class="stat-card-label">Gecertificeerd tot</div><div class="stat-card-value <?= $is_expired ? 'red' : ($warn ? 'amber' : 'green') ?>" style="font-size:18px"><?= $expiry ? date('d-m-Y', strtotime($expiry)) : '—' ?></div></div>
            <div class="stat-card"><div class="stat-card-label">Hercertificering uiterlijk</div><div class="stat-card-value" style="font-size:18px"><?= $tcvt['recert_date'] ? date('d-m-Y', strtotime($tcvt['recert_date'])) : '—' ?></div></div>
        </div>

        <div class="card">
            <div class="card-header"><h3>Module status</h3></div>
            <div class="card-body-pad">
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
                    <?php foreach(['A','B','C','D'] as $m): ?>
                        <?php $checked = in_array($m, $modules); ?>
                        <div style="text-align:center;padding:16px 8px;border:2px solid <?= $checked ? 'var(--green)' : 'var(--border)' ?>;border-radius:10px;background:<?= $checked ? 'var(--green-light)' : 'var(--surface2)' ?>">
                            <div style="font-size:22px;font-weight:700;color:<?= $checked ? 'var(--green)' : 'var(--text3)' ?>"><?= $m ?></div>
                            <div style="font-size:11px;margin-top:4px;color:var(--text3)"><?= $module_names[$m] ?></div>
                            <div style="margin-top:6px;font-size:12px">
                                <?= $checked ? '<span class="badge-ok">✓</span>' : '<span class="badge-none">—</span>' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if($tcvt['hercursus']): ?>
                    <div style="margin-top:14px;padding:10px;background:var(--surface2);border-radius:8px;font-size:13px;color:var(--text2)">
                        <strong>Opgegeven hercursus:</strong> <?= htmlspecialchars($tcvt['hercursus']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
  </main>
</body>
</html>