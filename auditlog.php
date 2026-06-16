<?php
// auditlog.php
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

// --- AUTO-SETUP TABEL ---
$pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    category VARCHAR(50),
    action VARCHAR(100),
    detail TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;");

// Controleer of de tabel leeg is, voeg dan een welkomstberichtje toe
$logCheck = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
if ($logCheck == 0) {
    $pdo->exec("INSERT INTO audit_log (user_id, category, action, detail) VALUES (NULL, 'systeem', 'Portaal gelanceerd', 'Het MST Logistics personeelsportaal is succesvol geïnstalleerd en verbonden met de database.')");
}

// --- BERICHTEN OPVANGEN (PRG Patroon) ---
$success_msg = '';
if (isset($_SESSION['success_msg'])) { $success_msg = $_SESSION['success_msg']; unset($_SESSION['success_msg']); }

// --- FORMULIER VERWERKEN (Log wissen) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_log') {
    // Alleen de echte hoofdbeheerder mag logs wissen!
    if ($currentUser['role'] === 'admin') {
        $pdo->exec("TRUNCATE TABLE audit_log");
        
        // Log direct dat de admin de boel heeft gewist
        $stmtLog = $pdo->prepare("INSERT INTO audit_log (user_id, category, action, detail) VALUES (?, 'systeem', 'Logboek gewist', 'De hoofdbeheerder heeft de volledige wijzigingsgeschiedenis verwijderd.')");
        $stmtLog->execute([$currentUser['id']]);
        
        $_SESSION['success_msg'] = "Wijzigingslog succesvol gewist.";
    }
    header("Location: auditlog.php");
    exit;
}

// --- DATA OPHALEN & FILTEREN ---
$filter = $_GET['category'] ?? '';

$query = "
    SELECT a.*, u.first_name, u.last_name 
    FROM audit_log a 
    LEFT JOIN users u ON a.user_id = u.id 
";
$params = [];

if (!empty($filter)) {
    $query .= " WHERE a.category = ? ";
    $params[] = $filter;
}

$query .= " ORDER BY a.created_at DESC LIMIT 500"; // Maximaal 500 regels om de pagina snel te houden

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Kleuren voor de categorie-labels
$catColors = [
    'verlof' => 'var(--accent)',
    'code95' => 'var(--green)',
    'tcvt' => 'var(--blue)',
    'opleiding' => 'var(--purple)',
    'cursus' => 'var(--accent2)',
    'gebruiker' => 'var(--text2)',
    'systeem' => '#1A1A18'
];
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Wijzigingslog — MST Logistics</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* Basis CSS */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --bg:#F5F3EE;--surface:#FFF;--surface2:#EFECE6;--border:#DDD9D0;
  --accent:#D4351C;--accent-light:#FAEAE7;
  --accent2:#C8873A;--accent2-light:#FAF0E4;
  --danger:#C0392B;--danger-light:#FAEAEA;
  --green:#1F7A4A;--green-light:#E4F3EC;
  --blue:#1A5EA8;--blue-light:#EBF3FB;
  --purple:#6B21A8;--purple-light:#F3E8FD;
  --text:#1A1A18;--text2:#5A5A54;--text3:#9A9A90;
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
.card-body-pad{padding:18px 20px;}

.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 17px;border-radius:8px;border:none;font-family:var(--font);font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;}
.btn-primary{background:var(--accent);color:#fff;} .btn-primary:hover{opacity:.88;}
.btn-danger{background:var(--danger-light);color:var(--danger);border:1px solid #F5C6C3;} .btn-danger:hover{background:#f5c6c3;}
.btn-sm{padding:5px 11px;font-size:12px;}

.alert{padding:12px 16px;border-radius:8px;font-size:13.5px;margin-bottom:20px;}
.alert-success{background:var(--green-light);color:var(--green);}

/* Specifieke Audit Log styling uit jouw origineel */
.audit-row{font-size:13px;color:var(--text2);padding:10px 0;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start;}
.audit-row:last-child{border-bottom:none;}
.audit-who{color:var(--accent);font-weight:600;min-width:130px;}
.audit-time{color:var(--text3);min-width:120px;font-size:12px;}
</style>
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main">
    <div class="page-header">
      <div class="page-header-text">
        <h1>Wijzigingslog</h1>
        <p>Wie heeft wat gewijzigd en wanneer</p>
      </div>
      <div class="page-header-actions">
        <?php if ($currentUser['role'] === 'admin'): ?>
            <form method="POST" action="auditlog.php" style="margin:0" onsubmit="return confirm('Weet je ZEKER dat je het hele logboek wilt wissen? Dit kan niet ongedaan worden gemaakt!');">
                <input type="hidden" name="action" value="clear_log">
                <button type="submit" class="btn btn-danger btn-sm">Log wissen</button>
            </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>

    <div class="card">
      <div class="card-header">
        <h3>Alle wijzigingen</h3>
        <select onchange="window.location.href='?category=' + this.value;" style="font-size:13px;padding:6px 12px;border:1px solid var(--border);border-radius:7px;font-family:var(--font);background:var(--surface);outline:none;">
          <option value="">Alle categorieën</option>
          <option value="systeem" <?= $filter === 'systeem' ? 'selected' : '' ?>>Systeemmeldingen</option>
          <option value="gebruiker" <?= $filter === 'gebruiker' ? 'selected' : '' ?>>Gebruikers / HR</option>
          <option value="verlof" <?= $filter === 'verlof' ? 'selected' : '' ?>>Verlof</option>
          <option value="code95" <?= $filter === 'code95' ? 'selected' : '' ?>>Code 95</option>
          <option value="tcvt" <?= $filter === 'tcvt' ? 'selected' : '' ?>>TCVT</option>
          <option value="opleiding" <?= $filter === 'opleiding' ? 'selected' : '' ?>>Opleidingen</option>
          <option value="cursus" <?= $filter === 'cursus' ? 'selected' : '' ?>>Cursussen</option>
        </select>
      </div>
      <div class="card-body-pad">
        <?php if (count($logs) === 0): ?>
            <div style="color:var(--text3);text-align:center;padding:20px 0;">Geen wijzigingen gevonden voor deze categorie.</div>
        <?php else: ?>
            <div style="max-height:600px;overflow-y:auto;padding-right:10px;">
                <?php foreach($logs as $l): ?>
                    <?php
                        $who = $l['user_id'] ? ($l['first_name'] . ' ' . $l['last_name']) : 'Systeem';
                        $catColor = $catColors[$l['category']] ?? 'var(--text3)';
                    ?>
                    <div class="audit-row">
                        <span class="audit-who"><?= htmlspecialchars($who) ?></span>
                        <span style="padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:<?= $catColor ?>15;color:<?= $catColor ?>;min-width:85px;text-align:center;text-transform:uppercase;">
                            <?= htmlspecialchars($l['category']) ?>
                        </span>
                        <span style="flex:1;">
                            <strong><?= htmlspecialchars($l['action']) ?></strong>: 
                            <span style="color:var(--text)"><?= htmlspecialchars($l['detail']) ?></span>
                        </span>
                        <span class="audit-time"><?= date('d-m-Y H:i', strtotime($l['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

</body>
</html>