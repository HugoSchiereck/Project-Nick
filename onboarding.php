<?php
// onboarding.php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Haal huidige gebruiker op
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'manager') {
    die("Je hebt geen toegang tot deze pagina.");
}

// --- TABEL AANMAKEN (Indien niet bestaat) ---
$pdo->exec("CREATE TABLE IF NOT EXISTS onboarding_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    start_date DATE,
    processed_by INT,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;");

// --- BERICHTEN OPVANGEN (PRG Patroon) ---
$success_msg = '';
$error_msg = '';
if (isset($_SESSION['success_msg'])) { $success_msg = $_SESSION['success_msg']; unset($_SESSION['success_msg']); }
if (isset($_SESSION['error_msg'])) { $error_msg = $_SESSION['error_msg']; unset($_SESSION['error_msg']); }

// --- FORMULIER VERWERKEN (Medewerker toevoegen via Onboarding) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_onboarding') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $function = trim($_POST['function'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $snipper = (int)($_POST['snipper'] ?? 25);
    $atv = (int)($_POST['atv'] ?? 10);
    $email = trim($_POST['email'] ?? '');
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;

    if (empty($first_name) || empty($last_name) || empty($username) || empty($password) || empty($function)) {
        $_SESSION['error_msg'] = "Vul alle verplichte velden (met sterretje) in.";
    } else {
        // Check of gebruikersnaam al bestaat
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['error_msg'] = "De gebruikersnaam '{$username}' is al in gebruik. Kies een andere.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Bereken volgend Personeelsnummer (PNR)
                $pnrStmt = $pdo->query("SELECT MAX(CAST(pnr AS UNSIGNED)) as max_pnr FROM users WHERE pnr REGEXP '^[0-9]+$'");
                $max_pnr = $pnrStmt->fetchColumn();
                $new_pnr = $max_pnr ? (int)$max_pnr + 1 : 1001;

                // 2. Medewerker opslaan
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $pdo->prepare("INSERT INTO users (pnr, first_name, last_name, email, username, password_hash, role, function_title) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([$new_pnr, $first_name, $last_name, $email, $username, $password_hash, $role, $function]);
                
                // 3. Zet het verlofsaldo klaar (optioneel, afhankelijk van of je tabel hiervoor klaar is, maar we slaan het nu niet in de DB op tenzij je die tabel hebt)
                // Let op: Verlofsaldo zit nog in localstorage in je oude setup. Om het compleet te maken loggen we dit in de historie:
                
                // 4. Onboarding historie bijwerken
                $histStmt = $pdo->prepare("INSERT INTO onboarding_history (name, email, start_date, processed_by) VALUES (?, ?, ?, ?)");
                $histStmt->execute([$first_name . ' ' . $last_name, $email, $start_date, $currentUser['id']]);

                $pdo->commit();
                $_SESSION['success_msg'] = "Medewerker {$first_name} {$last_name} (PNR: {$new_pnr}) is succesvol toegevoegd aan het portaal!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error_msg'] = "Fout bij opslaan: " . $e->getMessage();
            }
        }
    }
    header("Location: onboarding.php");
    exit;
}

