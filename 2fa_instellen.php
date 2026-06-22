<?php
// 2fa_instellen.php
require 'config.php';
require 'GoogleAuthenticator.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$ga = new GoogleAuthenticator();
$success_msg = '';
$error_msg = '';

// Als 2FA nog niet aan staat en er is nog geen tijdelijke secret, maak er een
if (!$user['google2fa_enabled'] && empty($_SESSION['temp_tfa_secret'])) {
    $_SESSION['temp_tfa_secret'] = $ga->createSecret();
}

$secret = $user['google2fa_enabled'] ? $user['google2fa_secret'] : $_SESSION['temp_tfa_secret'];
$qrText = $ga->getQRText($user['username'], $secret, 'MST Logistics');
// Veilige publieke API voor QR codes
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrText);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'verify_and_enable') {
        $code = trim($_POST['code'] ?? '');
        
        if ($ga->verifyCode($secret, $code, 2)) { // 2 = marge van 1 minuut voor tijdsverschillen
            $stmt = $pdo->prepare("UPDATE users SET google2fa_secret = ?, google2fa_enabled = 1 WHERE id = ?");
            $stmt->execute([$secret, $user['id']]);
            
            // Log in de audit log
            $pdo->exec("INSERT INTO audit_log (user_id, category, action, detail) VALUES ({$user['id']}, 'systeem', '2FA Ingeschakeld', 'Gebruiker heeft Two-Factor Authentication geactiveerd')");
            
            unset($_SESSION['temp_tfa_secret']);
            $success_msg = "Two-Factor Authentication is succesvol ingeschakeld! 🎉";
            $user['google2fa_enabled'] = 1;
        } else {
            $error_msg = "Ongeldige verificatiecode. Probeer het opnieuw.";
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'disable_2fa') {
        $stmt = $pdo->prepare("UPDATE users SET google2fa_secret = NULL, google2fa_enabled = 0 WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        $pdo->exec("INSERT INTO audit_log (user_id, category, action, detail) VALUES ({$user['id']}, 'systeem', '2FA Uitgeschakeld', 'Gebruiker heeft Two-Factor Authentication gedeactiveerd')");
        
        $success_msg = "Two-Factor Authentication is uitgeschakeld.";
        $user['google2fa_enabled'] = 0;
        $_SESSION['temp_tfa_secret'] = $ga->createSecret();
        $secret = $_SESSION['temp_tfa_secret'];
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>2FA Instellen — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
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
.main{margin-left:228px;padding:28px 32px;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:18px;max-width:500px;}
.card-header{padding:14px 18px;border-bottom:1px solid var(--border);background:var(--surface2);font-weight:600;}
.card-body-pad{padding:18px 20px;}
.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-success{background:var(--green-light);color:var(--green);border:1px solid #B0D9C0;}
.alert-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 17px;border-radius:8px;border:none;font-family:var(--font);font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;}
.btn-primary{background:var(--accent);color:#fff;}
.btn-danger{background:var(--danger);color:#fff;}
.field input{width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:8px;font-size:14px;outline:none;margin-bottom:12px;text-align:center;letter-spacing:4px;font-weight:600;}
</style>
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main">
    <div class="card">
      <div class="card-header">Two-Factor Authentication (2FA)</div>
      <div class="card-body-pad">
        
        <?php if($success_msg): ?><div class="alert alert-success"><?= $success_msg ?></div><?php endif; ?>
        <?php if($error_msg): ?><div class="alert alert-danger"><?= $error_msg ?></div><?php endif; ?>

        <?php if($user['google2fa_enabled']): ?>
            <div class="alert alert-success" style="margin-bottom:20px;">✓ 2FA staat momenteel **ingeschakeld** op jouw account.</div>
            <p style="font-size:13.5px; color:var(--text2); margin-bottom:20px;">Je account is extra beveiligd. Bij elke inlogpoging moet je de code uit je Authenticator-app invoeren.</p>
            <form method="POST" action="2fa_instellen.php" onsubmit="return confirm('Weet je zeker dat je 2FA wilt uitschakelen? Dit verlaagt de veiligheid van je account.');">
                <input type="hidden" name="action" value="disable_2fa">
                <button type="submit" class="btn btn-danger">2FA Uitschakelen</button>
            </form>
        <?php else: ?>
            <p style="font-size:13.5px; color:var(--text2); margin-bottom:16px;">Scan de onderstaande QR-code met een Authenticator-app (zoals Google Authenticator, Microsoft Authenticator of Bitwarden) om je account te koppelen.</p>
            
            <div style="text-align:center; margin:20px 0;">
                <img src="<?= $qrCodeUrl ?>" alt="QR Code" style="border:1px solid var(--border); padding:8px; background:#fff; border-radius:8px;">
                <div style="font-family:monospace; font-size:12px; margin-top:8px; color:var(--text3);">Handmatige code: <?= $secret ?></div>
            </div>

            <form method="POST" action="2fa_instellen.php">
                <input type="hidden" name="action" value="verify_and_enable">
                <div class="field">
                    <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px; text-align:center;">Voer de 6-cijferige code uit de app in ter controle:</label>
                    <input type="text" name="code" placeholder="000000" maxlength="6" required autocomplete="off">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Koppelen & Inschakelen</button>
            </form>
        <?php endif; ?>

      </div>
    </div>
  </main>

</body>
</html>