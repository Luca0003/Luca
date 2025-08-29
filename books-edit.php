<?php
require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
require_login();
include __DIR__ . '/sezioni/header.php';
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$u = current_user(); $uid = $u['id'] ?? null;
$stmt = $pdo->prepare('SELECT * FROM books WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $uid]);
$book = $stmt->fetch();
if (!$book) { flash('error','Libro non trovato.'); redirect('books.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? ($book['status'] ?? 'non_letto');
    if (!in_array($status, ['non_letto','in_lettura','letto'], true)) { $status = 'non_letto'; }
    $coverPath = $book['cover_path'];
    $errors = [];
    if ($title === '') $errors[] = 'Il titolo è obbligatorio.';
    if ($author === '') $errors[] = 'L\'autore è obbligatorio.';
    if (!valid_year($year)) $errors[] = 'Anno non valido.';
    if (!empty($_FILES['cover']['name'])) {
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($allowed[$_FILES['cover']['type']])) { $errors[] = 'Formato copertina non supportato.'; }
        elseif ($_FILES['cover']['size'] > 4 * 1024 * 1024) { $errors[] = 'La copertina supera i 4 MB.'; }
        elseif (is_uploaded_file($_FILES['cover']['tmp_name'])) {
            $ext = $allowed[$_FILES['cover']['type']];
            $name = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = __DIR__ . '/uploads/' . $name;
            if (!move_uploaded_file($_FILES['cover']['tmp_name'], $dest)) { $errors[] = 'Errore durante il salvataggio della copertina.'; }
            else { $coverPath = 'uploads/' . $name; }
        }
    }
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE books
        SET title = :title,
            author = :author,
            genre = :genre,
            year = :year,
            description = :description
        WHERE id = :id AND user_id = :uid");
$stmt->execute([
    ':title' => $title,
    ':author' => $author,
    ':genre' => $genre,
    ':year' => $year,
    ':description' => $description,
    ':id' => $id,
    ':uid' => $uid
]);
        flash('success','Libro aggiornato.');
        redirect('book.php?id=' . $id);
    } else { flash('error', implode(' ', $errors)); redirect('books-edit.php?id=' . $id); }
}
?>
<h2 class="mb-3">Modifica libro</h2>
<form class="card shadow-sm" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int)$book['id'] ?>">
  <div class="card-body">
  <div class="mb-3">
    <label class="form-label">Stato lettura</label>
    <select class="form-select" name="status">
      <?php $st = $book['status'] ?? 'non_letto'; ?>
      <option value="non_letto" <?= ($st==='non_letto')?'selected':'' ?>>Non letto</option>
      <option value="in_lettura" <?= ($st==='in_lettura')?'selected':'' ?>>In lettura</option>
      <option value="letto" <?= ($st==='letto')?'selected':'' ?>>Letto</option>
    </select>
  </div>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Titolo <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" value="<?= e($book['title']) ?>" required></div>
      <div class="col-md-6"><label class="form-label">Autore <span class="text-danger">*</span></label><input type="text" name="author" class="form-control" value="<?= e($book['author']) ?>" required></div>
      <div class="col-md-3"><label class="form-label">Anno</label><input type="number" name="year" class="form-control" min="0" max="9999" value="<?= e($book['year']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Genere</label><input type="text" name="genre" class="form-control" value="<?= e($book['genre']) ?>"></div>
      <div class="col-md-6"><label class="form-label">ISBN</label><input type="text" name="isbn" class="form-control" value="<?= e($book['isbn']) ?>"></div>
      <div class="col-md-12"><label class="form-label">Descrizione</label><textarea name="description" class="form-control" rows="4"><?= e($book['description']) ?></textarea></div>
      <div class="col-md-6">
        <label class="form-label">Copertina</label>
        <input type="file" name="cover" class="form-control" accept=".jpg,.jpeg,.png,.webp">
        <?php if (!empty($book['cover_path'])): ?>
          <img src="<?= e($book['cover_path']) ?>" class="img-thumbnail mt-2 cover-preview" alt="Copertina attuale">
        <?php endif; ?>
        <img id="coverPreview" class="img-thumbnail mt-2 d-none cover-preview" alt="Anteprima nuova copertina">
        <?php if (!empty($book['cover_path'])): ?><div class="form-text">Copertina attuale: <a href="<?= e($book['cover_path']) ?>" target="_blank">apri</a></div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between">
    <a href="book.php?id=<?= (int)$book['id'] ?>" class="btn btn-outline-secondary">Annulla</a>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Salva</button>
  </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var input = document.querySelector('input[name="cover"]');
  var img = document.getElementById('coverPreview');
  if(input && img){
    input.addEventListener('change', function(){
      const f = this.files && this.files[0];
      if (f) { img.src = URL.createObjectURL(f); img.classList.remove('d-none'); }
      else { img.src=''; img.classList.add('d-none'); }
    });
  }
});
</script>
<?php include __DIR__ . '/sezioni/footer.php'; ?>
