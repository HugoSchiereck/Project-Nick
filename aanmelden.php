<?php
// aanmelden.php
require 'config.php';

$success = false;
$new_user = '';
$new_pass = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Haal de belangrijkste gegevens uit het formulier
    $first = trim($_POST['firstname'] ?? '');
    $last = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $start_date = $_POST['startdate'] ?? null;
    
    // (Optioneel: je kunt de rest van de POST data zoals BSN en IBAN hier ook opvangen 
    // en bijv. naar jezelf mailen of in een beveiligde tabel zetten)

    if (empty($first) || empty($last)) {
        $error = "Voornaam en achternaam zijn verplicht.";
    } else {
        try {
            $pdo->beginTransaction();

            // 2. Genereer een unieke gebruikersnaam (bijv. jan.janssen)
            $base_user = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $last));
            $username = $base_user;
            $counter = 1;
            while (true) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->rowCount() == 0) break; // Uniek!
                $username = $base_user . $counter;
                $counter++;
            }

            // 3. Genereer een standaard wachtwoord
            $password = 'Welkom' . date('Y') . '!';
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // 4. Bereken het volgende PNR nummer
            $pnrStmt = $pdo->query("SELECT MAX(CAST(pnr AS UNSIGNED)) FROM users WHERE pnr REGEXP '^[0-9]+$'");
            $max_pnr = $pnrStmt->fetchColumn();
            $new_pnr = $max_pnr ? (int)$max_pnr + 1 : 1001;

            // 5. Maak de gebruiker aan in de database
            $insertStmt = $pdo->prepare("INSERT INTO users (pnr, first_name, last_name, email, username, password_hash, role, function_title) VALUES (?, ?, ?, ?, ?, ?, 'employee', 'Chauffeur')");
            $insertStmt->execute([$new_pnr, $first, $last, $email, $username, $hash]);

            // 6. Log dit in de onboarding historie voor de beheerder
            $hist = $pdo->prepare("INSERT INTO onboarding_history (name, email, start_date) VALUES (?, ?, ?)");
            $hist->execute([$first . ' ' . $last, $email, $start_date]);

            $pdo->commit();
            $success = true;
            $new_user = $username;
            $new_pass = $password;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Er is een fout opgetreden bij het aanmaken van het account: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MST Logistics — Onboarding nieuwe medewerker</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* Exact de styling uit jouw HTML bestand */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --radius:10px;--font:'DM Sans',sans-serif;--mono:'DM Mono',monospace;
}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;font-size:15px;padding:0;}
.page-wrap{max-width:660px;margin:0 auto;padding:40px 20px 60px;}
.form-header{display:flex;align-items:center;gap:14px;margin-bottom:8px;}
.form-header img{height:40px;width:auto;object-fit:contain;}
.logo-fallback{width:38px;height:38px;background:var(--accent);border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:17px;flex-shrink:0;}
.form-header-text h1{font-size:20px;font-weight:600;letter-spacing:-.4px;}
.form-header-text p{color:var(--text2);font-size:13px;margin-top:2px;}
.form-divider{border:none;border-top:1px solid var(--border);margin:20px 0 28px;}
.section-title{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:14px;padding-bottom:8px;border-bottom:1px solid var(--border);}
.section-gap{margin-bottom:28px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field input,.field select,.field textarea{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius);font-family:var(--font);font-size:14px;background:var(--surface);color:var(--text);outline:none;transition:border-color .15s,box-shadow .15s;}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(212,53,28,.1);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.field .hint{font-size:11px;color:var(--text3);margin-top:4px;}
.required-star{color:var(--accent);margin-left:2px;}
.privacy-box{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-bottom:20px;font-size:12.5px;color:var(--text2);line-height:1.7;}
.privacy-box strong{color:var(--text);}
.btn-submit{width:100%;padding:13px;background:var(--accent);color:#fff;border:none;border-radius:var(--radius);font-family:var(--font);font-size:15px;font-weight:600;cursor:pointer;letter-spacing:-.2px;transition:opacity .15s,transform .1s;}
.btn-submit:hover{opacity:.88;}
.btn-submit:active{transform:scale(.99);}
.form-error-box{background:#FAEAEA;color:#C0392B;border:1px solid #F5C6C3;border-radius:var(--radius);padding:12px 16px;font-size:13px;margin-bottom:18px;line-height:1.6;}
.success-screen{text-align:center;padding:40px 20px;}
.success-icon{width:60px;height:60px;background:var(--green-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:26px;color:var(--green);}
.success-screen h2{font-size:20px;font-weight:600;margin-bottom:8px;letter-spacing:-.4px;}
.success-screen p{color:var(--text2);font-size:14px;line-height:1.7;margin-bottom:20px;}
.code-box{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px 20px;font-family:var(--mono);font-size:13px;color:var(--text);line-height:1.6;text-align:center;}
.code-box-label{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:6px;}
.form-footer{margin-top:24px;font-size:11.5px;color:var(--text3);text-align:center;line-height:1.7;}
</style>
</head>
<body>

<div class="page-wrap">

  <div class="form-header">
    <img src="https://mstlogistics.nl/assets/images/logo/logo.svg" alt="MST Logistics" onerror="this.style.display='none';document.getElementById('logo-fb').style.display='flex'">
    <div class="logo-fallback" id="logo-fb" style="display:none">M</div>
    <div class="form-header-text">
      <h1>Onboarding MST Logistics</h1>
      <p>Vul onderstaand formulier in om je gegevens door te sturen</p>
    </div>
  </div>
  <hr class="form-divider">

  <?php if($error): ?>
      <div class="form-error-box" style="display:block"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if($success): ?>
      <div class="success-screen">
        <div class="success-icon">✓</div>
        <h2>Welkom bij MST Logistics!</h2>
        <p>Je account voor het personeelsportaal is succesvol aangemaakt. Bewaar onderstaande inloggegevens goed.</p>
        
        <div class="code-box-label">Jouw inloggegevens</div>
        <div class="code-box" style="font-size:15px; margin-bottom:20px;">
            Gebruikersnaam: <strong style="color:var(--accent)"><?= htmlspecialchars($new_user) ?></strong><br>
            Wachtwoord: <strong style="color:var(--accent)"><?= htmlspecialchars($new_pass) ?></strong>
        </div>

        <a href="index.php" class="btn-submit" style="display:inline-block; text-decoration:none; text-align:center;">Direct inloggen op het portaal →</a>
      </div>

  <?php else: ?>
      <form method="POST" action="aanmelden.php">

        <div class="section-gap">
          <div class="section-title">Persoonsgegevens</div>
          <div class="form-row">
            <div class="field"><label>Voornaam<span class="required-star">*</span></label><input type="text" name="firstname" required placeholder="Jan"></div>
            <div class="field"><label>Achternaam<span class="required-star">*</span></label><input type="text" name="lastname" required placeholder="Janssen"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Geboortedatum</label><input type="date" name="birthdate"></div>
            <div class="field"><label>Geboorteplaats</label><input type="text" name="birthplace" placeholder="Amsterdam"></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Telefoon (mobiel)</label><input type="tel" name="phone" placeholder="06 12 34 56 78"></div>
            <div class="field"><label>E-mailadres</label><input type="email" name="email" placeholder="jan@example.nl"></div>
          </div>
        </div>

        <div class="section-gap">
          <div class="section-title">Adresgegevens</div>
          <div class="field"><label>Straat + huisnummer</label><input type="text" name="street" placeholder="Voorbeeldstraat 12"></div>
          <div class="form-row">
            <div class="field"><label>Postcode</label><input type="text" name="zip" placeholder="1234 AB"></div>
            <div class="field"><label>Woonplaats</label><input type="text" name="city" placeholder="Amsterdam"></div>
          </div>
        </div>

        <div class="section-gap">
          <div class="section-title">Bankgegevens</div>
          <div class="field"><label>Bankrekeningnummer (IBAN)</label><input type="text" name="iban" placeholder="NL91 ABNA 0417 1643 00" style="font-family:var(--mono)"></div>
          <div class="field"><label>Tenaamstelling bankrekening</label><input type="text" name="ibanname" placeholder="J. Janssen"></div>
        </div>

        <div class="section-gap">
          <div class="section-title">Identiteitsgegevens</div>
          <div class="field"><label>BSN-nummer</label><input type="text" name="bsn" placeholder="123456789" style="font-family:var(--mono)" maxlength="9"></div>
          <div class="form-row">
            <div class="field"><label>Paspoort- of ID-kaartnummer</label><input type="text" name="docnr" placeholder="XM1234567" style="font-family:var(--mono)"></div>
            <div class="field"><label>Geldig t/m</label><input type="date" name="docexpiry"></div>
          </div>
        </div>

        <div class="section-gap">
          <div class="section-title">In geval van nood</div>
          <div class="form-row">
            <div class="field"><label>Naam contactpersoon</label><input type="text" name="ecname" placeholder="Maria Janssen"></div>
            <div class="field"><label>Telefoonnummer</label><input type="tel" name="ecphone" placeholder="06 98 76 54 32"></div>
          </div>
        </div>

        <div class="section-gap">
          <div class="section-title">Indiensttreding</div>
          <div class="field"><label>Datum indiensttreding</label><input type="date" name="startdate"></div>
        </div>

        <div class="privacy-box">
          <strong>Privacy:</strong> Je gegevens worden uitsluitend gebruikt voor de administratie van MST Logistics en worden niet gedeeld met derden. Door te verzenden ga je akkoord met de verwerking van je gegevens voor HR-doeleinden.
        </div>

        <button type="submit" class="btn-submit">Gegevens versturen & Account aanmaken →</button>
      </form>
  <?php endif; ?>

  <div class="form-footer">MST Logistics · Personeelsportaal · Alle velden gemarkeerd met <span style="color:var(--accent)">*</span> zijn verplicht</div>

</div>

</body>
</html>