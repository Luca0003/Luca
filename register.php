<?php

    
    
session_start();

require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
include __DIR__ . '/sezioni/header.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $pdo = db();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    
$errors = [];
// Validazione username: 3-32 caratteri, lettere/numeri . _ -
if ($name === '' || !preg_match('/^[A-Za-z0-9._-]{3,32}$/', $name)) {
    $errors[] = 'Username non valido (3-32 caratteri: lettere/numeri . _ -).';
}

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email non valida.';
    if (strlen($pass) !== 12) $errors[] = 'La password deve avere esattamente 12 caratteri'; if (!preg_match('/[A-Z]/', $pass)) $errors[] = 'Serve almeno una lettera maiuscola.'; if (!preg_match('/[a-z]/', $pass)) $errors[] = 'Serve almeno una lettera minuscola.'; if (!preg_match('/[0-9]/', $pass)) $errors[] = 'Serve almeno una cifra.'; if (!preg_match('/[^A-Za-z0-9]/', $pass)) $errors[] = 'Serve almeno un simbolo.';
    if ($pass !== $pass2) $errors[] = 'Le password non coincidono.';
    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $stmt->execute([$email]);
        if ($stmt->fetch()) { $errors[] = 'Email già registrata.'; }
        // Verifica univocità username
        $stmt = $pdo->prepare('SELECT id FROM users WHERE name = ?'); $stmt->execute([$name]);
        if (!$errors && $stmt->fetch()) { $errors[] = 'Username già in uso. Scegline un altro.'; }
        else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,created_at) VALUES (?,?,?,NOW())');
            $stmt->execute([$name?:null,$email,$hash]);
            $uid = (int)$pdo->lastInsertId();
            login_user($uid);
            flash('success','Registrazione completata. Benvenuto!'); redirect('index.php');
        }
    }
    if (!empty($errors)) { flash('error', implode(' ', $errors)); redirect('register.php'); }
}
?>
<h2 class="mb-3">Registrati</h2>
<form class="card shadow-sm" method="post" action="register.php" novalidate>
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Username</label><input type="text" name="name" required class="form-control" placeholder="Es. Mario" value="<?= e(old('name')) ?>">
          <div class="form-text" id="uname_hint">Lo username deve essere unico. Controllo…</div></div>
      <div class="col-md-6"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required value="<?= e(old('email')) ?>">
          <div class="form-text" id="email_hint">L\'email deve essere unica. Controllo…</div></div>
      <div class="col-md-6"><label class="form-label">Password <span class="text-danger">*</span></label><input type="password" name="password" class="form-control" required minlength="12" maxlength="12" pattern=".{12}">
          <div class="form-text" id="pw_hint">
            Requisiti: minimo 12 caratteri, almeno 1 maiuscola, 1 minuscola, 1 cifra, 1 simbolo.
          </div></div>
      <div class="col-md-6"><label class="form-label">Conferma Password <span class="text-danger">*</span></label><input type="password" name="password2" class="form-control" required minlength="12" maxlength="12" pattern=".{12}">
          <div class="form-text" id="pw2_hint"></div></div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between">
    <a href="login.php" class="btn btn-link">Hai già un account? Accedi</a>
    <button class="btn btn-primary" type="submit"><i class="fa-regular fa-user me-1"></i> Crea account</button>
  </div>







