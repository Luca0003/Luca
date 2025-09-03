<?php

    
    
session_start();

require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
include __DIR__ . '/sezioni/header.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $pdo = db();
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
        login_user((int)$u['id']);
        flash('success','Accesso effettuato.');
        $ref = $_GET['ref'] ?? 'index.php';
        redirect($ref);
    } else { flash('error','Credenziali non valide.'); redirect('login.php'); }
}
?>
<h2 class="mb-3">Accedi</h2>
<form class="card shadow-sm" method="post" action="login.php">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required value="<?= e(old('email')) ?>">
      <div class="form-text" id="login_email_hint"></div></div>
      <div class="col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required>
      <div class="form-text" id="login_pw_hint"></div></div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between">
    <a href="register.php" class="btn btn-link">Nuovo qui? Registrati</a>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-right-to-bracket me-1"></i> Accedi</button>
  </div>

<script>
(function(){
  const emailInput = document.querySelector('input[name="email"]');
  const passInput  = document.querySelector('input[name="password"]');
  const emailHint  = document.getElementById('login_email_hint');
  const pwHint     = document.getElementById('login_pw_hint');

  let t=null;
  function check(){
    const e = (emailInput && emailInput.value || '').trim();
    const p = (passInput && passInput.value || '');
    // Messaggi base
    if (!e) { emailHint.textContent = 'Inserisci la tua email.'; return; }
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(e)) { emailHint.textContent='Formato email non valido.'; return; }
    emailHint.textContent = '';

    if (!p) { pwHint.textContent = 'Inserisci la password.'; return; }
    pwHint.textContent = 'Verifico credenziali…';

    fetch('login_check.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'email=' + encodeURIComponent(e) + '&password=' + encodeURIComponent(p)
    })
    .then(r => r.json())
    .then(j => {
      if (j.ok === false) { pwHint.textContent = 'Impossibile verificare ora.'; return; }
      if (j.valid) {
        pwHint.textContent = 'Credenziali corrette ✅ (puoi inviare)';
      } else {
        pwHint.textContent = 'Email o password non corretti ❌';
      }
    })
    .catch(() => { pwHint.textContent = 'Impossibile verificare ora.'; });
  }

  function schedule(){ clearTimeout(t); t = setTimeout(check, 300); }
  emailInput && emailInput.addEventListener('input', schedule);
  passInput && passInput.addEventListener('input', schedule);
})();
</script>

</form>
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
