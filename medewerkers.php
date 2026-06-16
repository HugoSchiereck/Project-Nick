<?php
// medewerkers.php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Haal huidige gebruiker op
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

// Alleen admins en managers mogen hier in
if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'manager') {
    die("Je hebt geen toegang tot deze pagina.");
}

// --- AUTO-SETUP VERLOF TABELLEN & KOLOMMEN ---
try { $pdo->exec("ALTER TABLE users ADD COLUMN snipper_saldo DECIMAL(5,2) DEFAULT 25.00"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN snipper_used DECIMAL(5,2) DEFAULT 0.00"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN atv_saldo DECIMAL(5,2) DEFAULT 10.00"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN atv_used DECIMAL(5,2) DEFAULT 0.00"); } catch (PDOException $e) {}

$pdo->exec("CREATE TABLE IF NOT EXISTS leave_mutations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('snipper', 'atv') NOT NULL,
    action ENUM('afschrijven', 'bijschrijven', 'saldo') NOT NULL,
    days DECIMAL(5,2) NOT NULL,
    description VARCHAR(255),
    mutation_date DATE,
    added_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;");

// --- BERICHTEN OPVANGEN ---
$success_msg = '';
$error_msg = '';
if (isset($_SESSION['success_msg'])) { $success_msg = $_SESSION['success_msg']; unset($_SESSION['success_msg']); }
if (isset($_SESSION['error_msg'])) { $error_msg = $_SESSION['error_msg']; unset($_SESSION['error_msg']); }

// --- FORMULIEREN VERWERKEN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Medewerker Toevoegen
    if ($_POST['action'] === 'add_employee') {
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'employee';
        $func = trim($_POST['function'] ?? '');
        $snipper = (float)($_POST['snipper'] ?? 25);
        $atv = (float)($_POST['atv'] ?? 10);
        $pnrInput = trim($_POST['pnr'] ?? '');

        if (empty($first) || empty($username) || empty($password)) {
            $_SESSION['error_msg'] = "Vul alle verplichte velden in.";
        } else {
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtCheck->execute([$username]);
            if ($stmtCheck->rowCount() > 0) {
                $_SESSION['error_msg'] = "Gebruikersnaam bestaat al.";
            } else {
                if (empty($pnrInput)) {
                    $pnrStmt = $pdo->query("SELECT MAX(CAST(pnr AS UNSIGNED)) FROM users WHERE pnr REGEXP '^[0-9]+$'");
                    $max_pnr = $pnrStmt->fetchColumn();
                    $pnrInput = $max_pnr ? (int)$max_pnr + 1 : 1001;
                }
                
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (pnr, first_name, last_name, username, email, password_hash, role, function_title, snipper_saldo, atv_saldo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$pnrInput, $first, $last, $username, $email, $hash, $role, $func, $snipper, $atv]);

                // -- AUDIT LOG --
                $logDetail = "$first $last ($role, pnr $pnrInput)";
                $pdo->exec("INSERT INTO audit_log (user_id, category, action, detail) VALUES ({$_SESSION['user_id']}, 'gebruiker', 'Medewerker toegevoegd', '$logDetail')");

                $_SESSION['success_msg'] = "Medewerker $first $last succesvol toegevoegd!";
            }
        }
        header("Location: medewerkers.php"); exit;
    }

    // 2. Medewerker Verwijderen
    if ($_POST['action'] === 'delete_employee') {
        $uid = $_POST['user_id'];
        $uInfo = $pdo->query("SELECT first_name, last_name FROM users WHERE id = " . (int)$uid)->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$uid]);

        // -- AUDIT LOG --
        if ($uInfo) {
            $pdo->exec("INSERT INTO audit_log (user_id, category, action, detail) VALUES ({$_SESSION['user_id']}, 'gebruiker', 'Medewerker verwijderd', '{$uInfo['first_name']} {$uInfo['last_name']}')");
        }
        
        $_SESSION['success_msg'] = "Medewerker verwijderd.";
        header("Location: medewerkers.php"); exit;
    }

    // 3. Wachtwoord Resetten
    if ($_POST['action'] === 'reset_password') {
        $uid = $_POST['user_id'];
        $new_pass = $_POST['new_password'];
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $uid]);

        // -- AUDIT LOG --
        $uInfo = $pdo->query("SELECT first_name, last_name FROM users WHERE id = " . (int)$uid)->fetch();
        $pdo->exec("INSERT INTO audit_log (user_id, category, action, detail) VALUES ({$_SESSION['user_id']}, 'gebruiker', 'Wachtwoord gereset', '{$uInfo['first_name']} {$uInfo['last_name']}')");

        $_SESSION['success_msg'] = "Wachtwoord succesvol gereset!";
        header("Location: medewerkers.php"); exit;
    }

    // 4. Rol Wijzigen
    if ($_POST['action'] === 'change_role') {
        $uid = $_POST['user_id'];
        $new_role = $_POST['new_role'];
        
        $uInfo = $pdo->query("SELECT first_name, last_name, role FROM users WHERE id = " . (int)$uid)->fetch();
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $uid]);

        // -- AUDIT LOG --
        $pdo->exec("INSERT INTO audit_log (user_id, category, action, detail) VALUES ({$_SESSION['user_id']}, 'gebruiker', 'Rol gewijzigd', '{$uInfo['first_name']} {$uInfo['last_name']}: {$uInfo['role']} → $new_role')");

        $_SESSION['success_msg'] = "Rol succesvol gewijzigd!";
        header("Location: medewerkers.php"); exit;
    }

    // 5. Individuele Snipperkaart Mutatie
    if ($_POST['action'] === 'mutate_leave') {
        $uid = $_POST['user_id'];
        $type = $_POST['type']; // 'snipper' of 'atv'
        $mut_action = $_POST['mut_action']; // 'afschrijven', 'bijschrijven', 'saldo'
        $days = (float)$_POST['days'];
        $desc = trim($_POST['description'] ?? '');
        $date = date('Y-m-d');

        if ($days > 0) {
            $uInfo = $pdo->query("SELECT * FROM users WHERE id = " . (int)$uid)->fetch();
            $saldoCol = $type . '_saldo';
            $usedCol = $type . '_used';
            
            $newSaldo = (float)$uInfo[$saldoCol];
            $newUsed = (float)$uInfo[$usedCol];

            if ($mut_action === 'saldo') {
                $newSaldo = $days;
                $newUsed = 0;
            } elseif ($mut_action === 'bijschrijven') {
                $newUsed = max(0, $newUsed - $days); // Brengt het 'verbruik' omlaag
            } elseif ($mut_action === 'afschrijven') {
                $newUsed = min($newSaldo, $newUsed + $days); // Verhoogt het verbruik
            }

            // Update user
            $stmtU = $pdo->prepare("UPDATE users SET $saldoCol = ?, $usedCol = ? WHERE id = ?");
            $stmtU->execute([$newSaldo, $newUsed, $uid]);

            // Save mutation history
            $stmtM = $pdo->prepare("INSERT INTO leave_mutations (user_id, type, action, days, description, mutation_date, added_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtM->execute([$uid, $type, $mut_action, $days, $desc, $date, $_SESSION['user_id']]);

            // -- AUDIT LOG --
            $logDetail = "{$uInfo['first_name']} {$uInfo['last_name']}: $mut_action {$days}d $type — " . ($desc ?: 'Geen omschrijving');
            $pdo->exec("INSERT INTO audit_log (user_id, category, action, detail) VALUES ({$_SESSION['user_id']}, 'verlof', 'Verlof/ATV mutatie', '$logDetail')");

            $_SESSION['success_msg'] = "Mutatie succesvol verwerkt!";
        }
        header("Location: medewerkers.php"); exit;
    }

    // 6. Bulk Verlof Toewijzen
    if ($_POST['action'] === 'bulk_leave') {
        $participants = $_POST['participants'] ?? [];
        $mut_action = $_POST['bulk_action'];
        $snipperDays = (float)($_POST['snipper_days'] ?? 0);
        $atvDays = (float)($_POST['atv_days'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $date = date('Y-m-d');

        if (empty($participants)) {
            $_SESSION['error_msg'] = "Selecteer minimaal één medewerker.";
        } else {
            $pdo->beginTransaction();
            foreach ($participants as $uid) {
                $uInfo = $pdo->query("SELECT * FROM users WHERE id = " . (int)$uid)->fetch();
                
                // Snipper
                if ($snipperDays > 0) {
                    $sSaldo = (float)$uInfo['snipper_saldo']; $sUsed = (float)$uInfo['snipper_used'];
                    if ($mut_action === 'saldo') { $sSaldo = $snipperDays; $sUsed = 0; }
                    elseif ($mut_action === 'bijschrijven') { $sUsed = max(0, $sUsed - $snipperDays); }
                    elseif ($mut_action === 'afschrijven') { $sUsed = min($sSaldo, $sUsed + $snipperDays); }
                    
                    $pdo->prepare("UPDATE users SET snipper_saldo = ?, snipper_used = ? WHERE id = ?")->execute([$sSaldo, $sUsed, $uid]);
                    $pdo->prepare("INSERT INTO leave_mutations (user_id, type, action, days, description, mutation_date, added_by) VALUES (?, 'snipper', ?, ?, ?, ?, ?)")
                        ->execute([$uid, $mut_action, $snipperDays, $desc, $date, $_SESSION['user_id']]);
                }

                // ATV
                if ($atvDays > 0) {
                    $aSaldo = (float)$uInfo['atv_saldo']; $aUsed = (float)$uInfo['atv_used'];
                    if ($mut_action === 'saldo') { $aSaldo = $atvDays; $aUsed = 0; }
                    elseif ($mut_action === 'bijschrijven') { $aUsed = max(0, $aUsed - $atvDays); }
                    elseif ($mut_action === 'afschrijven') { $aUsed = min($aSaldo, $aUsed + $atvDays); }
                    
                    $pdo->prepare("UPDATE users SET atv_saldo = ?, atv_used = ? WHERE id = ?")->execute([$aSaldo, $aUsed, $uid]);
                    $pdo->prepare("INSERT INTO leave_mutations (user_id, type, action, days, description, mutation_date, added_by) VALUES (?, 'atv', ?, ?, ?, ?, ?)")
                        ->execute([$uid, $mut_action, $atvDays, $desc, $date, $_SESSION['user_id']]);
                }
            }
            $pdo->commit();

            // -- AUDIT LOG --
            $logDetail = "$mut_action: " . ($snipperDays > 0 ? "{$snipperDays}d snipper " : "") . ($atvDays > 0 ? "{$atvDays}d ATV " : "") . "voor " . count($participants) . " medewerker(s) — $desc";
            $pdo->exec("INSERT INTO audit_log (user_id, category, action, detail) VALUES ({$_SESSION['user_id']}, 'verlof', 'Bulk verlof/ATV', '$logDetail')");

            $_SESSION['success_msg'] = "Bulkmutatie verwerkt voor " . count($participants) . " medewerker(s)!";
        }
        header("Location: medewerkers.php"); exit;
    }
}

// --- DATA OPHALEN ---
// Voor de tabel:
if ($currentUser['role'] === 'admin') {
    $employees = $pdo->query("SELECT * FROM users ORDER BY role ASC, first_name ASC")->fetchAll();
} else {
    $employees = $pdo->query("SELECT * FROM users WHERE role != 'admin' ORDER BY role ASC, first_name ASC")->fetchAll();
}

// Makkelijker: bouw een JSON object per user_id met zijn mutaties voor de pop-ups
$mutData = [];
$mutRaw = $pdo->query("SELECT m.*, u.first_name as by_first FROM leave_mutations m LEFT JOIN users u ON m.added_by = u.id ORDER BY m.mutation_date DESC, m.id DESC")->fetchAll();
foreach ($mutRaw as $m) {
    $mutData[$m['user_id']][] = [
        'date' => date('d-m-Y', strtotime($m['mutation_date'])),
        'type' => $m['type'],
        'action' => $m['action'],
        'days' => $m['days'],
        'desc' => htmlspecialchars($m['description']),
        'door' => htmlspecialchars($m['by_first'] ?: 'Systeem')
    ];
}

// Bepaal max PNR voor het toevoeg-formulier
$pnrStmt = $pdo->query("SELECT MAX(CAST(pnr AS UNSIGNED)) FROM users WHERE pnr REGEXP '^[0-9]+$'");
$max_pnr = $pnrStmt->fetchColumn();
$next_pnr = $max_pnr ? (int)$max_pnr + 1 : 1001;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Medewerkers — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* CSS Basis */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --blue:#1A5EA8;
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
table{width:100%;border-collapse:collapse;}
th{font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);padding:10px 18px;text-align:left;background:var(--surface2);}
td{padding:11px 18px;font-size:13.5px;border-top:1px solid var(--border);vertical-align:middle;}
tr:hover td{background:#FAFAF8;}

.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 17px;border-radius:8px;border:none;font-family:var(--font);font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;}
.btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{opacity:.88;}
.btn-secondary{background:var(--surface2);color:var(--text);border:1px solid var(--border);} .btn-secondary:hover{background:var(--border);}
.btn-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;} .btn-danger:hover{background:#f5c6c3;}
.btn-sm{padding:5px 11px;font-size:12px;}
.badge-manager{background:#E8F8F5;color:#0E6655;border:1px solid #A9DFBF;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:500;}
.tag{display:inline-block;padding:2px 8px;border-radius:5px;font-size:10px;background:var(--surface2);color:var(--text2);border:1px solid var(--border);}

.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-info{background:#EBF3FB;color:var(--blue);}
.alert-success{background:var(--green-light);color:var(--green);}
.alert-danger{background:var(--danger-light);color:var(--danger);}

/* Modals */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:100;opacity:0;pointer-events:none;transition:opacity .2s;}
.modal-overlay.open{opacity:1;pointer-events:all;}
.modal{background:var(--surface);border-radius:14px;width:100%;max-width:580px;max-height:92vh;overflow-y:auto;padding:26px 26px 22px;transform:translateY(12px);transition:transform .2s;}
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
.emp-picker-item:hover{background:var(--surface2);}
.emp-picker-item input[type=checkbox]{width:14px;height:14px;accent-color:var(--accent);}
.emp-picker-controls{display:flex;gap:7px;margin-bottom:7px;}
.bar-wrap{background:var(--surface2);border-radius:4px;height:6px;flex:1;overflow:hidden;}
.bar{height:6px;border-radius:4px;transition:width .3s;}
.bar.green{background:var(--green);}
.bar.blue{background:var(--blue);}
</style>
<script>
// JSON injectie van mutaties voor JS
const leaveMutations = <?= json_encode($mutData) ?>;
</script>
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main">
    <div class="page-header">
      <div class="page-header-text">
        <h1>Medewerkers</h1>
        <p>Personeelsbeheer, toegangsrechten en verlofsaldi</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn-secondary btn-sm" onclick="openBulkVerlofModal()">⚡ Bulk verlof/ATV</button>
        <button class="btn btn-primary btn-sm" onclick="openModal('modal-add-emp')">+ Toevoegen</button>
      </div>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= $success_msg ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-danger"><?= $error_msg ?></div><?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Pnr.</th>
            <th>Naam</th>
            <th>Gebruikersnaam</th>
            <th>Functie</th>
            <th>Rol</th>
            <th>Snipper (d)</th>
            <th>ATV (d)</th>
            <th>Actie</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($employees as $u): ?>
              <?php 
                  $sRes = $u['snipper_saldo'] - $u['snipper_used'];
                  $aRes = $u['atv_saldo'] - $u['atv_used'];
              ?>
              <tr>
                <td style="font-family:var(--mono);font-size:12px;color:var(--text3)"><?= htmlspecialchars($u['pnr'] ?: '—') ?></td>
                <td>
                    <div style="display:flex;align-items:center;gap:9px">
                        <div class="avatar" style="width:30px;height:30px;font-size:11px"><?= substr($u['first_name'],0,1) ?><?= substr($u['last_name'],0,1) ?></div>
                        <div>
                            <strong><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></strong><br>
                            <span style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($u['email'] ?: 'Geen e-mail') ?></span>
                        </div>
                    </div>
                </td>
                <td style="font-family:var(--mono);font-size:12px;color:var(--text2)"><?= htmlspecialchars($u['username']) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($u['function_title'] ?: '—') ?></td>
                <td>
                    <?php if($u['role'] === 'admin'): ?>
                        <span class="badge-manager" style="background:#FAEAE7;color:var(--accent);border-color:#F5C6C3">Hoofdbeheerder</span>
                    <?php elseif($u['role'] === 'manager'): ?>
                        <span class="badge-manager">Manager</span>
                    <?php else: ?>
                        <span style="font-size:12px;color:var(--text3)">Medewerker</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px">
                    <span style="font-weight:600"><?= number_format($sRes, 1, ',', '') ?></span><span style="color:var(--text3)">/<?= number_format($u['snipper_saldo'], 0, ',', '') ?>d</span>
                    <button class="btn btn-secondary btn-sm" style="padding:2px 6px;margin-left:4px" onclick="openSnipper(<?= $u['id'] ?>, '<?= addslashes($u['first_name'] . ' ' . $u['last_name']) ?>', '<?= $u['pnr'] ?>', <?= $u['snipper_saldo'] ?>, <?= $u['snipper_used'] ?>, <?= $u['atv_saldo'] ?>, <?= $u['atv_used'] ?>)" title="Beheren">✎</button>
                </td>
                <td style="font-size:12px">
                    <span style="font-weight:600"><?= number_format($aRes, 1, ',', '') ?></span><span style="color:var(--text3)">/<?= number_format($u['atv_saldo'], 0, ',', '') ?>d</span>
                </td>
                <td>
                    <div style="display:flex;gap:5px;">
                        <?php if($currentUser['role'] === 'admin' && $u['id'] !== $currentUser['id']): ?>
                            <button class="btn btn-secondary btn-sm" onclick="changeRole(<?= $u['id'] ?>, '<?= $u['role'] ?>')">Rol</button>
                        <?php endif; ?>
                        
                        <?php if($u['id'] !== $currentUser['id']): ?>
                            <button class="btn btn-secondary btn-sm" onclick="resetPassword(<?= $u['id'] ?>)">Ww.</button>
                        <?php endif; ?>

                        <?php if($currentUser['role'] === 'admin' && $u['id'] !== $currentUser['id']): ?>
                            <form method="POST" style="margin:0" onsubmit="return confirm('Weet je ZEKER dat je deze medewerker wilt verwijderen? Dit kan niet ongedaan worden gemaakt!');">
                                <input type="hidden" name="action" value="delete_employee">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">✕</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
              </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <div class="modal-overlay" id="modal-add-emp">
    <div class="modal">
      <h3>Medewerker toevoegen</h3>
      <form method="POST" action="medewerkers.php">
          <input type="hidden" name="action" value="add_employee">
          
          <div class="form-row">
              <div class="field"><label>Voornaam *</label><input type="text" name="first_name" required placeholder="Jan"></div>
              <div class="field"><label>Achternaam</label><input type="text" name="last_name" placeholder="Janssen"></div>
          </div>
          <div class="form-row">
              <div class="field"><label>Gebruikersnaam *</label><input type="text" name="username" required placeholder="jan.janssen"></div>
              <div class="field"><label>Wachtwoord *</label><input type="password" name="password" required placeholder="••••••••"></div>
          </div>
          <div class="form-row">
              <div class="field"><label>E-mailadres</label><input type="email" name="email" placeholder="jan@mstlogistics.nl"></div>
              <div class="field"><label>Functie</label><input type="text" name="function" placeholder="Chauffeur"></div>
          </div>
          <div class="form-row">
              <div class="field"><label>Personeelsnummer</label><input type="text" name="pnr" value="<?= $next_pnr ?>" placeholder="auto"></div>
              <div class="field">
                  <label>Rol</label>
                  <select name="role">
                      <option value="employee">Medewerker</option>
                      <?php if($currentUser['role'] === 'admin'): ?>
                          <option value="manager">Manager</option>
                          <option value="admin">Hoofdbeheerder</option>
                      <?php endif; ?>
                  </select>
              </div>
          </div>
          <div class="form-row">
              <div class="field"><label>Snipperdagen (start)</label><input type="number" name="snipper" step="0.5" value="25"></div>
              <div class="field"><label>ATV-dagen (start)</label><input type="number" name="atv" step="0.5" value="10"></div>
          </div>
          
          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-emp')">Annuleren</button>
            <button type="submit" class="btn btn-primary">Toevoegen</button>
          </div>
      </form>
    </div>
  </div>

  <div class="modal-overlay" id="modal-snipper">
    <div class="modal" style="max-width:620px">
      <h3 id="snipper-title">Verlof & ATV beheren</h3>
      
      <div id="snipper-stats" style="margin-bottom:16px;"></div>

      <form method="POST" action="medewerkers.php" style="background:#FAFAF8;border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:16px;">
          <input type="hidden" name="action" value="mutate_leave">
          <input type="hidden" name="user_id" id="mut-user-id">
          
          <div class="form-row">
            <div class="field">
                <label>Type</label>
                <select name="type">
                    <option value="snipper">Snipperdagen</option>
                    <option value="atv">ATV-dagen</option>
                </select>
            </div>
            <div class="field">
                <label>Actie</label>
                <select name="mut_action">
                    <option value="afschrijven">Dagen afschrijven (-)</option>
                    <option value="bijschrijven">Dagen bijschrijven (+)</option>
                    <option value="saldo">Nieuw jaarsaldo instellen</option>
                </select>
            </div>
          </div>
          <div class="form-row">
              <div class="field"><label>Aantal dagen</label><input type="number" name="days" step="0.5" min="0" required placeholder="Bijv. 1.5"></div>
              <div class="field"><label>Omschrijving</label><input type="text" name="description" placeholder="Bijv. correctie of vakantie"></div>
          </div>
          <div style="text-align:right">
              <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modal-snipper')">Sluiten</button>
              <button type="submit" class="btn btn-primary btn-sm">Verwerken</button>
          </div>
      </form>

      <div class="card" style="margin:0">
        <div class="card-header"><h3>Mutatiehistorie</h3></div>
        <div style="max-height:180px;overflow-y:auto">
          <table>
            <thead><tr><th>Datum</th><th>Type</th><th>Omschrijving</th><th>Dagen</th><th>Door</th></tr></thead>
            <tbody id="mut-history-body"></tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <div class="modal-overlay" id="modal-bulk">
    <div class="modal" style="max-width:620px">
      <h3>Bulk toewijzen — Snipperdagen & ATV</h3>
      <div class="alert alert-info" style="margin-bottom:14px">Selecteer medewerkers en stel het saldo in. Bestaand saldo wordt <strong>overschreven</strong> tenzij je "Bijschrijven" kiest.</div>
      
      <form method="POST" action="medewerkers.php">
          <input type="hidden" name="action" value="bulk_leave">
          
          <div class="form-row">
            <div class="field">
                <label>Actie</label>
                <select name="bulk_action">
                    <option value="saldo">Jaarsaldo instellen (overschrijven)</option>
                    <option value="bijschrijven">Bijschrijven bovenop huidig saldo</option>
                    <option value="afschrijven">Afschrijven van huidig saldo</option>
                </select>
            </div>
            <div class="field"><label>Omschrijving</label><input type="text" name="description" placeholder="Bijv. jaarlijkse toekenning" required></div>
          </div>
          <div class="form-row">
            <div class="field"><label>Snipperdagen (+/-)</label><input type="number" name="snipper_days" step="0.5" placeholder="25"></div>
            <div class="field"><label>ATV-dagen (+/-)</label><input type="number" name="atv_days" step="0.5" placeholder="10"></div>
          </div>

          <div class="field">
            <label>Medewerkers selecteren</label>
            <div class="emp-picker-controls">
                <button type="button" class="btn btn-sm btn-secondary" onclick="document.querySelectorAll('.bulk-cb').forEach(c=>c.checked=true)">Alles selecteren</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="document.querySelectorAll('.bulk-cb').forEach(c=>c.checked=false)">Deselecteren</button>
            </div>
            <div class="emp-picker">
                <?php foreach($employees as $emp): if($emp['role']==='admin') continue; ?>
                    <label class="emp-picker-item">
                        <input type="checkbox" name="participants[]" value="<?= $emp['id'] ?>" class="bulk-cb">
                        <div>
                            <div style="font-size:13.5px;font-weight:500"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></div>
                            <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($emp['function_title']) ?></div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
          </div>

          <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-bulk')">Annuleren</button>
            <button type="submit" class="btn btn-primary">Verwerken</button>
          </div>
      </form>
    </div>
  </div>

  <form id="form-reset-pass" method="POST" action="medewerkers.php" style="display:none;">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="rp_uid">
      <input type="hidden" name="new_password" id="rp_pass">
  </form>
  <form id="form-change-role" method="POST" action="medewerkers.php" style="display:none;">
      <input type="hidden" name="action" value="change_role">
      <input type="hidden" name="user_id" id="cr_uid">
      <input type="hidden" name="new_role" id="cr_role">
  </form>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openBulkVerlofModal() {
    openModal('modal-bulk');
}

function resetPassword(uid) {
    const np = prompt("Voer het nieuwe wachtwoord in voor deze medewerker:");
    if (np && np.trim().length >= 6) {
        document.getElementById('rp_uid').value = uid;
        document.getElementById('rp_pass').value = np;
        document.getElementById('form-reset-pass').submit();
    } else if (np) {
        alert("Wachtwoord moet minimaal 6 tekens zijn.");
    }
}

function changeRole(uid, currentRole) {
    const roles = "Kies: employee / manager / admin";
    const nr = prompt(`Huidige rol: ${currentRole}\nNieuwe rol invoeren:\n(${roles})`, currentRole);
    if (nr && ['employee', 'manager', 'admin'].includes(nr.toLowerCase())) {
        if (nr.toLowerCase() === 'admin' && !confirm("Weet je ZEKER dat je deze persoon volledige hoofdbeheerder-rechten wilt geven?")) return;
        document.getElementById('cr_uid').value = uid;
        document.getElementById('cr_role').value = nr.toLowerCase();
        document.getElementById('form-change-role').submit();
    } else if (nr) {
        alert("Ongeldige rol ingevoerd.");
    }
}

function openSnipper(uid, name, pnr, sSaldo, sUsed, aSaldo, aUsed) {
    document.getElementById('mut-user-id').value = uid;
    document.getElementById('snipper-title').textContent = `Verlof & ATV — ${name} (Pnr: ${pnr || '—'})`;
    
    let sRes = (sSaldo - sUsed).toFixed(1);
    let aRes = (aSaldo - aUsed).toFixed(1);
    let sPct = Math.min(100, Math.max(0, (sUsed / Math.max(1, sSaldo)) * 100));
    let aPct = Math.min(100, Math.max(0, (aUsed / Math.max(1, aSaldo)) * 100));

    document.getElementById('snipper-stats').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px 14px">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:6px">📅 Snipperdagen</div>
        <div style="display:flex;gap:12px;align-items:baseline">
          <span style="font-size:22px;font-weight:700;color:var(--green)">${sRes}d</span>
          <span style="font-size:12px;color:var(--text3)">resterend</span>
        </div>
        <div style="font-size:12px;color:var(--text3);margin-top:3px">${sUsed}d gebruikt / ${sSaldo}d saldo</div>
        <div class="bar-wrap" style="margin-top:6px"><div class="bar green" style="width:${sPct}%"></div></div>
      </div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:12px 14px">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:6px">⚡ ATV-dagen</div>
        <div style="display:flex;gap:12px;align-items:baseline">
          <span style="font-size:22px;font-weight:700;color:var(--blue)">${aRes}d</span>
          <span style="font-size:12px;color:var(--text3)">resterend</span>
        </div>
        <div style="font-size:12px;color:var(--text3);margin-top:3px">${aUsed}d gebruikt / ${aSaldo}d saldo</div>
        <div class="bar-wrap" style="margin-top:6px"><div class="bar blue" style="width:${aPct}%"></div></div>
      </div>
    </div>`;

    let mutHtml = '';
    if (leaveMutations[uid] && leaveMutations[uid].length > 0) {
        leaveMutations[uid].forEach(m => {
            let isSnip = m.type === 'snipper';
            let sign = m.action === 'afschrijven' ? '−' : (m.action === 'bijschrijven' ? '+' : '=');
            let color = m.action === 'afschrijven' ? 'var(--danger)' : (m.action === 'bijschrijven' ? 'var(--green)' : 'var(--blue)');
            mutHtml += `<tr>
                <td style="font-size:12px">${m.date}</td>
                <td><span class="tag" style="font-size:10px">${isSnip ? 'Snipper' : 'ATV'}</span></td>
                <td style="font-size:12px;color:var(--text2)">${m.desc || '—'}</td>
                <td style="font-size:12px;font-weight:600;color:${color}">${sign}${parseFloat(m.days)}d</td>
                <td style="font-size:11px;color:var(--text3)">${m.door}</td>
            </tr>`;
        });
    } else {
        mutHtml = '<tr class="empty-row"><td colspan="5" style="text-align:center;color:var(--text3);padding:20px;">Geen mutaties</td></tr>';
    }
    document.getElementById('mut-history-body').innerHTML = mutHtml;

    openModal('modal-snipper');
}

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>