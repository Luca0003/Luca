<?php
require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
require_login();

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
    if (!in_array($status, ['non_letto','in_lettura','letto'], true)) { $status = 'non_letto'; }

    $errors = [];
    if ($title === '')  { $errors[] = 'Il titolo è obbligatorio.'; }
    if ($author === '') { $errors[] = 'L\'autore è obbligatorio.'; }
    if (!valid_year($year)) { $errors[] = 'Anno non valido.'; }

    // Upload copertina (opzionale)
    $coverPath = null;
    if (!empty($_FILES['cover']['name'])) {
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($allowed[$_FILES['cover']['type']])) {
            $errors[] = 'Formato copertina non supportato.';
        } elseif ($_FILES['cover']['size'] > 4 * 1024 * 1024) {
            $errors[] = 'La copertina supera i 4 MB.';
        } elseif (is_uploaded_file($_FILES['cover']['tmp_name'])) {
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

    if (empty($errors)) {
        $uid  = current_user()['id'];
        $stmt = $pdo->prepare('INSERT INTO books (user_id,title,author,year,genre,isbn,description,cover_path,status,views,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,0,NOW(),NOW())');
        $stmt->execute([$uid,$title,$author,$year?:null,$genre?:null,$isbn?:null,$desc?:null,$coverPath,$status]);
        flash('success','Libro aggiunto con successo.');
        redirect('books.php'); // <-- header() prima di qualunque output
    } else {
        flash('error', implode(' ', $errors));
        redirect('books-create.php');
    }
    exit; // importantissimo
}

// --- SOLO GET: renderizzo la pagina ---
include __DIR__ . '/sezioni/header.php';
?>
<h2 class="mb-3">Aggiungi libro</h2>
<form class="card shadow-sm" method="post" action="books-create.php" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Titolo <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Autore <span class="text-danger">*</span></label>
        <input type="text" name="author" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Anno</label>
        <input type="number" name="year" class="form-control" min="0" max="9999" placeholder="es. 2024">
      </div>
      <div class="col-md-3">
        <label class="form-label">Genere</label>
        <input type="text" name="genre" class="form-control" placeholder="Romanzo, Saggio, ...">
      </div>
      <div class="col-md-6">
        <label class="form-label">ISBN</label>
        <input type="text" name="isbn" class="form-control" placeholder="ISBN-10 o ISBN-13">
      </div>
      <div class="col-md-12">
        <label class="form-label">Descrizione</label>
        <textarea name="description" class="form-control" rows="4" placeholder="Note, trama, edizione..."></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label">Copertina (JPG, PNG, WEBP - max 4 MB)</label>
        <input type="file" name="cover" class="form-control" accept=".jpg,.jpeg,.png,.webp">
        <img id="coverPreview" class="img-thumbnail mt-2 d-none cover-preview" alt="Anteprima copertina">
      </div>
    </div>
  </div>
    <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Stato lettura</label>
      <select class="form-select" name="status">
        <option value="non_letto">Non letto</option>
        <option value="in_lettura">In lettura</option>
        <option value="letto">Letto</option>
      </select>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between">
    <a href="books.php" class="btn btn-outline-secondary">Annulla</a>
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
