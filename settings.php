
<?php

    
    
session_start();

require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/sezioni/util.php';
require_login();
include __DIR__ . '/sezioni/header.php';

$pdo = db();
$me = current_user();
$errors = [];
$success = null;

function username_valid(string $u): bool {
    return (bool) preg_match('/^[A-Za-z0-9._\-\\\\]{3,32}$/', $u);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'change_username') {
        $new = trim($_POST['new_username'] ?? '');
        if (!username_valid($new)) { $errors[] = 'Username non valido (3-32: lettere/numeri . _ - \\).'; }
        if (!$errors) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE name = ? AND id <> ? LIMIT 1');
            $stmt->execute([$new, $me['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'Username già in uso.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
                $stmt->execute([$new, $me['id']]);
                $_SESSION['flash']['success'] = 'Username aggiornato.';
                header('Location: settings.php'); exit;
            }
        }
    }

    if ($action === 'change_email') {
        $new = trim($_POST['new_email'] ?? '');
        if (!filter_var($new, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email non valida.'; }
        if (!$errors) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $stmt->execute([$new, $me['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'Email già registrata.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
                $stmt->execute([$new, $me['id']]);
                $_SESSION['flash']['success'] = 'Email aggiornata.';
                header('Location: settings.php'); exit;
            }
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password2'] ?? '';

        if ($new !== $new2) $errors[] = 'Le nuove password non coincidono.';
        if (strlen($new) < 8) $errors[] = 'La password deve avere almeno 8 caratteri.';
        if (!preg_match('/[A-Z]/', $new)) $errors[] = 'Serve almeno una maiuscola.';
        if (!preg_match('/[a-z]/', $new)) $errors[] = 'Serve almeno una minuscola.';
        if (!preg_match('/[0-9]/', $new)) $errors[] = 'Serve almeno una cifra.';
        if (!preg_match('/[^A-Za-z0-9]/', $new)) $errors[] = 'Serve almeno un simbolo.';

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$me['id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            $errors[] = 'La password attuale non è corretta.';
        }

        if (!$errors) {
            if (password_verify($new, $row['password_hash'])) {
                $errors[] = 'La nuova password non può essere uguale alla precedente.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([$hash, $me['id']]);
                $_SESSION['flash']['success'] = 'Password aggiornata con successo.';
                header('Location: settings.php'); exit;
            }
        }
    }
}
?>

<div class="container my-4">
  <div class="d-flex align-items-center mb-3">
    <i class="fa-solid fa-gear me-2"></i><h2 class="mb-0">Impostazioni</h2>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header"><i class="fa-regular fa-user me-1"></i> Cambia username</div>
        <div class="card-body">
          <form method="post" class="row g-3" id="form-username">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="change_username">
            <div>
              <label class="form-label">Nuovo username</label>
              <input type="text" name="new_username" class="form-control" value="<?= htmlspecialchars($me['name'] ?? '') ?>" required>
              <div class="form-text" id="uname_hint_settings">Lo username deve essere unico. Controllo…</div>
            </div>
            <div class="d-flex justify-content-end">
              <button class="btn btn-primary" type="submit"><i class="fa-regular fa-floppy-disk me-1"></i> Salva</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-header"><i class="fa-regular fa-envelope me-1"></i> Cambia email</div>
        <div class="card-body">
          <form method="post" class="row g-3" id="form-email">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="change_email">
            <div>
              <label class="form-label">Nuova email</label>
              <input type="email" name="new_email" class="form-control" value="<?= htmlspecialchars($me['email'] ?? '') ?>" required>
              <div class="form-text" id="email_hint_settings">L'email deve essere unica. Controllo…</div>
            </div>
            <div class="d-flex justify-content-end">
              <button class="btn btn-primary" type="submit"><i class="fa-regular fa-floppy-disk me-1"></i> Salva</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-12">
      <div class="card">
        <div class="card-header"><i class="fa-solid fa-key me-1"></i> Cambia password</div>
        <div class="card-body">
          <form method="post" class="row g-3" id="form-password">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="col-md-4">
              <label class="form-label">Password attuale</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Nuova password</label>
              <input type="password" name="new_password" class="form-control" required>
              <div class="form-text" id="pw_hint_settings">Min 8 + maiuscola, minuscola, cifra, simbolo.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Conferma nuova password</label>
              <input type="password" name="new_password2" class="form-control" required>
              <div class="form-text" id="pw2_hint_settings"></div>
            </div>
            <div class="d-flex justify-content-end">
              <button class="btn btn-primary" type="submit"><i class="fa-regular fa-floppy-disk me-1"></i> Salva</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // Username live check
  const uInput = document.querySelector('#form-username input[name="new_username"]');
  const uHint  = document.getElementById('uname_hint_settings');
  const reUser = /^[A-Za-z0-9._\-\\]{3,32}$/;
  let t1=null;
  function checkU(){
    const v = (uInput.value||'').trim();
    if (!v) { uHint.textContent='Inserisci uno username.'; return; }
    if (!reUser.test(v)) { uHint.textContent='Formato non valido (3-32: lettere/numeri . _ - \\).'; return; }
    uHint.textContent='Controllo disponibilità...';
    fetch('username_available.php?u=' + encodeURIComponent(v), {cache:'no-store'})
      .then(r=>r.json()).then(j=>{
        if (j.ok===false) { uHint.textContent='Impossibile verificare ora.'; return; }
        if (j.valid===false) { uHint.textContent='Formato non valido.'; return; }
        if (j.available) { uHint.textContent='Disponibile ✅'; } else { uHint.textContent='Username già in uso ❌'; }
      }).catch(()=> uHint.textContent='Impossibile verificare ora.');
  }
  uInput && uInput.addEventListener('input', function(){ clearTimeout(t1); t1=setTimeout(checkU, 250); });
  document.addEventListener('DOMContentLoaded', checkU);

  // Email live check
  const eInput = document.querySelector('#form-email input[name="new_email"]');
  const eHint  = document.getElementById('email_hint_settings');
  let t2=null;
  function checkE(){
    const v = (eInput.value||'').trim();
    if (!v) { eHint.textContent='Inserisci una email.'; return; }
    eHint.textContent='Controllo disponibilità...';
    fetch('email_available.php?e=' + encodeURIComponent(v), {cache:'no-store'})
      .then(r=>r.json()).then(j=>{
        if (j.ok===false) { eHint.textContent='Impossibile verificare ora.'; return; }
        if (j.valid===false) { eHint.textContent='Email non valida.'; return; }
        if (j.available) { eHint.textContent='Disponibile ✅'; } else { eHint.textContent='Email già registrata ❌'; }
      }).catch(()=> eHint.textContent='Impossibile verificare ora.');
  }
  eInput && eInput.addEventListener('input', function(){ clearTimeout(t2); t2=setTimeout(checkE, 250); });
  document.addEventListener('DOMContentLoaded', checkE);

  // Password strength + match
  const p1 = document.querySelector('#form-password input[name="new_password"]');
  const p2 = document.querySelector('#form-password input[name="new_password2"]');
  const pHint = document.getElementById('pw_hint_settings');
  const p2Hint = document.getElementById('pw2_hint_settings');
  let t3=null, t4=null;
  const reqs = [
    {re:/^.{8,}$/, text:'Min 8'},
    {re:/[A-Z]/,   text:'1 maiuscola'},
    {re:/[a-z]/,   text:'1 minuscola'},
    {re:/[0-9]/,   text:'1 cifra'},
    {re:/[^A-Za-z0-9]/, text:'1 simbolo'}
  ];
  function renderStrength(){
    const v = p1.value || '';
    const ok=[], ko=[];
    reqs.forEach(r => (r.re.test(v) ? ok : ko).push(r.text));
    pHint.textContent = (ko.length?'❌ '+ko.join(' · ')+'   ':'') + (ok.length?'✅ '+ok.join(' · '):'');
  }
  function renderMatch(){
    if (!p2.value) { p2Hint.textContent='Conferma la password.'; p2.classList.remove('is-valid','is-invalid'); return; }
    if (p1.value === p2.value) { p2Hint.textContent='Le password coincidono ✅'; p2.classList.add('is-valid'); p2.classList.remove('is-invalid'); }
    else { p2Hint.textContent='Le password non coincidono ❌'; p2.classList.add('is-invalid'); p2.classList.remove('is-valid'); }
  }
  p1 && p1.addEventListener('input', function(){ clearTimeout(t3); t3=setTimeout(function(){ renderStrength(); renderMatch(); }, 150); });
  p2 && p2.addEventListener('input', function(){ clearTimeout(t4); t4=setTimeout(renderMatch, 150); });
  document.addEventListener('DOMContentLoaded', function(){ renderStrength(); renderMatch(); });
})();
</script>

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
