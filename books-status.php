<?php
require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'msg'=>'Method not allowed']); exit; }

$token = $_POST['csrf_token'] ?? '';
if ($token !== csrf_token()) { http_response_code(419); echo json_encode(['ok'=>false,'msg'=>'CSRF token mismatch']); exit; }

$id = (int)($_POST['id'] ?? 0);
$next = $_POST['next'] ?? '';
$allowed = ['non_letto','in_lettura','letto'];
if (!$id || !in_array($next, $allowed, true)) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Bad params']); exit; }

$pdo = db();
$u = current_user(); $uid = $u['id'] ?? null;
$stmt = $pdo->prepare("UPDATE books SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
$stmt->execute([$next, $id, $uid]);

echo json_encode(['ok'=>true, 'id'=>$id, 'status'=>$next]);
