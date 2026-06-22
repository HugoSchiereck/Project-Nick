<?php
// index.php
require 'config.php';
require 'GoogleAuthenticator.php';

// Als de gebruiker al volledig is ingelogd, stuur door naar dashboard
if (isset($_SESSION['user_id']) && !isset($_SESSION['tfa_pending'])) {
    header("Location: dashboard.php"); exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // SCENARIO A: Normaal wachtwoord inloggen
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['google2fa_enabled'] == 1) {
                // Gebruiker heeft 2FA aanstaan! Zet in de wachtrij
                $_SESSION['tfa_user_id'] = $user['id'];
                $_SESSION['tfa_pending'] = true;
            } else {
                // Geen 2FA? Direct doorloggen
                $_SESSION['user_id'] = $user['id'];
                header("Location: dashboard.php"); exit;
            }
        } else {
            $error = "Ongeldige gebruikersnaam of wachtwoord.";
        }
    }

    // SCENARIO B: 2FA Code Verificatie
    if (isset($_POST['action']) && $_POST['action'] === 'verify_2fa') {
        $code = trim($_POST['code'] ?? '');
        $uid = $_SESSION['tfa_user_id'] ?? null;

        if ($uid) {
            $stmt = $pdo->prepare("SELECT google2fa_secret FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $secret = $stmt->fetchColumn();

            $ga = new GoogleAuthenticator();
            if ($ga->verifyCode($secret, $code, 2)) {
                // Code klopt! Nu pas echt ingelogd
                $_SESSION['user_id'] = $uid;
                unset($_SESSION['tfa_user_id']);
                unset($_SESSION['tfa_pending']);
                header("Location: dashboard.php"); exit;
            } else {
                $error = "Onjuiste 2FA verificatiecode. Probeer het opnieuw.";
            }
        } else {
            $error = "Sessie verlopen. Log opnieuw in met je wachtwoord.";
            unset($_SESSION['tfa_pending']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MST Logistics — Inloggen</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--border:#DDD9D0;--accent:#D4351C;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;--danger:#C0392B;--danger-light:#FAEAEA;
  --radius:10px;--font:'DM Sans',sans-serif;
}
body{font-family:var(--font);background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.login-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:44px 44px 36px;width:100%;max-width:400px;}
.login-logo-wrap{display:flex;justify-content:center;margin-bottom:32px;}
.login-logo-wrap img{height:48px;}
.login-divider{border:none;border-top:1px solid var(--border);margin:0 0 24px;}
.login-card h2{font-size:20px;font-weight:600;margin-bottom:4px;letter-spacing:-.4px;}
.login-card > p{color:var(--text2);font-size:14px;margin-bottom:24px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field input{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius);font-size:14px;outline:none;}
.field input:focus{border-color:var(--accent);}
.btn-submit{width:100%;padding:11px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius);font-size:14px;font-weight:600;cursor:pointer;}
.error-msg{background:var(--danger-light);color:var(--danger);font-size:13px;padding:10px 14px;border-radius:var(--radius);margin-bottom:14px;border:1px solid #F5C6C3;}
</style>
</head>
<body>

<div class="login-card">
    <div class="login-logo-wrap">
      <img src="https://mstlogistics.nl/assets/images/logo/logo.svg" alt="MST Logistics">
    </div>
    <hr class="login-divider">

    <?php if($error): ?><div class="error-msg"><?= $error ?></div><?php endif; ?>

    <?php if(isset($_SESSION['tfa_pending']) && $_SESSION['tfa_pending'] === true): ?>
        <h2>Verificatie vereist</h2>
        <p>Voer de 6-cijferige beveiligingscode in uit je Authenticator-app om door te gaan.</p>
        
        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="verify_2fa">
            <div class="field">
                <label>Beveiligingscode</label>
                <input type="text" name="code" placeholder="000000" maxlength="6" required autocomplete="off" style="text-align:center; font-size:18px; letter-spacing:6px; font-weight:600;">
            </div>
            <button type="submit" class="btn-submit">Verifiëren & Inloggen →</button>
            <p style="text-align:center; margin-top:16px; font-size:12px;"><a href="logout.php" style="color:var(--text3); text-decoration:none;">Terug naar wachtwoord</a></p>
        </form>

    <?php else: ?>
        <h2>Personeelsportaal</h2>
        <p>Log in met je personeelsgegevens</p>
        
        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="login">
            <div class="field">
                <label>Gebruikersnaam</label>
                <input type="text" name="username" placeholder="voornaam.achternaam" required autocomplete="username">
            </div>
            <div class="field">
                <label>Wachtwoord</label>
                <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-submit">Inloggen</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>