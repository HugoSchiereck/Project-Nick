<?php
// onboarding.php - Volledig Publiek Aanmeldformulier
require 'config.php';

$success = false;
$error = '';

// --- AUTO-SETUP: Zorg dat alle uitgebreide kolommen in de database bestaan ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS onboarding_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        email VARCHAR(255),
        phone VARCHAR(50),
        dob DATE,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;");
} catch (PDOException $e) {}

// Voeg de overige benodigde kolommen toe als ze nog niet bestaan
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN birthplace VARCHAR(100)"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN street VARCHAR(255)"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN zip VARCHAR(20)"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN city VARCHAR(100)"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN start_date DATE"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN bsn VARCHAR(50)"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN iban VARCHAR(50)"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN iban_name VARCHAR(255)"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN doc_nr VARCHAR(100)"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN doc_expiry DATE"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN ec_name VARCHAR(255)"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE onboarding_requests ADD COLUMN ec_phone VARCHAR(50)"); } catch (PDOException $e) {}


// --- FORMULIER VERWERKEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first       = trim($_POST['first_name'] ?? '');
    $last        = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $dob         = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $birthplace  = trim($_POST['birthplace'] ?? '');
    $street      = trim($_POST['street'] ?? '');
    $zip         = trim($_POST['zip'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $start_date  = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $bsn         = trim($_POST['bsn'] ?? '');
    $iban        = trim($_POST['iban'] ?? '');
    $iban_name   = trim($_POST['iban_name'] ?? '');
    $doc_nr      = trim($_POST['doc_nr'] ?? '');
    $doc_expiry  = !empty($_POST['doc_expiry']) ? $_POST['doc_expiry'] : null;
    $ec_name     = trim($_POST['ec_name'] ?? '');
    $ec_phone    = trim($_POST['ec_phone'] ?? '');

    if (empty($first) || empty($last)) {
        $error = "Voornaam en achternaam zijn verplichte velden.";
    } else {
        try {
            $sql = "INSERT INTO onboarding_requests 
                    (first_name, last_name, email, phone, dob, birthplace, street, zip, city, start_date, bsn, iban, iban_name, doc_nr, doc_expiry, ec_name, ec_phone) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $first, $last, $email, $phone, $dob, $birthplace, 
                $street, $zip, $city, $start_date, $bsn, $iban, 
                $iban_name, $doc_nr, $doc_expiry, $ec_name, $ec_phone
            ]);
            $success = true;
        } catch (PDOException $e) {
            $error = "Er is een technische fout opgetreden bij het opslaan van je gegevens. Probeer het later nog eens.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Onboarding — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --font:'DM Sans',sans-serif;
}
body{font-family:var(--font);background:var(--bg);color:var(--text);display:flex;align-items:center;justify-content:center;min-height:100vh;padding:40px 20px;}
.container{width:100%;max-width:640px;}
.logo-wrap{text-align:center;margin-bottom:28px;}
.logo-wrap img{height:42px;width:auto;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:36px;box-shadow:0 4px 14px rgba(0,0,0,0.02);}
.card h1{font-size:24px;font-weight:600;margin-bottom:8px;}
.card p{font-size:14.5px;color:var(--text2);margin-bottom:28px;line-height:1.5;}
.section-title{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin:24px 0 14px;padding-bottom:6px;border-bottom:1px solid var(--border);}
.field{margin-bottom:16px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:6px;}
.field input{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:14.5px;outline:none;transition:border-color 0.2s;}
.field input:focus{border-color:var(--accent);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.btn{width:100%;padding:13px;background:var(--accent);color:#FFF;border:none;border-radius:8px;font-family:var(--font);font-size:15px;font-weight:600;cursor:pointer;margin-top:20px;transition:opacity 0.2s;}
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
            <div class="alert alert-success" style="margin-bottom:0;text-align:center;padding:24px;">
                <h2 style="font-size:20px;margin-bottom:10px;">✅ Gegevens succesvol ontvangen</h2>
                <p style="margin:0;color:var(--text2);">Bedankt voor het invullen van het onboardingformulier! Je gegevens zijn veilig opgeslagen in onze database. De administratie zal je account spoedig activeren.</p>
            </div>
        <?php else: ?>
            <h1>Onboarding MST Logistics</h1>
            <p>Welkom bij het team! Vul hieronder zorgvuldig al je gegevens in om je inschrijving en indiensttreding administratief volledig af te ronden.</p>
            
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="POST">
                
                <div class="section-title" style="margin-top:0;">Persoonlijke Gegevens</div>
                <div class="form-row">
                    <div class="field"><label>Voornaam *</label><input type="text" name="first_name" required placeholder="Bijv. Jan"></div>
                    <div class="field"><label>Achternaam *</label><input type="text" name="last_name" required placeholder="Bijv. Janssen"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label>E-mailadres</label><input type="email" name="email" placeholder="jan@voorbeeld.nl"></div>
                    <div class="field"><label>Telefoonnummer</label><input type="text" name="phone" placeholder="06 12345678"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label>Geboortedatum</label><input type="date" name="dob"></div>
                    <div class="field"><label>Geboorteplaats</label><input type="text" name="birthplace" placeholder="Bijv. Utrecht"></div>
                </div>

                <div class="section-title">Adresgegevens</div>
                <div class="field"><label>Straatnaam & Huisnummer</label><input type="text" name="street" placeholder="Dorpsstraat 12A"></div>
                <div class="form-row">
                    <div class="field"><label>Postcode</label><input type="text" name="zip" placeholder="1234 AB"></div>
                    <div class="field"><label>Woonplaats</label><input type="text" name="city" placeholder="Bijv. Montfoort"></div>
                </div>

                <div class="section-title">Identificatie & Contract</div>
                <div class="form-row">
                    <div class="field"><label>BSN Nummer</label><input type="text" name="bsn" placeholder="123456789"></div>
                    <div class="field"><label>Verwachte Startdatum</label><input type="date" name="start_date"></div>
                </div>
                <div class="form-row">
                    <div class="field"><label>ID / Paspoortnummer</label><input type="text" name="doc_nr" placeholder="Bijv. NX9999999"></div>
                    <div class="field"><label>Document Geldig Tot</label><input type="date" name="doc_expiry"></div>
                </div>

                <div class="section-title">Financiële Gegevens</div>
                <div class="field"><label>IBAN Rekeningnummer</label><input type="text" name="iban" placeholder="NL99 INGB 0123 4567 89"></div>
                <div class="field"><label>Tenaamstelling rekening</label><input type="text" name="iban_name" placeholder="Bijv. J. Janssen"></div>

                <div class="section-title">In Geval van Nood (ICE)</div>
                <div class="form-row">
                    <div class="field"><label>Naam Noodcontact</label><input type="text" name="ec_name" placeholder="Bijv. Partner of Ouder"></div>
                    <div class="field"><label>Telefoonnummer Noodcontact</label><input type="text" name="ec_phone" placeholder="06 87654321"></div>
                </div>

                <button type="submit" class="btn">Gegevens Veilig Verzenden</button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>