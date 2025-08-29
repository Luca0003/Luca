
<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$e = trim($_GET['e'] ?? '');
if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => true, 'valid' => false, 'available' => false]);
    exit;
}
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$e]);
    $exists = (bool)$stmt->fetch();
    echo json_encode(['ok' => true, 'valid' => true, 'available' => !$exists]);
} catch (Throwable $ex) {
    echo json_encode(['ok' => false, 'valid' => false, 'available' => false]);
}
