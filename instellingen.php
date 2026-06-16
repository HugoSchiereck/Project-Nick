<?php
// instellingen.php
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

// --- AUTO-SETUP TABELLEN ---
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    key_name VARCHAR(50) PRIMARY KEY,
    key_value VARCHAR(255)
) ENGINE=InnoDB;");

$pdo->exec("CREATE TABLE IF NOT EXISTS leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;");

$pdo->exec("CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    subject VARCHAR(255),
    body TEXT
) ENGINE=InnoDB;");

// Voeg wat standaard data toe als de tabellen leeg zijn
$setupCheck = $pdo->query("SELECT COUNT(*) FROM leave_types")->fetchColumn();
if ($setupCheck == 0) {
    $pdo->exec("INSERT INTO leave_types (name) VALUES ('Jaarlijks verlof'), ('Vakantie'), ('Zorgverlof'), ('Bijzonder verlof')");
    $pdo->exec("INSERT INTO settings (key_name, key_value) VALUES ('company_name', 'MST Logistics'), ('admin_email', 'planning@mstlogistics.nl')");
    $pdo->exec("INSERT INTO email_templates (name, subject, body) VALUES 
        ('Verlof goedgekeurd', '[{bedrijf}] Verlofaanvraag goedgekeurd', 'Beste {voornaam},\n\nJe verlofaanvraag is goedgekeurd.\nType: {verlof_type}\nPeriode: {verlof_van} t/m {verlof_tot}\n\nMet vriendelijke groet,\n{bedrijf}'),
        ('Code 95 herinnering', '[{bedrijf}] Code 95 verloopt binnenkort', 'Beste {voornaam},\n\nJe Code 95 verloopt op {c95_vervaldatum}.\nBehaald: {c95_uren}/35 uur.\n\nZorg tijdig voor bijscholing.\n\nMet vriendelijke groet,\n{bedrijf}')");
}

// --- BERICHTEN OPVANGEN (PRG Patroon) ---
$success_msg = '';
$error_msg = '';
if (isset($_SESSION['success_msg'])) { $success_msg = $_SESSION['success_msg']; unset($_SESSION['success_msg']); }
if (isset($_SESSION['error_msg'])) { $error_msg = $_SESSION['error_msg']; unset($_SESSION['error_msg']); }

// --- FORMULIEREN VERWERKEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Algemene instellingen opslaan
    if ($_POST['action'] === 'save_settings') {
        $company = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, key_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)");
        $stmt->execute(['company_name', $company]);
        $stmt->execute(['admin_email', $email]);
        
        $_SESSION['success_msg'] = "Instellingen succesvol opgeslagen!";
        header("Location: instellingen.php"); exit;
    }

    // 2. Verloftype toevoegen
    if ($_POST['action'] === 'add_leave_type') {
        $name = trim($_POST['leave_type_name'] ?? '');
        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO leave_types (name) VALUES (?)");
            $stmt->execute([$name]);
            $_SESSION['success_msg'] = "Verloftype '{$name}' toegevoegd!";
        }
        header("Location: instellingen.php"); exit;
    }

    // 3. Verloftype verwijderen
    if ($_POST['action'] === 'delete_leave_type') {
        $id = $_POST['leave_type_id'];
        $stmt = $pdo->prepare("DELETE FROM leave_types WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_msg'] = "Verloftype verwijderd.";
        header("Location: instellingen.php"); exit;
    }

    // 4. E-mailsjabloon opslaan (nieuw of update)
    if ($_POST['action'] === 'save_template') {
        $id = $_POST['template_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if (empty($id)) {
            $stmt = $pdo->prepare("INSERT INTO email_templates (name, subject, body) VALUES (?, ?, ?)");
            $stmt->execute([$name, $subject, $body]);
            $_SESSION['success_msg'] = "Sjabloon toegevoegd!";
        } else {
            $stmt = $pdo->prepare("UPDATE email_templates SET name = ?, subject = ?, body = ? WHERE id = ?");
            $stmt->execute([$name, $subject, $body, $id]);
            $_SESSION['success_msg'] = "Sjabloon opgeslagen!";
        }
        header("Location: instellingen.php"); exit;
    }

    // 5. E-mailsjabloon verwijderen
    if ($_POST['action'] === 'delete_template') {
        $id = $_POST['template_id'];
        $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_msg'] = "Sjabloon verwijderd.";
        header("Location: instellingen.php"); exit;
    }
}