<script>
(function(){
  const nameInput   = document.querySelector('input[name="name"]');
  const emailInput  = document.querySelector('input[name="email"]');
  const passInput   = document.querySelector('input[name="password"]');
  const pass2Input  = document.querySelector('input[name="password2"]');
  const unameHint   = document.getElementById('uname_hint');
  const emailHint   = document.getElementById('email_hint');
  const pwHint      = document.getElementById('pw_hint');
  const pw2Hint     = document.getElementById('pw2_hint');

  let t1=null, t2=null, t3=null, t4=null;
  const reUser = /^[A-Za-z0-9._\-\\]{3,32}$/;
  const rules = [
    { re: /^.{12}$/,       text: 'Esattamente 12 caratteri' },
    { re: /[A-Z]/,         text: 'Almeno una maiuscola' },
    { re: /[a-z]/,         text: 'Almeno una minuscola' },
    { re: /[0-9]/,         text: 'Almeno una cifra' },
    { re: /[^A-Za-z0-9]/,  text: 'Almeno un simbolo' }
  ];

  function renderPw(msgsOk, msgsKo){
    if (!pwHint) return;
    const ok  = msgsOk.map(m => '✅ ' + m).join(' · ');
    const bad = msgsKo.map(m => '❌ ' + m).join(' · ');
    pwHint.textContent = [bad, ok].filter(Boolean).join('   ');
  }

  function checkPw(){
    if (!passInput) return;
    const v = passInput.value || '';
    const ok=[], ko=[];
    rules.forEach(r => (r.re.test(v) ? ok : ko).push(r.text));
    renderPw(ok, ko);
    // Re-check match when password changes
    checkPw2();
  }

  function checkPw2(){
    if (!passInput || !pass2Input || !pw2Hint) return;
    const p1 = passInput.value || '';
    const p2 = pass2Input.value || '';
    if (!p2) {
      pw2Hint.textContent = 'Conferma la password.';
      pass2Input.classList.remove('is-valid','is-invalid');
      return;
    }
    if (p1 === p2) {
      pw2Hint.textContent = 'Le password coincidono ✅';
      pass2Input.classList.remove('is-invalid'); pass2Input.classList.add('is-valid');
    } else {
      pw2Hint.textContent = 'Le password non coincidono ❌';
      pass2Input.classList.remove('is-valid'); pass2Input.classList.add('is-invalid');
    }
  }

  function checkUser(){
    if (!nameInput || !unameHint) return;
    const u = (nameInput.value||'').trim();
    if (!u) { unameHint.textContent='Inserisci uno username.'; nameInput.classList.remove('is-valid','is-invalid'); return; }
    if (!reUser.test(u)) {
      unameHint.textContent='Formato non valido (3-32: lettere/numeri . _ - \\).';
      nameInput.classList.remove('is-valid'); nameInput.classList.add('is-invalid');
      return;
    }
    unameHint.textContent='Controllo disponibilità...';
    fetch('username_available.php?u=' + encodeURIComponent(u), {cache:'no-store'})
      .then(r=>r.json()).then(j=>{
        if (j.ok===false) { unameHint.textContent='Impossibile verificare ora.'; nameInput.classList.add('is-invalid'); return; }
        if (j.valid===false) { unameHint.textContent='Formato non valido.'; nameInput.classList.add('is-invalid'); return; }
        if (j.available) { unameHint.textContent='Disponibile ✅'; nameInput.classList.remove('is-invalid'); nameInput.classList.add('is-valid'); }
        else { unameHint.textContent='Username già in uso ❌'; nameInput.classList.remove('is-valid'); nameInput.classList.add('is-invalid'); }
      }).catch(()=>{ unameHint.textContent='Impossibile verificare ora.'; nameInput.classList.add('is-invalid'); });
  }

  function checkEmail(){
    if (!emailInput || !emailHint) return;
    const e = (emailInput.value||'').trim();
    if (!e) { emailHint.textContent='Inserisci una email.'; emailInput.classList.remove('is-valid','is-invalid'); return; }
    emailHint.textContent='Controllo disponibilità...';
    fetch('email_available.php?e=' + encodeURIComponent(e), {cache:'no-store'})
      .then(r=>r.json()).then(j=>{
        if (j.ok===false) { emailHint.textContent='Impossibile verificare ora.'; emailInput.classList.add('is-invalid'); return; }
        if (j.valid===false) { emailHint.textContent='Email non valida.'; emailInput.classList.add('is-invalid'); return; }
        if (j.available) { emailHint.textContent='Disponibile ✅'; emailInput.classList.remove('is-invalid'); emailInput.classList.add('is-valid'); }
        else { emailHint.textContent='Email già registrata ❌'; emailInput.classList.remove('is-valid'); emailInput.classList.add('is-invalid'); }
      }).catch(()=>{ emailHint.textContent='Impossibile verificare ora.'; emailInput.classList.add('is-invalid'); });
  }

  // Debounced listeners
  nameInput  && nameInput.addEventListener('input', function(){ clearTimeout(t1); t1=setTimeout(checkUser, 250); });
  emailInput && emailInput.addEventListener('input', function(){ clearTimeout(t2); t2=setTimeout(checkEmail, 250); });
  passInput  && passInput.addEventListener('input', function(){ clearTimeout(t3); t3=setTimeout(checkPw, 150); });
  pass2Input && pass2Input.addEventListener('input', function(){ clearTimeout(t4); t4=setTimeout(checkPw2, 150); });

  document.addEventListener('DOMContentLoaded', function(){ checkUser(); checkEmail(); checkPw(); checkPw2(); });
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
