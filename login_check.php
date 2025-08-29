
<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') {
    echo json_encode(['ok' => true, 'valid' => false]);
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    $valid = $row && password_verify($pass, $row['password_hash']);
    echo json_encode(['ok' => true, 'valid' => (bool)$valid]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'valid' => false]);
}
