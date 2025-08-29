
<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

$u = trim($_GET['u'] ?? '');
// 3-32, lettere/numeri . _ - e backslash
if ($u === '' || !preg_match('/^[A-Za-z0-9._\-\\\\]{3,32}$/', $u)) {
    echo json_encode(['ok' => true, 'valid' => false, 'available' => false]);
    exit;
}
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE name = ? LIMIT 1');
    $stmt->execute([$u]);
    $exists = (bool)$stmt->fetch();
    echo json_encode(['ok' => true, 'valid' => true, 'available' => !$exists]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'valid' => false, 'available' => false]);
}
