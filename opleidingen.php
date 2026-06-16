<?php
// opleidingen.php
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

// --- BERICHTEN OPVANGEN (PRG Patroon) ---
$success_msg = '';
$error_msg = '';
if (isset($_SESSION['success_msg'])) { $success_msg = $_SESSION['success_msg']; unset($_SESSION['success_msg']); }
if (isset($_SESSION['error_msg'])) { $error_msg = $_SESSION['error_msg']; unset($_SESSION['error_msg']); }

// --- FORMULIEREN VERWERKEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Opleidingstype aanmaken / bewerken
    if ($_POST['action'] === 'save_type') {
        $type_id = $_POST['type_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $valid_years = (int)($_POST['valid_years'] ?? 0);
        $category = $_POST['category'] ?? 'algemeen';

        if (empty($name)) {
            $_SESSION['error_msg'] = "De naam van de opleiding is verplicht.";
        } else {
            if (empty($type_id)) {
                // Nieuw type
                $stmt = $pdo->prepare("INSERT INTO education_types (name, description, valid_years, category) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $description, $valid_years, $category]);
                $_SESSION['success_msg'] = "Opleidingstype succesvol aangemaakt!";
            } else {
                // Bestaand type updaten
                $stmt = $pdo->prepare("UPDATE education_types SET name = ?, description = ?, valid_years = ?, category = ? WHERE id = ?");
                $stmt->execute([$name, $description, $valid_years, $category, $type_id]);
                $_SESSION['success_msg'] = "Opleidingstype succesvol bijgewerkt!";
            }
        }
        header("Location: opleidingen.php");
        exit;
    }

    // 2. Opleiding toewijzen aan medewerkers
    if ($_POST['action'] === 'assign_education') {
        $type_id = $_POST['type_id'] ?? '';
        $date_achieved = $_POST['date_achieved'] ?? '';
        $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
        $cert_number = trim($_POST['cert_number'] ?? '');
        $note = trim($_POST['note'] ?? '');
        $participants = $_POST['participants'] ?? [];

        if (empty($type_id) || empty($date_achieved) || empty($participants)) {
            $_SESSION['error_msg'] = "Selecteer een opleiding, datum en minimaal één medewerker.";
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO user_educations (user_id, type_id, date_achieved, expiry_date, cert_number, note) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($participants as $uid) {
                    $stmt->execute([$uid, $type_id, $date_achieved, $expiry_date, $cert_number, $note]);
                }
                $pdo->commit();
                $_SESSION['success_msg'] = "Opleiding succesvol toegewezen aan " . count($participants) . " medewerker(s)!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error_msg'] = "Fout bij opslaan: " . $e->getMessage();
            }
        }
        header("Location: opleidingen.php");
        exit;
    }

    // 3. Behaalde opleiding verwijderen
    if ($_POST['action'] === 'delete_education') {
        $edu_id = $_POST['education_id'];
        $stmt = $pdo->prepare("DELETE FROM user_educations WHERE id = ?");
        $stmt->execute([$edu_id]);
        $_SESSION['success_msg'] = "Behaalde opleiding verwijderd.";
        header("Location: opleidingen.php");
        exit;
    }
}

// --- DATA OPHALEN ---
$types = $pdo->query("SELECT * FROM education_types ORDER BY category ASC, name ASC")->fetchAll();

