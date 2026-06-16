<?php
// medewerkers.php
require 'config.php';

// Check of de gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Haal huidige gebruiker op
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

// Alleen admins en managers mogen deze pagina zien
$isAdmin = ($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager');
if (!$isAdmin) {
    die("Je hebt geen toegang tot deze pagina.");
}

// Haal ALLE gebruikers op uit de database
$stmtUsers = $pdo->query("SELECT * FROM users ORDER BY role ASC, first_name ASC");
$employees = $stmtUsers->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MST Logistics — Medewerkers</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* Basis CSS */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --radius:10px;--font:'DM Sans',sans-serif;--mono:'DM Mono',monospace;
}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;font-size:15px;}
.sidebar{position:fixed;top:0;left:0;width:228px;height:100vh;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;padding:0 0 16px;z-index:10;}
.sidebar-logo-wrap{padding:18px 16px 14px;border-bottom:1px solid var(--border);margin-bottom:12px;}
.sidebar-logo-wrap img{height:32px;width:auto;object-fit:contain;}
.nav-section{padding:0 10px;margin-bottom:2px;}
.nav-label{font-size:10px;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--text3);padding:6px 8px 3px;display:block;}
.nav-item{display:block;padding:8px 10px;border-radius:8px;text-decoration:none;font-size:13.5px;color:var(--text2);margin-bottom:1px; transition: background .12s;}
.nav-item:hover{background:var(--surface2);color:var(--text);}
.nav-item.active{background:var(--accent-light);color:var(--accent);font-weight:500;}
.sidebar-bottom{margin-top:auto;padding:0 10px;}
.user-chip{display:flex;align-items:center;gap:9px;padding:9px;border-radius:8px;border:1px solid var(--border);text-decoration:none;color:var(--text); transition: background .12s;}
.user-chip:hover{background:var(--surface2);}
.avatar{width:28px;height:28px;border-radius:50%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--accent);flex-shrink:0;}
.main{margin-left:228px;padding:28px 32px;}
.page-header{margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.page-header-text h1{font-size:22px;font-weight:600;letter-spacing:-.5px;}
.page-header-text p{color:var(--text2);font-size:13px;margin-top:3px;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:18px;}
table{width:100%;border-collapse:collapse;}
th{font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);padding:10px 18px;text-align:left;background:var(--surface2);}
td{padding:11px 18px;font-size:13.5px;border-top:1px solid var(--border);vertical-align:middle;}
tr:hover td{background:#FAFAF8;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 17px;border-radius:var(--radius);border:none;font-family:var(--font);font-size:14px;font-weight:500;cursor:pointer;transition:opacity .15s,transform .1s; text-decoration:none;}
.btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{opacity:.88;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:500;}
.badge-manager{background:#E8F8F5;color:#0E6655;border:1px solid #A9DFBF;}
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
      <a href="medewerkers.php" class="nav-item active">Medewerkers</a>
      <a href="#" class="nav-item">Verlofaanvragen</a>
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
        <h1>Medewerkers</h1>
        <p>Overzicht van alle actieve accounts uit de database</p>
      </div>
      <div class="page-header-actions">
        <a href="medewerker_toevoegen.php" class="btn btn-primary">+ Medewerker toevoegen</a>
      </div>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Pnr.</th>
            <th>Naam</th>
            <th>Gebruikersnaam</th>
            <th>E-mail</th>
            <th>Functie</th>
            <th>Rol</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($employees as $emp): ?>
          <tr>
            <td style="font-family:var(--mono);font-size:12px;color:var(--text3)">
                <?= htmlspecialchars($emp['pnr'] ?? '—') ?>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:9px">
                <div class="avatar" style="width:30px;height:30px;font-size:11px">
                    <?= htmlspecialchars(substr($emp['first_name'], 0, 1)) ?>
                </div>
                <div><strong><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></strong></div>
              </div>
            </td>
            <td style="font-family:var(--mono);font-size:12px;color:var(--text2)">
                <?= htmlspecialchars($emp['username']) ?>
            </td>
            <td style="font-size:12px;color:var(--text2)">
                <?= htmlspecialchars($emp['email'] ?? '—') ?>
            </td>
            <td style="font-size:12px">
                <?= htmlspecialchars($emp['function_title'] ?? '—') ?>
            </td>
            <td>
                <?php if ($emp['role'] === 'admin'): ?>
                    <span class="badge badge-manager" style="background:#FAEAE7;color:var(--accent);border-color:#F5C6C3">Hoofdbeheerder</span>
                <?php elseif ($emp['role'] === 'manager'): ?>
                    <span class="badge badge-manager">Manager</span>
                <?php else: ?>
                    <span style="font-size:12px;color:var(--text3)">Medewerker</span>
                <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

</body>
</html>