
<?php
require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/sezioni/util.php';
require_login();
include __DIR__ . '/sezioni/header.php';

$pdo = db();
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $new2 = $_POST['new_password2'] ?? '';

    if ($new !== $new2) { $errors[] = 'Le nuove password non coincidono.'; }

    // Politica password: min 8 + maiuscola, minuscola, cifra, simbolo
    if (strlen($new) < 8) $errors[] = 'La password deve avere almeno 8 caratteri.';
    if (!preg_match('/[A-Z]/', $new)) $errors[] = 'Serve almeno una lettera maiuscola.';
    if (!preg_match('/[a-z]/', $new)) $errors[] = 'Serve almeno una lettera minuscola.';
    if (!preg_match('/[0-9]/', $new)) $errors[] = 'Serve almeno una cifra.';
    if (!preg_match('/[^A-Za-z0-9]/', $new)) $errors[] = 'Serve almeno un simbolo.';

    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE id = ?');
    $stmt->execute([current_user()['id']]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($current, $u['password_hash'])) {
        $errors[] = 'La password attuale non è corretta.';
    }

    if (!$errors) {
        if (password_verify($new, $u['password_hash'])) {
            $errors[] = 'La nuova password non può essere uguale alla precedente.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $u['id']]);
            $success = 'Password aggiornata con successo.';
        }
    }
}
?>

<div class="container my-4">
  <h2 class="mb-3"><i class="fa-solid fa-key me-2"></i>Cambia password</h2>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <form method="post" class="card">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Password attuale</label>
          <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Nuova password</label>
          <input type="password" name="new_password" class="form-control" required>
          <div class="form-text">Min 8, maiuscola, minuscola, numero, simbolo.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Conferma nuova password</label>
          <input type="password" name="new_password2" class="form-control" required>
        </div>
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-rotate me-1"></i> Aggiorna</button>
    </div>
  </form>
</div>

<?php include __DIR__ . '/sezioni/footer.php'; ?>

<!-- toggle-mostra-password: start -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('input[type="password"]').forEach(function(pw){
    if (pw.dataset.toggleAdded === "1") return;
    pw.dataset.toggleAdded = "1";
    var wrap = document.createElement('div');
    wrap.style.marginTop = '6px';
    var label = document.createElement('label');
    label.style.fontSize = '0.9em';
    var cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.style.marginRight = '6px';
    cb.addEventListener('change', function(){ pw.type = this.checked ? 'text' : 'password'; });
    label.appendChild(cb);
    label.appendChild(document.createTextNode(' Mostra password'));
    wrap.appendChild(label);
    pw.insertAdjacentElement('afterend', wrap);
  });
});
</script>
<!-- toggle-mostra-password: end -->
