<?php

    
    
session_start();

require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
require_login();

include __DIR__ . '/sezioni/header.php';

// --- POST PRIMA DI QUALSIASI OUTPUT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $pdo    = db();
    $title  = trim($_POST['title']  ?? '');
    $author = trim($_POST['author'] ?? '');
    $year   = trim($_POST['year']   ?? '');
    $genre  = trim($_POST['genre']  ?? '');
    $isbn   = trim($_POST['isbn']   ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'non_letto';
    $allowedStatus = ['non_letto','in_lettura','letto'];
    if (!in_array($status, $allowedStatus, true)) { $status = 'non_letto'; }

    $errors = [];
    if ($title === '')  { $errors[] = 'Il titolo è obbligatorio.'; }
    if ($author === '') { $errors[] = "L'autore è obbligatorio."; }
    if ($year !== '' && !valid_year($year)) { $errors[] = 'Anno non valido.'; }

    // Upload copertina (opzionale)
    $coverPath = null;
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
        redirect('books-create.php');
        exit;
    }

    $uid = current_user()['id'] ?? null;

    // Leggi colonne effettive della tabella
    $colsPresent = [];
    try {
        $res = $pdo->query("SHOW COLUMNS FROM books");
        foreach ($res as $row) { $colsPresent[] = $row['Field']; }
    } catch (Throwable $e) {
        $colsPresent = ['user_id','title','author','year','genre','isbn','description','cover_path','views','created_at','updated_at'];
    }

    $cols = [];
    $vals = [];
    $bind = [];

    // aggiunge colonna con placeholder + bind
    $addVal = function(string $col, $value) use (&$cols,&$vals,&$bind) {
        $cols[] = $col;
        $ph = ':' . $col;
        $vals[] = $ph;
        $bind[$ph] = $value;
    };
    // aggiunge colonna con espressione SQL letterale (es. 0, NOW())
    $addExpr = function(string $col, string $expr) use (&$cols,&$vals) {
        $cols[] = $col;
        $vals[] = $expr;
    };

    if (in_array('user_id', $colsPresent, true))     $addVal('user_id', $uid);
    if (in_array('title', $colsPresent, true))       $addVal('title', $title);
    if (in_array('author', $colsPresent, true))      $addVal('author', $author);
    if (in_array('year', $colsPresent, true))        $addVal('year', ($year !== '' ? (int)$year : null));
    if (in_array('genre', $colsPresent, true))       $addVal('genre', ($genre !== '' ? $genre : null));
    if (in_array('isbn', $colsPresent, true))        $addVal('isbn', ($isbn !== '' ? $isbn : null));
    if (in_array('description', $colsPresent, true)) $addVal('description', ($desc !== '' ? $desc : null));
    if (in_array('cover_path', $colsPresent, true))  $addVal('cover_path', ($coverPath !== null ? $coverPath : null));
    if (in_array('status', $colsPresent, true))      $addVal('status', $status);
    if (in_array('views', $colsPresent, true))       $addExpr('views', '0');
    if (in_array('created_at', $colsPresent, true))  $addExpr('created_at', 'NOW()');
    if (in_array('updated_at', $colsPresent, true))  $addExpr('updated_at', 'NOW()');

    if (empty($cols)) {
        flash('error','Schema tabella non riconosciuto (nessuna colonna valida per INSERT).');
        redirect('books-create.php');
        exit;
    }

    // Backtick ai nomi colonna e costruzione SQL
    $colsQuoted = array_map(fn($c)=>'`'.$c.'`', $cols);
    $sql = "INSERT INTO `books` (" . implode(',', $colsQuoted) . ") VALUES (" . implode(',', $vals) . ")";
    $stmt = $pdo->prepare($sql);

    foreach ($bind as $k => $v) {
        if ($v === null) { $stmt->bindValue($k, null, PDO::PARAM_NULL); }
        elseif (is_int($v)) { $stmt->bindValue($k, $v, PDO::PARAM_INT); }
        else { $stmt->bindValue($k, $v, PDO::PARAM_STR); }
    }
    $stmt->execute();

    flash('success','Libro aggiunto con successo.');
    redirect('books.php');
    exit;
}
?>

<h2 class="mb-3">Nuovo libro</h2>
<form class="card shadow-sm" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Titolo <span class="text-danger">*</span></label><input type="text" name="title" class="form-control" required value="<?= e(old('title')) ?>"></div>
      <div class="col-md-6"><label class="form-label">Autore <span class="text-danger">*</span></label><input type="text" name="author" class="form-control" required value="<?= e(old('author')) ?>"></div>
      <div class="col-md-3"><label class="form-label">Anno</label><input type="number" name="year" class="form-control" min="1" max="9999" value="<?= e(old('year')) ?>"></div>
      <div class="col-md-3"><label class="form-label">Genere</label><input type="text" name="genre" class="form-control" value="<?= e(old('genre')) ?>"></div>
      <div class="col-md-3"><label class="form-label">ISBN</label><input type="text" name="isbn" class="form-control" value="<?= e(old('isbn')) ?>"></div>
      <div class="col-md-3">
        <label class="form-label">Stato</label>
        <select class="form-select" name="status">
          <option value="non_letto" <?= old_selected('status', 'non_letto') ?>>Non letto</option>
          <option value="in_lettura" <?= old_selected('status', 'in_lettura') ?>>In lettura</option>
          <option value="letto" <?= old_selected('status', 'letto') ?>>Letto</option>
        </select>
      </div>
      <div class="col-12"><label class="form-label">Descrizione</label><textarea name="description" class="form-control" rows="4"><?= e(old('description')) ?></textarea></div>
      <div class="col-md-6"><label class="form-label">Copertina</label><input type="file" name="cover" class="form-control" accept=".jpg,.jpeg,.png,.webp" id="coverInput" value="<?= e(old('cover')) ?>">
        <img id="coverPreview" class="img-thumbnail mt-2 d-none" alt="Anteprima copertina" style="max-width:180px; max-height:240px; object-fit:cover;"></div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between">
    <a href="books.php" class="btn btn-outline-secondary">Annulla</a>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Salva</button>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var input = document.querySelector('#coverInput, input[name="cover"]');
  var img = document.getElementById('coverPreview');
  if(!input || !img) return;
  input.addEventListener('change', function(){
    const f = this.files && this.files[0];
    if (f) {
      img.src = URL.createObjectURL(f);
      img.classList.remove('d-none');
    } else {
      img.src = '';
      img.classList.add('d-none');
    }
  });
});
</script>

</form>
<?php include __DIR__ . '/sezioni/footer.php'; ?>
