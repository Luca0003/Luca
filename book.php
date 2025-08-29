<?php
require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
include __DIR__ . '/sezioni/header.php';
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$u = current_user();
$uid = $u['id'] ?? null;
if ($uid === null) { flash('error','Libro non trovato.'); redirect('books.php'); }
$stmt = $pdo->prepare('SELECT * FROM books WHERE id = :id AND user_id = :uid');
$stmt->execute([':id'=>$id, ':uid'=>$uid]);
$book = $stmt->fetch();
if (!$book) { flash('error','Libro non trovato.'); redirect('books.php'); }
$pdo->prepare('UPDATE books SET views = views + 1 WHERE id = :id AND user_id = :uid')->execute([':id'=>$id, ':uid'=>$uid]);
$book['views']++;
?>
<div class="row g-4">
  <div class="col-md-4">
    <div class="bg-white p-3 rounded-3 shadow-sm text-center">
      <?php if (!empty($book['cover_path'])): ?>
        <img src="<?= e($book['cover_path']) ?>" alt="Copertina" class="img-fluid rounded shadow-sm" style="max-height: 480px; width: auto;">
      <?php else: ?>
        <div class="ratio ratio-3x4 bg-light rounded d-flex align-items-center justify-content-center text-muted">
          <i class="fa-regular fa-image fa-2xl"></i>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-md-8">
    <div class="bg-white p-4 rounded-3 shadow-sm">
      <div class="d-flex align-items-start">
        <h2 class="mb-3 flex-grow-1"><?= e($book['title']) ?></h2>
        <div class="ms-2"><a class="btn btn-outline-secondary" href="books-edit.php?id=<?= (int)$book['id'] ?>"><i class="fa-regular fa-pen-to-square me-1"></i>Modifica</a></div>
      </div>
      <dl class="row">
        <dt class="col-sm-3">Autore</dt><dd class="col-sm-9"><?= e($book['author']) ?></dd>
        <dt class="col-sm-3">Genere</dt><dd class="col-sm-9"><?= e($book['genre']) ?></dd>
        <dt class="col-sm-3">Anno</dt><dd class="col-sm-9"><?= e($book['year']) ?></dd>
        <dt class="col-sm-3">ISBN</dt><dd class="col-sm-9"><?= e($book['isbn']) ?></dd>
        <dt class="col-sm-3">Visualizzazioni</dt><dd class="col-sm-9"><?= (int)$book['views'] ?></dd>
      </dl>
      <?php if (!empty($book['description'])): ?>
        <h5 class="mt-3">Descrizione</h5>
        <p><?= nl2br(e($book['description'])) ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/sezioni/footer.php'; ?>
