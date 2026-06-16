<?php
// mijn_code95.php
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

// 1. Haal Code 95 status op (vervaldatum)
$stmtStatus = $pdo->prepare("SELECT expiry_date, note FROM code95_status WHERE user_id = ?");
$stmtStatus->execute([$user_id]);
$c95_status = $stmtStatus->fetch();
$expiry = $c95_status ? $c95_status['expiry_date'] : null;

// 2. Haal gevolgde cursussen op
$stmtCourses = $pdo->prepare("
    SELECT c.* FROM courses c 
    JOIN course_participants cp ON c.id = cp.course_id 
    WHERE cp.user_id = ? 
    ORDER BY c.course_date DESC
");
$stmtCourses->execute([$user_id]);
$my_courses = $stmtCourses->fetchAll();

// Bereken uren
$theorie = 0; $praktijk = 0;
foreach ($my_courses as $c) {
    if ($c['type'] === 'theorie') $theorie += $c['hours'];
    if ($c['type'] === 'praktijk') $praktijk += $c['hours'];
}
$totaal = $theorie + $praktijk;
$has_praktijk = $praktijk > 0;
$pct = min(100, round(($totaal / 35) * 100));

$today = date('Y-m-d');
$is_expired = $expiry && $expiry < $today;
$days_until = $expiry ? round((strtotime($expiry) - strtotime($today)) / 86400) : null;
$warn = $expiry && !$is_expired && $days_until <= 90;

$bar_color = $is_expired ? 'red' : ($warn ? 'amber' : 'green');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mijn Code 95 — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --accent2:#C8873A;--accent2-light:#FAF0E4;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --blue:#1A5EA8;--blue-light:#EBF3FB;
  --purple:#6B21A8;--purple-light:#F3E8FD;
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
.page-header{margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.page-header-text h1{font-size:22px;font-weight:600;}
.page-header-text p{color:var(--text2);font-size:13px;margin-top:3px;}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:24px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;}
.stat-card-label{font-size:11px;color:var(--text3);font-weight:500;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
.stat-card-value{font-size:26px;font-weight:600;letter-spacing:-1px;}
.stat-card-value.green{color:var(--green);}
.stat-card-value.amber{color:var(--accent2);}
.stat-card-value.red{color:var(--danger);}
.stat-card-value.blue{color:var(--blue);}
.stat-card-value.purple{color:var(--purple);}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:18px;overflow:hidden;}
.card-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--surface2);}
.card-header h3{font-size:14px;font-weight:600;}
.card-body-pad{padding:18px 20px;}
table{width:100%;border-collapse:collapse;}
th{font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);padding:10px 18px;text-align:left;background:var(--surface2);}
td{padding:11px 18px;font-size:13.5px;border-top:1px solid var(--border);vertical-align:middle;}
tr:hover td{background:#FAFAF8;}
.alert{padding:11px 15px;border-radius:var(--radius);font-size:13px;margin-bottom:14px;line-height:1.5;}
.alert-warning{background:var(--accent2-light);color:#7A4F10;border:1px solid #F5D9B0;}
.alert-info{background:var(--blue-light);color:#1A4F7A;border:1px solid #B8D3EF;}
.alert-success{background:var(--green-light);color:#1F5E37;border:1px solid #B0D9C0;}
.alert-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;}
.bar-wrap{background:var(--surface2);border-radius:4px;height:6px;flex:1;}
.bar{height:6px;border-radius:4px;transition:width .3s;}
.bar.green{background:var(--green);}
.bar.amber{background:var(--accent2);}
.bar.red{background:var(--danger);}
.badge-praktijk{background:var(--blue-light);color:var(--blue);border:1px solid #C0D4F5; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:500;}
.badge-theorie{background:var(--purple-light);color:var(--purple);border:1px solid #DBBCF5; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:500;}
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
      <a href="mijn_code95.php" class="nav-item active">Mijn Code 95</a>
      <a href="mijn_tcvt.php" class="nav-item">Mijn TCVT</a>
    </div>

    <?php if($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager'): ?>
    <div class="nav-section" style="margin-top:20px;">
      <span class="nav-label">Beheerders Menu</span>
      <a href="code95.php" class="nav-item">← Terug naar beheer</a>
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
        <h1>Mijn Code 95</h1>
        <p>Bijscholingsstatus en gevolgde cursussen</p>
      </div>
    </div>

    <?php if(!$expiry): ?>
        <div class="alert alert-info">Er is voor jou (nog) geen Code 95 verplichting geregistreerd in het systeem.</div>
    <?php else: ?>
        
        <?php if($is_expired): ?>
            <div class="alert alert-danger">⚠️ Je Code 95 certificering is verlopen. Neem direct contact op met de planning/beheerder.</div>
        <?php elseif($warn): ?>
            <div class="alert alert-warning">🔔 Let op: Je Code 95 verloopt over <?= $days_until ?> dagen. Zorg dat je uren compleet zijn!</div>
        <?php elseif(!$has_praktijk): ?>
            <div class="alert alert-warning">⚠️ Je hebt nog geen praktijkcursus gevolgd. Dit is verplicht voor het behalen van de 35 uur.</div>
        <?php elseif($totaal >= 35): ?>
            <div class="alert alert-success">✓ Jouw Code 95 bijscholing is volledig in orde.</div>
        <?php else: ?>
            <div class="alert alert-info">Je hebt nog <?= 35 - $totaal ?> uur bijscholing nodig voor je volgende vervaldatum.</div>
        <?php endif; ?>

        <div class="stats-grid">
          <div class="stat-card"><div class="stat-card-label">📖 Theorie</div><div class="stat-card-value purple"><?= $theorie ?></div></div>
          <div class="stat-card"><div class="stat-card-label">🚛 Praktijk<?= $has_praktijk ? ' ✓' : ' ⚠' ?></div><div class="stat-card-value blue"><?= $praktijk ?></div></div>
          <div class="stat-card"><div class="stat-card-label">Totaal / 35</div><div class="stat-card-value <?= $bar_color ?>"><?= $totaal ?></div></div>
          <div class="stat-card"><div class="stat-card-label">Vervaldatum</div><div class="stat-card-value <?= $bar_color ?>" style="font-size:18px"><?= date('d-m-Y', strtotime($expiry)) ?></div></div>
        </div>

        <div class="card">
            <div class="card-header"><h3>Voortgang</h3></div>
            <div class="card-body-pad">
                <div class="bar-wrap" style="height:12px;border-radius:6px">
                    <div class="bar <?= $bar_color ?>" style="width:<?= $pct ?>%;height:12px;border-radius:6px"></div>
                </div>
                <div style="font-size:12px;color:var(--text3);margin-top:4px"><?= $pct ?>% — <?= $totaal ?>/35 uur behaald</div>
            </div>
        </div>

    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3>Gevolgde cursussen</h3></div>
        <table>
            <thead><tr><th>Datum</th><th>Cursus</th><th>Type</th><th>Uren</th></tr></thead>
            <tbody>
                <?php foreach($my_courses as $c): ?>
                <tr>
                    <td><?= date('d-m-Y', strtotime($c['course_date'])) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($c['name']) ?></strong>
                        <?php if($c['provider']): ?><br><span style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($c['provider']) ?></span><?php endif; ?>
                    </td>
                    <td>
                        <?php if($c['type'] === 'praktijk'): ?>
                            <span class="badge-praktijk">🚛 Praktijk</span>
                        <?php else: ?>
                            <span class="badge-theorie">📖 Theorie</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $c['hours'] ?> u</td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($my_courses) === 0): ?>
                    <tr><td colspan="4" style="text-align:center;color:var(--text3);padding:20px;">Nog geen cursussen geregistreerd.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

  </main>
</body>
</html>