<?php
require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
require_login();
check_csrf();
$pdo = db();
$id = (int)($_POST['id'] ?? 0);
if ($id > 0) { $stmt = $pdo->prepare('DELETE FROM books WHERE id = ?'); $stmt->execute([$id]); flash('success','Libro eliminato.'); }
redirect('books.php');