// --- DATA OPHALEN ---
$settingsArr = $pdo->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$company_name = $settingsArr['company_name'] ?? 'MST Logistics';
$admin_email = $settingsArr['admin_email'] ?? '';

$leave_types = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll();
$templates = $pdo->query("SELECT * FROM email_templates ORDER BY id ASC")->fetchAll();
$employees = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'employee' ORDER BY first_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instellingen — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* CSS Styling */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --blue:#1A5EA8;--blue-light:#EBF3FB;
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
.page-header{margin-bottom:24px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;}
.page-header-text h1{font-size:22px;font-weight:600;}
.page-header-text p{color:var(--text2);font-size:13px;margin-top:3px;}

.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:18px;}
.card-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--surface2);}
.card-header h3{font-size:14px;font-weight:600;}
.card-header-sub{font-size:12px;color:var(--text3);margin-top:1px;font-weight:400;}
.card-body-pad{padding:18px 20px;}

.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field input, .field select, .field textarea{width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:14px;outline:none;}
.field input:focus, .field select:focus, .field textarea:focus{border-color:var(--accent);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 17px;border-radius:8px;border:none;font-family:var(--font);font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;}
.btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{opacity:.88;}
.btn-secondary{background:var(--surface2);color:var(--text);border:1px solid var(--border);} .btn-secondary:hover{background:var(--border);}
.btn-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;} .btn-danger:hover{background:#f5c6c3;}
.btn-email{background:var(--blue-light);color:var(--blue);border:1px solid #BDD6EF;}.btn-email:hover{background:#d6e9f7;}
.btn-sm{padding:5px 11px;font-size:12px;}
.tag{display:inline-block;padding:3px 9px;border-radius:5px;font-size:12px;background:var(--surface2);color:var(--text2);border:1px solid var(--border);}

.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-info{background:#EBF3FB;color:var(--blue);}
.alert-success{background:var(--green-light);color:var(--green);}

/* Email Templates */
.template-card{border:1px solid var(--border);border-radius:9px;margin-bottom:12px;overflow:hidden;}
.template-card-header{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--surface2);gap:10px;}
.template-card-header input[type=text]{flex:1;border:none;background:transparent;font-family:var(--font);font-size:13.5px;font-weight:600;color:var(--text);outline:none;}
.template-card-body{padding:12px 14px;}
.var-chips{display:flex;flex-wrap:wrap;gap:5px;margin-top:7px;}
.var-chip{display:inline-block;padding:3px 8px;background:var(--blue-light);color:var(--blue);border:1px solid #BDD6EF;border-radius:5px;font-size:11px;font-family:var(--mono);cursor:pointer;transition:background .1s;}
.var-chip:hover{background:#d6e9f7;}

/* Modals */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:100;opacity:0;pointer-events:none;transition:opacity .2s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border-radius:14px;width:100%;max-width:540px;max-height:92vh;overflow-y:auto;padding:26px 26px 22px;transform:translateY(12px);transition:transform .2s;}
.modal-overlay.open .modal{transform:translateY(0);}
.modal h3{font-size:17px;font-weight:600;margin-bottom:18px;}
.modal-actions{display:flex;gap:9px;justify-content:flex-end;margin-top:20px;}
</style>
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main">
    <div class="page-header">
      <div class="page-header-text">
        <h1>Instellingen</h1>
        <p>E-mail, verloftypes, sjablonen en notificaties</p>
      </div>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-danger" style="background:var(--danger-light);color:var(--danger);"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px">
      
      <div class="card" style="margin:0">
        <div class="card-header"><h3>E-mailinstellingen & Bedrijf</h3></div>
        <div class="card-body-pad">
          <form method="POST" action="instellingen.php">
            <input type="hidden" name="action" value="save_settings">
            <div class="field"><label>Bedrijfsnaam in e-mails</label><input type="text" name="company_name" value="<?= htmlspecialchars($company_name) ?>" required></div>
            <div class="field"><label>Beheerder e-mailadres (CC optioneel)</label><input type="email" name="admin_email" value="<?= htmlspecialchars($admin_email) ?>" placeholder="planning@mstlogistics.nl"></div>
            <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
          </form>
        </div>
      </div>

      <div class="card" style="margin:0">
        <div class="card-header">
            <h3>Verloftypes beheren</h3>
        </div>
        <div class="card-body-pad">
          <form method="POST" action="instellingen.php" style="display:flex;gap:8px;margin-bottom:16px">
             <input type="hidden" name="action" value="add_leave_type">
             <input type="text" name="leave_type_name" placeholder="Nieuw type..." required style="flex:1;padding:8px 12px;border:1px solid var(--border);border-radius:6px;font-size:13px">
             <button type="submit" class="btn btn-primary btn-sm">+ Toevoegen</button>
          </form>

          <?php foreach($leave_types as $lt): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
              <span class="tag" style="flex:1"><?= htmlspecialchars($lt['name']) ?></span>
              <form method="POST" action="instellingen.php" style="margin:0" onsubmit="return confirm('Verloftype verwijderen?');">
                  <input type="hidden" name="action" value="delete_leave_type">
                  <input type="hidden" name="leave_type_id" value="<?= $lt['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">✕</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div>
            <h3>Standaard e-mailteksten</h3>
            <div class="card-header-sub">Gebruik variabelen die automatisch worden ingevuld. Klik op een variabele om in te voegen.</div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="openTemplateModal()">+ Nieuw sjabloon</button>
      </div>
      <div class="card-body-pad">
        <div class="alert alert-info" style="margin-bottom:18px">
          <strong>Beschikbare variabelen:</strong>
          <div class="var-chips">
              <span class="var-chip" onclick="copyVar('{voornaam}')">{voornaam}</span>
              <span class="var-chip" onclick="copyVar('{bedrijf}')">{bedrijf}</span>
              <span class="var-chip" onclick="copyVar('{verlof_type}')">{verlof_type}</span>
              <span class="var-chip" onclick="copyVar('{verlof_van}')">{verlof_van}</span>
              <span class="var-chip" onclick="copyVar('{verlof_tot}')">{verlof_tot}</span>
              <span class="var-chip" onclick="copyVar('{c95_vervaldatum}')">{c95_vervaldatum}</span>
          </div>
          <div style="font-size:11px;margin-top:6px;opacity:0.8">Klik op een variabele om hem te kopiëren.</div>
        </div>

        <?php foreach($templates as $t): ?>
          <form method="POST" action="instellingen.php" class="template-card">
            <input type="hidden" name="action" value="save_template">
            <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
            
            <div class="template-card-header">
              <input type="text" name="name" value="<?= htmlspecialchars($t['name']) ?>" required placeholder="Naam sjabloon">
              <div style="display:flex;gap:6px">
                  <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
                  <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('del-form-<?= $t['id'] ?>').submit()">✕</button>
              </div>
            </div>
            <div class="template-card-body">
              <div class="field" style="margin-bottom:8px">
                  <label style="font-size:11px">Onderwerp</label>
                  <input type="text" name="subject" value="<?= htmlspecialchars($t['subject']) ?>" required>
              </div>
              <div class="field" style="margin-bottom:0">
                  <label style="font-size:11px">Inhoud</label>
                  <textarea name="body" rows="5" required style="font-family:var(--mono);font-size:12.5px"><?= htmlspecialchars($t['body']) ?></textarea>
              </div>
            </div>
          </form>
          <form method="POST" action="instellingen.php" id="del-form-<?= $t['id'] ?>" style="display:none" onsubmit="return confirm('Sjabloon verwijderen?');">
              <input type="hidden" name="action" value="delete_template">
              <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
          </form>
        <?php endforeach; ?>

      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>Direct mailen via Outlook/Gmail</h3></div>
      <div class="card-body-pad">
        <div class="form-row" style="margin-bottom:12px">
          <div class="field" style="margin:0">
              <label>Ontvanger (Medewerker)</label>
              <select id="manual-recipient" onchange="autoFillEmail()">
                  <option value="">— Selecteer medewerker —</option>
                  <?php foreach($employees as $emp): ?>
                      <option value="<?= htmlspecialchars(json_encode(['voornaam' => $emp['first_name'], 'email' => $emp['email']])) ?>">
                          <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>
          <div class="field" style="margin:0">
              <label>Kies een sjabloon</label>
              <select id="manual-template" onchange="autoFillEmail()">
                  <option value="">— Vrije tekst (geen sjabloon) —</option>
                  <?php foreach($templates as $t): ?>
                      <option value="<?= htmlspecialchars(json_encode(['subject' => $t['subject'], 'body' => $t['body']])) ?>">
                          <?= htmlspecialchars($t['name']) ?>
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>
        </div>
        <div class="field"><label>Onderwerp</label><input type="text" id="manual-subject" placeholder="Onderwerp..."></div>
        <div class="field"><label>Bericht</label><textarea id="manual-body" rows="5" placeholder="Typ hier je bericht..." style="font-family:var(--mono)"></textarea></div>
        <button class="btn btn-email btn-sm" onclick="openMailClient()">✉ Openen in e-mailprogramma</button>
      </div>
    </div>

  </main>

  <div class="modal-overlay" id="modal-template">
    <div class="modal">
      <h3>Nieuw sjabloon aanmaken</h3>
      <form method="POST" action="instellingen.php">
          <input type="hidden" name="action" value="save_template">
          <div class="field"><label>Naam sjabloon</label><input type="text" name="name" required placeholder="Bijv. Welkomstmail"></div>
          <div class="field"><label>Onderwerp</label><input type="text" name="subject" required value="[{bedrijf}] "></div>
          <div class="field">
              <label>Inhoud</label>
              <textarea name="body" rows="6" required style="font-family:var(--mono)">Beste {voornaam},&#13;&#10;&#13;&#10;&#13;&#10;Met vriendelijke groet,&#13;&#10;{bedrijf}</textarea>
          </div>
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-template')">Annuleren</button>
            <button type="submit" class="btn btn-primary">Toevoegen</button>
          </div>
      </form>
    </div>
  </div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openTemplateModal() { openModal('modal-template'); }

function copyVar(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert("Gekopieerd: " + text + "\nJe kunt dit nu plakken in een tekstvak.");
    });
}

// Slimme functie voor de handmatige mail generator
const companyName = "<?= addslashes($company_name) ?>";

function autoFillEmail() {
    const recVal = document.getElementById('manual-recipient').value;
    const tplVal = document.getElementById('manual-template').value;
    
    let voornaam = "Medewerker";
    if (recVal) {
        const rec = JSON.parse(recVal);
        voornaam = rec.voornaam;
    }

    if (tplVal) {
        const tpl = JSON.parse(tplVal);
        let subject = tpl.subject.replace('{bedrijf}', companyName).replace('{voornaam}', voornaam);
        let body = tpl.body.replace('{bedrijf}', companyName).replace('{voornaam}', voornaam);
        
        document.getElementById('manual-subject').value = subject;
        document.getElementById('manual-body').value = body;
    }
}

function openMailClient() {
    const recVal = document.getElementById('manual-recipient').value;
    if (!recVal) {
        alert("Selecteer eerst een ontvanger.");
        return;
    }
    const rec = JSON.parse(recVal);
    if (!rec.email) {
        alert("Deze medewerker heeft geen e-mailadres ingesteld.");
        return;
    }

    const subject = encodeURIComponent(document.getElementById('manual-subject').value);
    const body = encodeURIComponent(document.getElementById('manual-body').value);
    
    window.location.href = `mailto:${rec.email}?subject=${subject}&body=${body}`;
}

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>