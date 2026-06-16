<?php
// medewerker_toevoegen.php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'manager') {
    die("Je hebt geen toegang tot deze pagina.");
}

$error = '';
$success = '';

// Verwerk het formulier als er op 'Opslaan' is geklikt
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pnr = trim($_POST['pnr'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $function_title = trim($_POST['function_title'] ?? '');

    // Verplichte velden checken
    if (empty($first_name) || empty($last_name) || empty($username) || empty($password)) {
        $error = 'Vul alle velden met een sterretje (*) in.';
    } else {
        // Controleren of de gebruikersnaam al bestaat
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->rowCount() > 0) {
            $error = 'Deze gebruikersnaam is al in gebruik. Kies een andere.';
        } else {
            // Wachtwoord veilig versleutelen
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Medewerker in de database zetten
            $insertStmt = $pdo->prepare("INSERT INTO users (pnr, first_name, last_name, email, username, password_hash, role, function_title) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            try {
                $insertStmt->execute([$pnr, $first_name, $last_name, $email, $username, $password_hash, $role, $function_title]);
                $success = 'Medewerker succesvol toegevoegd!';
            } catch(PDOException $e) {
                $error = 'Er is een fout opgetreden: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nieuwe Medewerker — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --radius:10px;--font:'DM Sans',sans-serif;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --success:#27AE60;--success-light:#EAF6F0;
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
.user-chip{display:flex;align-items:center;gap:9px;padding:9px;border-radius:8px;border:1px solid var(--border);text-decoration:none;color:var(--text);}
.avatar{width:28px;height:28px;border-radius:50%;background:var(--accent-light);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--accent);flex-shrink:0;}
.main{margin-left:228px;padding:28px 32px;}
.page-header{margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.page-header-text h1{font-size:22px;font-weight:600;letter-spacing:-.5px;}
.page-header-text p{color:var(--text2);font-size:13px;margin-top:3px;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:24px;max-width:800px;}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:6px;}
.field input, .field select{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:14px;outline:none;}
.field input:focus, .field select:focus{border-color:var(--accent);}
.field.full-width{grid-column: span 2;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border-radius:8px;border:none;font-family:var(--font);font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;}
.btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{opacity:.88;}
.btn-secondary{background:var(--surface2);color:var(--text);} .btn-secondary:hover{background:#E3DFD5;}
.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;line-height:1.4;}
.alert-error{background:var(--danger-light);color:var(--danger);}
.alert-success{background:var(--success-light);color:var(--success);}
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
        <h1>Nieuwe medewerker</h1>
        <p>Voeg een nieuw account toe aan het portaal</p>
      </div>
      <div>
        <a href="medewerkers.php" class="btn btn-secondary">Terug naar overzicht</a>
      </div>
    </div>

    <div class="card">
      <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <br><br><a href="medewerkers.php" style="color:var(--success);font-weight:600;">Bekijk in overzicht →</a></div><?php endif; ?>

      <form method="POST" action="medewerker_toevoegen.php">
        <div class="form-grid">
          
          <div class="field">
            <label>Voornaam *</label>
            <input type="text" name="first_name" required placeholder="Bijv. Jan">
          </div>
          <div class="field">
            <label>Achternaam *</label>
            <input type="text" name="last_name" required placeholder="Bijv. Janssen">
          </div>

          <div class="field">
            <label>Personeelsnummer (PNR)</label>
            <input type="text" name="pnr" placeholder="Bijv. 0102">
          </div>
          <div class="field">
            <label>Functietitel</label>
            <input type="text" name="function_title" placeholder="Bijv. Chauffeur">
          </div>

          <div class="field">
            <label>E-mailadres</label>
            <input type="email" name="email" placeholder="jan@mstlogistics.nl">
          </div>
          <div class="field">
            <label>Rol in het portaal *</label>
            <select name="role" required>
              <option value="employee">Medewerker (Standaard)</option>
              <option value="manager">Manager</option>
              <option value="admin">Hoofdbeheerder</option>
            </select>
          </div>

          <hr style="grid-column: span 2; border:none; border-top:1px solid var(--border); margin: 10px 0;">

          <div class="field">
            <label>Inlognaam *</label>
            <input type="text" name="username" required placeholder="Bijv. jan.janssen">
          </div>
          <div class="field">
            <label>Wachtwoord * (Medewerker kan dit later wijzigen)</label>
            <input type="text" name="password" required placeholder="Tijdelijk wachtwoord">
          </div>

        </div>

        <div style="margin-top: 24px; display:flex; gap: 12px;">
          <button type="submit" class="btn btn-primary">Opslaan</button>
        </div>
      </form>
    </div>
  </main>

</body>
</html>