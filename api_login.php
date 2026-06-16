<?php
// api_login.php
require 'config.php';
header('Content-Type: application/json');

// --- AUTO-SETUP VOOR EERSTE GEBRUIK ---
// Maakt een standaard admin aan als de database helemaal leeg is.
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
if ($stmt->fetchColumn() == 0) {
    $default_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username, password_hash, role, first_name, email) VALUES ('admin', ?, 'admin', 'Beheerder', 'nick@mstlogistics.nl')")->execute([$default_hash]);
}
// --------------------------------------

$action = $_POST['action'] ?? '';

// --- STAP 1: GEBRUIKERSNAAM & WACHTWOORD CONTROLE ---
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        
        // 2FA voor Admins en Managers
        if ($user['role'] === 'admin' || $user['role'] === 'manager') {
            $code = sprintf("%06d", mt_rand(1, 999999)); // Genereer 6 cijfers
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Sla code op in database
            $stmt = $pdo->prepare("UPDATE users SET two_factor_code = ?, two_factor_expires = ? WHERE id = ?");
            $stmt->execute([$code, $expires, $user['id']]);

            // Stuur de 2FA e-mail
            $to = $user['email'];
            $subject = "MST Logistics - Jouw 2FA Inlogcode";
            $message = "Beste " . $user['first_name'] . ",\n\nJe eenmalige inlogcode is: " . $code . "\nDeze code is 15 minuten geldig.\n\nMet vriendelijke groet,\nMST Logistics";
            $headers = "From: noreply@mstlogistics.nl";
            @mail($to, $subject, $message, $headers);

            echo json_encode(['status' => 'require_2fa', 'user_id' => $user['id']]);
        } else {
            // Personeel mag direct door
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            echo json_encode(['status' => 'success']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Ongeldige gebruikersnaam of wachtwoord.']);
    }
    exit;
}

// --- STAP 2: 2FA CODE CONTROLEREN ---
if ($action === 'verify_2fa') {
    $user_id = $_POST['user_id'] ?? '';
    $code = trim($_POST['code'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND two_factor_code = ? AND two_factor_expires > NOW()");
    $stmt->execute([$user_id, $code]);
    $user = $stmt->fetch();

    if ($user) {
        // Code klopt! Log de gebruiker in en verwijder de code uit de database
        $pdo->prepare("UPDATE users SET two_factor_code = NULL, two_factor_expires = NULL WHERE id = ?")->execute([$user_id]);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Ongeldige of verlopen code.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Ongeldige actie.']);
?>