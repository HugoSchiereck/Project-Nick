<?php
// dashboard.php
require 'config.php';

// Controleer of de gebruiker is ingelogd. Zo niet, stuur terug naar index.php
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Haal de gegevens van de ingelogde gebruiker op uit de database
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

$isAdmin = ($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MST Logistics — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* Basis opmaak uit jullie originele HTML */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
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
.sidebar-bottom{margin-top:auto;padding:0 10px;}
.user-chip{display:flex;align-items:center;gap:9px;padding:9px;border-radius:8px;border:1px solid var(--border);text-decoration:none;color:var(--text);}
.user-chip:hover{background:var(--surface2);}
.avatar{width:28px;height:28px;border-radius:50%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--accent);}
.main{margin-left:228px;padding:28px 32px;}
.page-header h1{font-size:22px;font-weight:600;}
.page-header p{color:var(--text2);font-size:13px;margin-top:3px;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px;margin-top:18px;}
</style>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-logo-wrap">
      <img src="https://mstlogistics.nl/assets/images/logo/logo.svg" alt="MST Logistics">
    </div>

    <div class="nav-section">
      <span class="nav-label">Menu</span>
      <a href="dashboard.php" class="nav-item" style="background:var(--accent-light);color:var(--accent);font-weight:500;">Dashboard</a>
      <a href="#" class="nav-item">Verlofaanvragen</a>
      <a href="#" class="nav-item">Code 95</a>
    </div>

    <div class="sidebar-bottom">
      <a href="logout.php" class="user-chip" title="Uitloggen">
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
      <h1>Welkom, <?= htmlspecialchars($currentUser['first_name']) ?>!</h1>
      <p>Je bent succesvol ingelogd op het nieuwe, beveiligde portaal.</p>
    </div>

    <div class="card">
      <h3>Applicatie in opbouw</h3>
      <p style="margin-top:10px; color:var(--text2);">We gaan nu stap voor stap de statische HTML-onderdelen (zoals verlof en medewerkers) omzetten naar dynamische tabellen die direct uit jouw MySQL-database laden.</p>
    </div>
  </main>

</body>
</html>