$educations = $pdo->query("
    SELECT ue.*, u.first_name, u.last_name, u.pnr 
    FROM user_educations ue
    JOIN users u ON ue.user_id = u.id
    ORDER BY ue.expiry_date ASC, u.first_name ASC
")->fetchAll();

$employees = $pdo->query("SELECT id, first_name, last_name, function_title FROM users WHERE role = 'employee' ORDER BY first_name ASC")->fetchAll();

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Opleidingen — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* CSS uit V3 ontwerp */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --blue:#1A5EA8;--purple:#6B21A8;
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
.card-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.card-header h3{font-size:16px;font-weight:600;}
.card-header-sub{font-size:12px;color:var(--text3);margin-top:2px;}
table{width:100%;border-collapse:collapse;}
th{font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);padding:10px 18px;text-align:left;background:var(--surface2);}
td{padding:11px 18px;font-size:13.5px;border-top:1px solid var(--border);vertical-align:middle;}
tr:hover td{background:#FAFAF8;}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 17px;border-radius:8px;border:none;font-family:var(--font);font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;}
.btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{opacity:.88;}
.btn-secondary{background:var(--surface2);color:var(--text);border:1px solid var(--border);} .btn-secondary:hover{background:var(--border);}
.btn-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;} .btn-danger:hover{background:#f5c6c3;}
.btn-sm{padding:5px 11px;font-size:12px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:500;}
.badge-ok{background:var(--green-light);color:var(--green);}
.badge-warning{background:#FEF5E7;color:#B7770D;}
.badge-expired{background:var(--danger-light);color:var(--danger);}
.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-info{background:#EBF3FB;color:var(--blue);}
.alert-success{background:var(--green-light);color:var(--green);}
.alert-danger{background:var(--danger-light);color:var(--danger);}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:100;opacity:0;pointer-events:none;transition:opacity .2s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border-radius:14px;width:100%;max-width:600px;max-height:92vh;overflow-y:auto;padding:26px 26px 22px;transform:translateY(12px);transition:transform .2s;}
.modal-overlay.open .modal{transform:translateY(0);}
.modal h3{font-size:17px;font-weight:600;margin-bottom:18px;}
.field{margin-bottom:14px;}
.field label{display:block;font-size:13px;font-weight:500;color:var(--text2);margin-bottom:5px;}
.field input, .field select, .field textarea{width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:8px;font-family:var(--font);font-size:14px;outline:none;}
.field input:focus, .field select:focus{border-color:var(--accent);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.modal-actions{display:flex;gap:9px;justify-content:flex-end;margin-top:20px;}

.emp-picker{border:1px solid var(--border);border-radius:8px;max-height:200px;overflow-y:auto;}
.emp-picker-item{display:flex;align-items:center;gap:10px;padding:8px 13px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s;}
.emp-picker-item:last-child{border-bottom:none;}
.emp-picker-item:hover{background:var(--surface2);}
.emp-picker-item input[type=checkbox]{width:14px;height:14px;accent-color:var(--accent);flex-shrink:0;}
.emp-picker-controls{display:flex;gap:7px;margin-bottom:7px;}
</style>
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main">
    <div class="page-header">
      <div class="page-header-text">
        <h1>Opleidingen</h1>
        <p>Certificaten & diploma's (zoals VCA en BRL) bijhouden per medewerker</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-secondary btn-sm" onclick="openTypeModal()">+ Opleiding aanmaken</button>
        <button class="btn btn-primary btn-sm" onclick="openAssignModal()">+ Toewijzen aan medewerker(s)</button>
      </div>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

    <?php if(count($types) === 0): ?>
        <div class="alert alert-info">Nog geen opleidingen aangemaakt. Klik rechtsboven op "+ Opleiding aanmaken" om te beginnen.</div>
    <?php endif; ?>

    <?php foreach($types as $type): ?>
      <?php 
        // Filter educations voor dit specifieke type
        $recs = array_filter($educations, function($e) use ($type) { return $e['type_id'] == $type['id']; });
        
        $catColors = ['veiligheid' => 'var(--accent)', 'transport' => 'var(--blue)', 'technisch' => 'var(--purple)', 'algemeen' => 'var(--green)'];
        $catColor = $catColors[$type['category']] ?? 'var(--text3)';
      ?>
      <div class="card">
        <div class="card-header">
          <div>
            <h3><?= htmlspecialchars($type['name']) ?> <span style="font-size:11px;color:<?= $catColor ?>;font-weight:400"><?= ucfirst(htmlspecialchars($type['category'])) ?></span></h3>
            <div class="card-header-sub">
                <?= htmlspecialchars($type['description']) ?> · 
                <?= $type['valid_years'] > 0 ? "Geldig <strong>{$type['valid_years']} jaar</strong>" : 'Geen vervaldatum' ?>
            </div>
          </div>
          <div style="display:flex;gap:6px">
            <button class="btn btn-secondary btn-sm" onclick="editType(<?= $type['id'] ?>, '<?= addslashes(htmlspecialchars($type['name'])) ?>', '<?= addslashes(htmlspecialchars($type['description'])) ?>', <?= $type['valid_years'] ?>, '<?= $type['category'] ?>')">✎ Bewerken</button>
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th>Medewerker</th>
              <th>Behaald op</th>
              <th>Vervaldatum</th>
              <th>Certificaatnr.</th>
              <th>Status</th>
              <th>Actie</th>
            </tr>
          </thead>
          <tbody>
            <?php if(count($recs) > 0): ?>
                <?php foreach($recs as $o): ?>
                    <?php 
                        $exp = $o['expiry_date'] && $o['expiry_date'] < $today;
                        $days = $o['expiry_date'] ? round((strtotime($o['expiry_date']) - strtotime($today))/86400) : null;
                        
                        if (!$o['expiry_date']) {
                            $badge = '<span class="badge badge-ok">Permanent</span>';
                        } elseif ($exp) {
                            $badge = '<span class="badge badge-expired">Verlopen</span>';
                        } elseif ($days <= 90) {
                            $badge = "<span class=\"badge badge-warning\">Verloopt ({$days} d)</span>";
                        } else {
                            $badge = '<span class="badge badge-ok">Geldig</span>';
                        }
                    ?>
                    <tr>
                      <td>
                          <strong><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?></strong><br>
                          <span style="font-size:11px;color:var(--text3)">Pnr: <?= htmlspecialchars($o['pnr'] ?: '—') ?></span>
                      </td>
                      <td><?= date('d-m-Y', strtotime($o['date_achieved'])) ?></td>
                      <td><?= $o['expiry_date'] ? date('d-m-Y', strtotime($o['expiry_date'])) : '—' ?></td>
                      <td style="font-family:var(--mono);font-size:12px"><?= htmlspecialchars($o['cert_number'] ?: '—') ?></td>
                      <td><?= $badge ?></td>
                      <td>
                          <form method="POST" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je dit certificaat wilt intrekken?');">
                              <input type="hidden" name="action" value="delete_education">
                              <input type="hidden" name="education_id" value="<?= $o['id'] ?>">
                              <button type="submit" class="btn btn-danger btn-sm">✕</button>
                          </form>
                      </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="empty-row"><td colspan="6" style="text-align:center;color:var(--text3);padding:20px;">Nog niemand heeft dit certificaat behaald.</td></tr>
            <?php endif; ?>
            <tr>
              <td colspan="6" style="padding:8px 18px">
                <button class="btn btn-primary btn-sm" onclick="openAssignModal(<?= $type['id'] ?>)">+ Snel toewijzen</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>

  </main>

  <div class="modal-overlay" id="modal-opl-type">
    <div class="modal">
      <h3 id="type-modal-title">Opleiding aanmaken</h3>
      <form method="POST" action="opleidingen.php">
          <input type="hidden" name="action" value="save_type">
          <input type="hidden" name="type_id" id="type-id" value="">
          
          <div class="field"><label>Naam *</label><input type="text" name="name" id="type-name" required placeholder="Bijv. VCA basis"></div>
          <div class="field"><label>Omschrijving</label><input type="text" name="description" id="type-desc" placeholder="Korte omschrijving..."></div>
          
          <div class="form-row">
            <div class="field">
                <label>Geldigheid (jaren)</label>
                <input type="number" name="valid_years" id="type-valid" min="0" max="20" value="3" placeholder="3">
                <div style="font-size:11px;color:var(--text3);margin-top:4px;">0 = geen vervaldatum</div>
            </div>
            <div class="field">
                <label>Categorie</label>
                <select name="category" id="type-cat">
                  <option value="veiligheid">Veiligheid</option>
                  <option value="transport">Transport</option>
                  <option value="technisch">Technisch</option>
                  <option value="algemeen">Algemeen</option>
                </select>
            </div>
          </div>
          
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-opl-type')">Annuleren</button>
            <button type="submit" class="btn btn-primary">Opslaan</button>
          </div>
      </form>
    </div>
  </div>

  <div class="modal-overlay" id="modal-opl-assign">
    <div class="modal" style="max-width:600px">
      <h3>Opleiding toewijzen</h3>
      <form method="POST" action="opleidingen.php">
          <input type="hidden" name="action" value="assign_education">
          
          <script>
            const typeValidities = {
                <?php foreach($types as $t): ?>
                    "<?= $t['id'] ?>": <?= $t['valid_years'] ?>,
                <?php endforeach; ?>
            };
          </script>

          <div class="form-row" style="margin-bottom:4px">
            <div class="field">
                <label>Opleiding *</label>
                <select name="type_id" id="assign-type" required onchange="calculateExpiry()">
                    <?php foreach($types as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['valid_years'] ? $t['valid_years'].' jr' : 'permanent' ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Behaald op *</label><input type="date" name="date_achieved" id="assign-date" required value="<?= date('Y-m-d') ?>" onchange="calculateExpiry()"></div>
          </div>
          
          <div class="form-row">
            <div class="field">
                <label>Vervaldatum <span style="font-weight:400;color:var(--text3)">(automatisch)</span></label>
                <input type="date" name="expiry_date" id="assign-expiry">
            </div>
            <div class="field"><label>Certificaatnummer (optioneel)</label><input type="text" name="cert_number" placeholder="Bijv. VCA-2024-00123"></div>
          </div>
          
          <div class="field">
            <label>Medewerkers *</label>
            <div class="emp-picker-controls">
              <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll(true)">Alles selecteren</button>
              <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll(false)">Deselecteren</button>
            </div>
            <div class="emp-picker">
                <?php foreach($employees as $emp): ?>
                    <label class="emp-picker-item">
                        <input type="checkbox" name="participants[]" value="<?= $emp['id'] ?>" class="emp-checkbox">
                        <div>
                            <div style="font-size:13.5px;font-weight:500"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                            <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($emp['function_title'] ?: 'Medewerker') ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="alert alert-info" style="font-size:12px; margin-top:8px; padding:8px;">Elke medewerker krijgt dezelfde behaaldatum en vervaldatum. Het certificaatnummer kan later per medewerker worden aangepast.</div>
          </div>
          
          <div class="field"><label>Notitie (optioneel)</label><textarea name="note" rows="2" placeholder="Bijv. groepstraining 2024..."></textarea></div>
          
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-opl-assign')">Annuleren</button>
            <button type="submit" class="btn btn-primary">Toewijzen</button>
          </div>
      </form>
    </div>
  </div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openTypeModal() {
    document.getElementById('type-modal-title').textContent = 'Opleiding aanmaken';
    document.getElementById('type-id').value = '';
    document.getElementById('type-name').value = '';
    document.getElementById('type-desc').value = '';
    document.getElementById('type-valid').value = '3';
    document.getElementById('type-cat').value = 'veiligheid';
    openModal('modal-opl-type');
}

function editType(id, name, desc, valid, cat) {
    document.getElementById('type-modal-title').textContent = 'Opleiding bewerken';
    document.getElementById('type-id').value = id;
    document.getElementById('type-name').value = name;
    document.getElementById('type-desc').value = desc;
    document.getElementById('type-valid').value = valid;
    document.getElementById('type-cat').value = cat;
    openModal('modal-opl-type');
}

function openAssignModal(preselectTypeId = null) {
    if(preselectTypeId) {
        document.getElementById('assign-type').value = preselectTypeId;
    }
    calculateExpiry();
    openModal('modal-opl-assign');
}

function calculateExpiry() {
    const typeId = document.getElementById('assign-type').value;
    const dateStr = document.getElementById('assign-date').value;
    const expiryInput = document.getElementById('assign-expiry');
    
    if (typeId && typeValidities[typeId] > 0 && dateStr) {
        const d = new Date(dateStr);
        d.setFullYear(d.getFullYear() + parseInt(typeValidities[typeId]));
        expiryInput.value = d.toISOString().split('T')[0];
    } else {
        expiryInput.value = '';
    }
}

function selectAll(check) {
    document.querySelectorAll('.emp-checkbox').forEach(cb => cb.checked = check);
}

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>