// --- DATA OPHALEN ---
$history = $pdo->query("
    SELECT h.*, u.first_name, u.last_name 
    FROM onboarding_history h 
    LEFT JOIN users u ON h.processed_by = u.id 
    ORDER BY h.processed_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Onboarding — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --radius:10px;--font:'DM Sans',sans-serif;--mono:'DM Mono',monospace;
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
.page-header{margin-bottom:24px;}
.page-header h1{font-size:22px;font-weight:600;}
.page-header p{color:var(--text2);font-size:13px;margin-top:3px;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:18px;overflow:hidden;}
.card-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--surface2);}
.card-header h3{font-size:16px;font-weight:600;}
.card-body-pad{padding:18px 20px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field input, .field select, .field textarea{width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:var(--radius);font-family:var(--font);font-size:14px;outline:none;}
.field input:focus, .field textarea:focus{border-color:var(--accent);}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 17px;border-radius:8px;border:none;font-family:var(--font);font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;}
.btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{opacity:.88;}
.btn-secondary{background:var(--surface2);color:var(--text);border:1px solid var(--border);} .btn-secondary:hover{background:var(--border);}
.btn-sm{padding:6px 14px;font-size:13px;}
table{width:100%;border-collapse:collapse;}
th{font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);padding:10px 18px;text-align:left;background:var(--surface2);}
td{padding:11px 18px;font-size:13.5px;border-top:1px solid var(--border);vertical-align:middle;}
tr:hover td{background:#FAFAF8;}
.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-success{background:var(--green-light);color:var(--green);}
.alert-danger{background:var(--danger-light);color:var(--danger);}
</style>
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main">
    <div class="page-header">
      <h1>Onboarding</h1>
      <p>Verwerk codes van nieuwe medewerkers uit het onboardingformulier</p>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div class="card">
      <div class="card-header"><h3>Code importeren</h3></div>
      <div class="card-body-pad">
        <p style="font-size:13px;color:var(--text2);margin-bottom:14px;">Plak hieronder de <code>MST-...</code> code die de nieuwe medewerker jou heeft verstuurd na het invullen van het onboardingformulier.</p>
        <div class="field">
          <textarea id="ob-code-input" rows="4" placeholder="MST-eyJ..." style="font-family:var(--mono);font-size:12px;resize:vertical"></textarea>
        </div>
        <div id="ob-import-error" class="alert alert-danger" style="display:none;"></div>
        <button class="btn btn-primary" onclick="parseCode()">Code verwerken →</button>
      </div>
    </div>

    <div id="ob-preview-card" style="display:none;">
      <div class="card">
        <div class="card-header">
          <div>
              <h3 id="ob-preview-name">—</h3>
              <div class="card-header-sub" id="ob-preview-sub">—</div>
          </div>
        </div>
        <div class="card-body-pad">
          
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0" id="ob-preview-grid"></div>
          
          <hr style="border:none;border-top:1px solid var(--border);margin:16px 0">
          <p style="font-size:13px;color:var(--text2);margin-bottom:14px;font-weight:500;">Gegevens voor inlogportaal en administratie:</p>
          
          <form method="POST" action="onboarding.php">
              <input type="hidden" name="action" value="process_onboarding">
              
              <input type="hidden" name="first_name" id="ob-first-name">
              <input type="hidden" name="last_name" id="ob-last-name">
              <input type="hidden" name="email" id="ob-email">
              <input type="hidden" name="start_date" id="ob-start-date">

              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
                <div class="field"><label>Functie *</label><input type="text" name="function" id="ob-func" placeholder="Chauffeur" required></div>
                <div class="field"><label>Gebruikersnaam *</label><input type="text" name="username" id="ob-username" placeholder="jan.janssen" required></div>
                <div class="field"><label>Tijdelijk Wachtwoord *</label><input type="password" name="password" id="ob-pass" placeholder="••••••••" required></div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px">
                <div class="field">
                    <label>Rol</label>
                    <select name="role">
                        <option value="employee">Medewerker (Standaard)</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
                <div class="field"><label>Snipperdagen start</label><input type="number" name="snipper" value="25" min="0"></div>
                <div class="field"><label>ATV-dagen start</label><input type="number" name="atv" value="10" min="0"></div>
              </div>

              <div style="display:flex;gap:9px">
                <button type="submit" class="btn btn-primary">✓ Medewerker toevoegen aan database</button>
                <button type="button" class="btn btn-secondary" onclick="cancelPreview()">Annuleren</button>
              </div>
          </form>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Verwerkte onboardingen</h3></div>
      <table>
        <thead><tr><th>Naam</th><th>E-mail</th><th>Startdatum</th><th>Verwerkt door</th><th>Datum</th></tr></thead>
        <tbody>
          <?php foreach($history as $h): ?>
          <tr>
            <td><strong><?= htmlspecialchars($h['name']) ?></strong></td>
            <td style="color:var(--text2);font-size:12.5px"><?= htmlspecialchars($h['email'] ?: '—') ?></td>
            <td><?= $h['start_date'] ? date('d-m-Y', strtotime($h['start_date'])) : '—' ?></td>
            <td style="font-size:12.5px;color:var(--text2)"><?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?></td>
            <td style="font-size:12px;color:var(--text3)"><?= date('d-m-Y H:i', strtotime($h['processed_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(count($history) === 0): ?>
            <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text3)">Nog geen onboardingen verwerkt in de database.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

<script>
function parseCode() {
  const raw = document.getElementById('ob-code-input').value.trim();
  const errEl = document.getElementById('ob-import-error');
  errEl.style.display = 'none';
  document.getElementById('ob-preview-card').style.display = 'none';

  if (!raw) { errEl.textContent = 'Plak eerst een code in het veld.'; errEl.style.display = 'block'; return; }
  if (!raw.startsWith('MST-')) { errEl.textContent = 'Ongeldige code — een geldige code begint met "MST-".'; errEl.style.display = 'block'; return; }

  let data;
  try {
    const b64 = raw.slice(4); // Verwijder de "MST-" tag
    // Decodeer the base64 string
    data = JSON.parse(decodeURIComponent(escape(atob(b64))));
  } catch(e) {
    errEl.textContent = 'Code kon niet worden gelezen. Is de code volledig gekopieerd?';
    errEl.style.display = 'block';
    return;
  }

  if (!data.firstName || !data.lastName) {
    errEl.textContent = 'Code mist verplichte naamsgegevens.';
    errEl.style.display = 'block';
    return;
  }

  // Vul de header van de preview
  document.getElementById('ob-preview-name').textContent = data.firstName + ' ' + data.lastName;
  const submittedDate = data.submittedAt ? new Date(data.submittedAt).toLocaleDateString('nl-NL') : '—';
  document.getElementById('ob-preview-sub').textContent = 'Ingediend op ' + submittedDate + ' · ' + (data.email || '—');

  // Vul het visuele grid met de gegevens
  const fields = [
    ['Voornaam', data.firstName], ['Achternaam', data.lastName],
    ['Geboortedatum', data.birthdate || '—'], ['Geboorteplaats', data.birthplace || '—'],
    ['Telefoon', data.phone || '—'], ['E-mailadres', data.email || '—'],
    ['Adres', (data.street||'') + ' ' + (data.zip||'') + ' ' + (data.city||'')], ['Startdatum', data.startDate || '—'],
    ['IBAN', data.iban || '—'], ['Tenaamstelling', data.ibanName || '—'],
    ['BSN', data.bsn ? '●●●●●' + data.bsn.slice(-4) : '—'], ['ID/Paspoort', data.docNr || '—'],
    ['Geldig t/m', data.docExpiry || '—'], ['Noodcontact', data.ecName || '—'],
    ['Tel. noodcontact', data.ecPhone || '—'], ['', '']
  ];

  document.getElementById('ob-preview-grid').innerHTML = fields.map(([k,v]) =>
    k ? `<div style="padding:7px 0;border-bottom:1px solid var(--border);font-size:12.5px">
      <span style="color:var(--text3);font-size:11px;display:block;margin-bottom:1px">${k}</span>
      <span style="color:var(--text);font-weight:500">${v}</span>
    </div>` : '<div></div>'
  ).join('');

  // Vul de formuliervelden automatisch (die naar de database gaan)
  document.getElementById('ob-first-name').value = data.firstName;
  document.getElementById('ob-last-name').value = data.lastName;
  document.getElementById('ob-email').value = data.email || '';
  document.getElementById('ob-start-date').value = data.startDate || '';

  // Automatische gebruikersnaam suggestie (voornaam.achternaam)
  const suggest = (data.firstName.toLowerCase().replace(/[^a-z]/g,'') + '.' + data.lastName.toLowerCase().replace(/[^a-z]/g,'')).slice(0,30);
  document.getElementById('ob-username').value = suggest;
  document.getElementById('ob-func').value = '';
  document.getElementById('ob-pass').value = '';
  
  // Toon scherm en scroll er naartoe
  document.getElementById('ob-preview-card').style.display = 'block';
  document.getElementById('ob-preview-card').scrollIntoView({behavior:'smooth', block:'start'});
}

function cancelPreview() {
  document.getElementById('ob-preview-card').style.display = 'none';
  document.getElementById('ob-code-input').value = '';
}
</script>
</body>
</html>