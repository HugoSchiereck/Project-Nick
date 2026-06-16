<?php
session_start();
// Als je al ingelogd bent, stuur direct door naar het dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MST Logistics — Personeelsportaal Login</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--border:#DDD9D0;
  --accent:#D4351C;--text:#1A1A18;--text2:#5A5A54;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --radius:10px;--font:'DM Sans',sans-serif;
}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;}
#login-screen{min-height:100vh;display:flex;align-items:center;justify-content:center;background-image:radial-gradient(circle at 20% 80%,rgba(212,53,28,.07) 0%,transparent 50%);}
.login-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:44px 44px 36px;width:100%;max-width:400px;box-shadow:0 10px 30px rgba(0,0,0,0.05);}
.login-logo-wrap{display:flex;justify-content:center;margin-bottom:32px;}
.login-logo-wrap img{height:48px;width:auto;object-fit:contain;}
.login-divider{border:none;border-top:1px solid var(--border);margin:0 0 24px;}
.login-card h2{font-size:20px;font-weight:600;margin-bottom:4px;}
.login-card>p{color:var(--text2);font-size:14px;margin-bottom:24px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field input{width:100%;padding:10px 13px;border:1px solid var(--border);border-radius:var(--radius);font-family:var(--font);font-size:14px;outline:none;transition:border-color .15s;}
.field input:focus{border-color:var(--accent);}
.btn-primary{width:100%;padding:11px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius);font-family:var(--font);font-size:15px;font-weight:600;cursor:pointer;transition:opacity .15s;}
.btn-primary:hover{opacity:.88;}
.error-msg{background:var(--danger-light);color:var(--danger);font-size:13px;padding:10px 14px;border-radius:var(--radius);margin-bottom:14px;display:none;line-height:1.5;}
#step-2fa { display: none; }
</style>
</head>
<body>

<div id="login-screen">
  <div class="login-card">
    <div class="login-logo-wrap">
      <img src="https://mstlogistics.nl/assets/images/logo/logo.svg" alt="MST Logistics">
    </div>
    <hr class="login-divider">
    
    <div id="step-login">
      <h2>Personeelsportaal</h2>
      <p>Log in met je accountgegevens</p>
      <div id="login-error" class="error-msg"></div>
      
      <div class="field">
        <label>Gebruikersnaam</label>
        <input type="text" id="login-user" placeholder="Bijv. jan.janssen" autocomplete="username">
      </div>
      <div class="field">
        <label>Wachtwoord</label>
        <input type="password" id="login-pass" placeholder="••••••••" autocomplete="current-password">
      </div>
      <button class="btn-primary" onclick="doLogin()" style="margin-top:10px">Inloggen</button>
    </div>

    <div id="step-2fa">
      <h2>Extra Beveiliging</h2>
      <p>We hebben een 6-cijferige code naar je e-mailadres gestuurd.</p>
      <div id="2fa-error" class="error-msg"></div>
      
      <div class="field">
        <label>Vul je code in</label>
        <input type="text" id="login-code" placeholder="123456" maxlength="6" style="letter-spacing:4px; font-size:18px; text-align:center;">
      </div>
      <button class="btn-primary" onclick="verify2FA()" style="margin-top:10px">Code verifiëren</button>
    </div>
    
  </div>
</div>

<script>
let tempUserId = null;

function doLogin() {
    const user = document.getElementById('login-user').value.trim();
    const pass = document.getElementById('login-pass').value;
    const errBox = document.getElementById('login-error');
    
    if(!user || !pass) {
        errBox.textContent = "Vul beide velden in.";
        errBox.style.display = "block";
        return;
    }

    const formData = new FormData();
    formData.append('action', 'login');
    formData.append('username', user);
    formData.append('password', pass);

    fetch('api_login.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.href = 'dashboard.php'; // Door naar het beveiligde portaal!
        } else if (data.status === 'require_2fa') {
            tempUserId = data.user_id;
            document.getElementById('step-login').style.display = 'none';
            document.getElementById('step-2fa').style.display = 'block';
        } else {
            errBox.textContent = data.message;
            errBox.style.display = "block";
        }
    })
    .catch(err => {
        errBox.textContent = "Er ging iets mis met de serververbinding.";
        errBox.style.display = "block";
    });
}

function verify2FA() {
    const code = document.getElementById('login-code').value.trim();
    const errBox = document.getElementById('2fa-error');

    if(!code) return;

    const formData = new FormData();
    formData.append('action', 'verify_2fa');
    formData.append('user_id', tempUserId);
    formData.append('code', code);

    fetch('api_login.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            window.location.href = 'dashboard.php';
        } else {
            errBox.textContent = data.message;
            errBox.style.display = "block";
        }
    });
}

// Zorg dat Enter ook werkt
document.getElementById('login-pass').addEventListener('keypress', function(e) { if (e.key === 'Enter') doLogin(); });
document.getElementById('login-code').addEventListener('keypress', function(e) { if (e.key === 'Enter') verify2FA(); });
</script>
</body>
</html>