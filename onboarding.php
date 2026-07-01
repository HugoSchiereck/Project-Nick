<?php
// onboarding.php - Publiek aanmeldformulier
require 'config.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $bsn = trim($_POST['bsn'] ?? '');
    $iban = trim($_POST['iban'] ?? '');

    if (empty($first) || empty($last)) {
        $error = "Voornaam en achternaam zijn verplicht.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO onboarding_requests (first_name, last_name, email, phone, dob, bsn, iban) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$first, $last, $email, $phone, $dob, $bsn, $iban]);
            $success = true;
        } catch (PDOException $e) {
            $error = "Er is een technische fout opgetreden bij het verzenden. Probeer het later opnieuw.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aanmelden — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--border:#DDD9D0;
  --accent:#D4351C;--text:#1A1A18;--text2:#5A5A54;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --font:'DM Sans',sans-serif;
}
body{font-family:var(--font);background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
.container{width:100%;max-width:540px;}
.logo-wrap{text-align:center;margin-bottom:24px;}
.logo-wrap img{height:40px;width:auto;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:32px;box-shadow:0 4px 12px rgba(0,0,0,0.03);}
.card h1{font-size:22px;font-weight:600;margin-bottom:6px;}
.card p{font-size:14.5px;color:var(--text2);margin-bottom:24px;line-height:1.5;}
.field{margin-bottom:16px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:6px;}
.field input{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:14.5px;outline:none;transition:border-color 0.2s;}
.field input:focus{border-color:var(--accent);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.btn{width:100%;padding:12px;background:var(--accent);color:#FFF;border:none;border-radius:8px;font-family:var(--font);font-size:15px;font-weight:600;cursor:pointer;margin-top:10px;transition:opacity 0.2s;}
.btn:hover{opacity:0.9;}
.alert{padding:14px 18px;border-radius:8px;font-size:14.5px;margin-bottom:24px;line-height:1.5;}
.alert-success{background:var(--green-light);color:var(--green);border:1px solid #B0D9C0;}
.alert-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;}
</style>
</head>
<body>

<div class="container">
    <div class="logo-wrap">
        <img src="https://mstlogistics.nl/wp-content/uploads/2024/09/Logo-MST-Logistics.svg" alt="MST Logistics">
    </div>

    <div class="card">
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom:0;text-align:center;">
                <h2 style="font-size:18px;margin-bottom:8px;">✅ Gegevens succesvol verzonden</h2>
                <p style="margin:0;">Bedankt voor je aanmelding! Jouw gegevens zijn veilig doorgestuurd naar de administratie van MST Logistics. Je kunt deze pagina nu sluiten.</p>
            </div>
        <?php else: ?>
            <h1>Welkom bij MST Logistics</h1>
            <p>Vul hieronder eenmalig je gegevens in om je profiel compleet te maken. Deze gegevens worden veilig opgeslagen voor onze administratie.</p>
            
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST">
                <div class="form-row">
                    <div class="field"><label>Voornaam *</label><input type="text" name="first_name" required></div>
                    <div class="field"><label>Achternaam *</label><input type="text" name="last_name" required></div>
                </div>
                <div class="form-row">
                    <div class="field"><label>E-mailadres</label><input type="email" name="email"></div>
                    <div class="field"><label>Telefoonnummer</label><input type="text" name="phone" placeholder="06..."></div>
                </div>
                <div class="form-row">
                    <div class="field"><label>Geboortedatum</label><input type="date" name="dob"></div>
                    <div class="field"><label>BSN Nummer</label><input type="text" name="bsn" placeholder="123456789"></div>
                </div>
                <div class="field">
                    <label>IBAN Rekeningnummer</label>
                    <input type="text" name="iban" placeholder="NL99 INGB 0123 4567 89">
                </div>
                <button type="submit" class="btn">Gegevens veilig verzenden</button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>