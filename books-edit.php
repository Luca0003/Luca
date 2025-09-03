<?php

    
    
session_start();

require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
require_login();

$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$u = current_user(); 
$uid = $u['id'] ?? null;

$stmt = $pdo->prepare('SELECT * FROM books WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $uid]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$book) { flash('error','Libro non trovato.'); redirect('books.php'); exit; }

// --- POST: salva ---
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

    $errors = [];
    if ($title === '')  $errors[] = 'Il titolo è obbligatorio.';
    if ($author === '') $errors[] = "L'autore è obbligatorio.";
    if ($year !== '' && !valid_year($year)) $errors[] = 'Anno non valido.';

    // Copertina: mantieni quella attuale se non viene caricata una nuova
    $coverPath = $book['cover_path'] ?? null;
    if (!empty($_FILES['cover']['name'])) {
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($allowed[$_FILES['cover']['type']])) {
            $errors[] = 'Formato copertina non supportato.';
        } elseif (!is_uploaded_file($_FILES['cover']['tmp_name'])) {
            $errors[] = 'Upload copertina non valido.';
        } else {
            if (!is_dir(__DIR__ . '/uploads')) { @mkdir(__DIR__ . '/uploads', 0777, true); }
            $ext  = $allowed[$_FILES['cover']['type']];
            $name = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = __DIR__ . '/uploads/' . $name;
            if (!move_uploaded_file($_FILES['cover']['tmp_name'], $dest)) {
                $errors[] = 'Errore durante il salvataggio della copertina.';
            } else {
                $coverPath = 'uploads/' . $name; // path relativo
            }
        }
    }

    if (!empty($errors)) {
        flash('error', implode(' ', $errors));
        redirect('books-edit.php?id=' . $id);
        exit;
    }

    // --- UPDATE robusto: costruisce SET solo con colonne realmente presenti ---
    // Questo evita "Invalid parameter number" perché i placeholder coincidono esattamente con i bind.
    $colsPresent = [];
    try {
        $res = $pdo->query("SHOW COLUMNS FROM books");
        foreach ($res as $row) { $colsPresent[] = $row['Field']; }
    } catch (Throwable $e) {
        // fallback prudente
        $colsPresent = ['title','author','year','genre','isbn','description','cover_path','status','updated_at'];
    }

    $setParts = [];
    $bind = []

;   // valori candidati
    $data = [
        'title'       => $title,
        'author'      => $author,
        'year'        => ($year !== '' ? (int)$year : null),
        'genre'       => ($genre !== '' ? $genre : null),
        'isbn'        => ($isbn !== '' ? $isbn : null),
        'description' => ($desc !== '' ? $desc : null),
        'cover_path'  => ($coverPath !== '' ? $coverPath : null),
        'status'      => $status,
    ];

    foreach ($data as $col => $val) {
        if (in_array($col, $colsPresent, true)) {
            $setParts[] = '`' . $col . '` = :' . $col;
            $bind[':' . $col] = $val;
        }
    }
    if (in_array('updated_at', $colsPresent, true)) {
        $setParts[] = '`updated_at` = NOW()';
    }

    if (empty($setParts)) {
        flash('error','Nessun campo aggiornabile trovato.');
        redirect('books-edit.php?id=' . $id);
        exit;
    }

    $sql = "UPDATE `books` SET " . implode(', ', $setParts) . " WHERE id = :id AND user_id = :uid";
    $stmt = $pdo->prepare($sql);
    foreach ($bind as $k => $v) {
        if ($v === null) { $stmt->bindValue($k, null, PDO::PARAM_NULL); }
        elseif (is_int($v)) { $stmt->bindValue($k, $v, PDO::PARAM_INT); }
        else { $stmt->bindValue($k, $v, PDO::PARAM_STR); }
    }
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
    $stmt->execute();

    flash('success','Libro aggiornato.');
    redirect('book.php?id=' . $id);
    exit;
}

// --- GET: form ---
include __DIR__ . '/sezioni/header.php';
?>
<h2 class="mb-3">Modifica libro</h2>
<form class="card shadow-sm" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int)$book['id'] ?>">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Titolo <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" value="<?= e($book['title']) ?>" required></div>
      <div class="col-md-6"><label class="form-label">Autore <span class="text-danger">*</span></label><input type="text" name="author" class="form-control" value="<?= e($book['author']) ?>" required></div>
      <div class="col-md-3"><label class="form-label">Anno</label><input type="number" name="year" class="form-control" min="1" max="9999" value="<?= e($book['year']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Genere</label><input type="text" name="genre" class="form-control" value="<?= e($book['genre']) ?>"></div>
      <div class="col-md-3"><label class="form-label">ISBN</label><input type="text" name="isbn" class="form-control" value="<?= e($book['isbn']) ?>"></div>
      <div class="col-md-3">
                <label class="form-label">Stato</label>
        <?php $sel = old('status', $book['status'] ?? 'non_letto'); ?>
        <select class="form-select" name="status">
          <option value="non_letto"  <?= ($sel=='non_letto') ? 'selected' : '' ?>>Non letto</option>
          <option value="in_lettura" <?= ($sel=='in_lettura') ? 'selected' : '' ?>>In lettura</option>
          <option value="letto"      <?= ($sel=='letto') ? 'selected' : '' ?>>Letto</option>
        </select>
      </div>
      <div class="col-12"><label class="form-label">Descrizione</label><textarea name="description" class="form-control" rows="4"><?= e($book['description']) ?></textarea></div>
      <div class="col-md-6">
        <label class="form-label">Copertina</label>
        <input type="file" name="cover" id="coverInput" class="form-control" accept=".jpg,.jpeg,.png,.webp" value="<?= e(old('cover')) ?>">
        <?php if (!empty($book['cover_path'])): ?>
          <img src="<?= e($book['cover_path']) ?>" class="img-thumbnail mt-2" alt="Copertina attuale" style="max-width:180px; height:auto; object-fit:cover;">
        <?php endif; ?>
        <img id="coverPreview" class="img-thumbnail mt-2 d-none" alt="Anteprima nuova copertina" style="max-width:180px; max-height:240px; object-fit:cover;">
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
  var input = document.getElementById('coverInput');
  var img = document.getElementById('coverPreview');
  if(!input || !img) return;
  input.addEventListener('change', function(){
    const f = this.files && this.files[0];
    if (f) { img.src = URL.createObjectURL(f); img.classList.remove('d-none'); }
    else { img.src = ''; img.classList.add('d-none'); }
  });
});
</script>
<?php include __DIR__ . '/sezioni/footer.php'; ?>
