<?php

    
    
session_start();

require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
include __DIR__ . '/sezioni/header.php';

$pdo = db();

// --- Parametri base ---
$q     = trim($_GET['q'] ?? '');
$sort  = $_GET['sort'] ?? 'created_at';
$order = (strtolower($_GET['order'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

// Nuovi parametri per i due menu a tendina
$filter_by   = trim($_GET['filter_by'] ?? '');
$filter_value = trim($_GET['filter_value'] ?? '');

$validSort = ['title','author','genre','status','year','created_at','views'];
if (!in_array($sort, $validSort, true)) { $sort = 'created_at'; }

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(1, min(100, (int)($_GET['perPage'] ?? 12)));
$offset  = ($page - 1) * $perPage;

// --- Base WHERE ---
$where  = '1=1';
$params = [];

// Ricerca testuale veloce
if ($q !== '') {
    $like = '%' . $q . '%';
    // placeholder diversi per evitare conflitti
    $where  = '(title LIKE :q1 OR author LIKE :q2 OR isbn LIKE :q3)';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}

// --- VISIBILITÀ PER UTENTE ---
$u = current_user();
$uid = $u['id'] ?? null;
if ($uid === null) {
    // Non loggato: catalogo vuoto
    $where = '0=1';
    $params = [];
} else {
    if ($q !== '') {
        $where = '(' . $where . ') AND user_id = :uid';
    } else {
        $where = 'user_id = :uid';
    }
    $params[':uid'] = $uid;
}

// Filtri via filter_by/filter_value
if ($filter_by !== '' && $filter_value !== '') {
    $allowedBy = ['title','author','genre','status','year'];
    if (in_array($filter_by, $allowedBy, true)) {
        if ($filter_by === 'year' && ctype_digit($filter_value)) {
            $where .= " AND year = :f_eq_year";
            $params[':f_eq_year'] = (int)$filter_value;
        } else {
            $field = $filter_by; // whitelisted
            $where .= " AND `$field` = :f_eq_val";
            $params[':f_eq_val'] = $filter_value;
        }
    }
}

// --- Opzioni DISTINCT per il secondo menù, per l'utente corrente ---
$opts_title = [];
$opts_author = [];
$opts_genre = [];
$opts_status = ['non_letto','in_lettura','letto'];
$opts_year = [];

if ($uid !== null) {
    try {
        $stmtOpt = $pdo->prepare("SELECT DISTINCT title FROM books WHERE user_id = :uid AND title <> '' ORDER BY title ASC");
        $stmtOpt->execute([':uid'=>$uid]);
        $opts_title = array_map(fn($r)=>$r['title'], $stmtOpt->fetchAll(PDO::FETCH_ASSOC));

        $stmtOpt = $pdo->prepare("SELECT DISTINCT author FROM books WHERE user_id = :uid AND author <> '' ORDER BY author ASC");
        $stmtOpt->execute([':uid'=>$uid]);
        $opts_author = array_map(fn($r)=>$r['author'], $stmtOpt->fetchAll(PDO::FETCH_ASSOC));

        $stmtOpt = $pdo->prepare("SELECT DISTINCT genre FROM books WHERE user_id = :uid AND genre IS NOT NULL AND genre <> '' ORDER BY genre ASC");
        $stmtOpt->execute([':uid'=>$uid]);
        $opts_genre = array_map(fn($r)=>$r['genre'], $stmtOpt->fetchAll(PDO::FETCH_ASSOC));

        // preferisci enum fisso ma usa quello presente a DB se differente
        $stmtOpt = $pdo->prepare("SELECT DISTINCT status FROM books WHERE user_id = :uid ORDER BY status ASC");
        $stmtOpt->execute([':uid'=>$uid]);
        $db_status = array_map(fn($r)=>$r['status'], $stmtOpt->fetchAll(PDO::FETCH_ASSOC));
        if (!empty($db_status)) { $opts_status = $db_status; }

        $stmtOpt = $pdo->prepare("SELECT DISTINCT year FROM books WHERE user_id = :uid AND year IS NOT NULL ORDER BY year ASC");
        $stmtOpt->execute([':uid'=>$uid]);
        $opts_year = array_map(fn($r)=>$r['year'], $stmtOpt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        // ignora
    }
}

$FILTER_OPTIONS = json_encode([
  'title' => $opts_title,
  'author'=> $opts_author,
  'genre' => $opts_genre,
  'status'=> $opts_status,
  'year'  => $opts_year,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// --- Conteggio totale ---
$sqlCount = "SELECT COUNT(*) FROM books WHERE $where";
$stmt = $pdo->prepare($sqlCount);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$total = (int)$stmt->fetchColumn();

// Select paginata
$perPageInt = (int)$perPage;
$offsetInt  = (int)$offset;

$sql = "SELECT * FROM books WHERE $where ORDER BY $sort $order LIMIT $perPageInt OFFSET $offsetInt";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = max(1, (int)ceil($total / $perPage));
?>
<div class="d-flex align-items-center mb-3">
  <h2 class="mb-0">Catalogo</h2>
  <span class="ms-2 badge text-bg-light border">Totale: <?= (int)$total ?></span>
</div>

<form class="row g-2 mb-3 align-items-end" method="get" id="toolbarForm">
  <div class="col-lg-4">
    <input type="text" class="form-control" name="q" placeholder="Cerca titolo, autore, ISBN…" value="<?= e($q) ?>">
  </div>
  <div class="col-lg-3">
    <label class="form-label mb-1">Filtra per</label>
    <select class="form-select" name="filter_by" id="filter_by">
      <?php $byOpts = ['title'=>'Titolo','author'=>'Autore','genre'=>'Genere','status'=>'Stato','year'=>'Anno']; ?>
      <?php foreach ($byOpts as $k => $label): ?>
        <option value="<?= $k ?>" <?= ($k === $filter_by) ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-lg-3">
    <label class="form-label mb-1">Valore</label>
    <select class="form-select" name="filter_value" id="filter_value">
      <!-- opzioni generate via JS -->
    </select>
  </div>
  <div class="col-lg-2 d-grid">
    <button class="btn btn-primary" type="submit">Riordina</button>
  </div>
</form>

<div class="table-responsive bg-white rounded-3 shadow-sm">
  <table class="table align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Copertina</th>
        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'title','order'=>$sort==='title' && ($order==='ASC')?'desc':'asc','page'=>1])) ?>" class="text-decoration-none">Titolo</a></th>
        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'author','order'=>$sort==='author' && ($order==='ASC')?'desc':'asc','page'=>1])) ?>" class="text-decoration-none">Autore</a></th>
        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'genre','order'=>$sort==='genre' && ($order==='ASC')?'desc':'asc','page'=>1])) ?>" class="text-decoration-none">Genere</a></th>
        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'year','order'=>$sort==='year' && ($order==='ASC')?'desc':'asc','page'=>1])) ?>" class="text-decoration-none">Anno</a></th>
        <th>ISBN</th>
        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'status','order'=>$sort==='status' && ($order==='ASC')?'desc':'asc','page'=>1])) ?>" class="text-decoration-none">Stato</a></th>
        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'views','order'=>$sort==='views' && ($order==='ASC')?'desc':'asc','page'=>1])) ?>" class="text-decoration-none">Visualizzazioni</a></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
      <tr>
        <td class="cover-cell">
          <?php if (!empty($r['cover_path'])): ?>
            <img src="<?= e($r['cover_path']) ?>" class="img-thumbnail cover-thumb" alt="copertina">
          <?php endif; ?>
        </td>
        <td><?= e($r['title']) ?></td>
        <td><?= e($r['author']) ?></td>
        <td><?= e($r['genre']) ?></td>
        <td><?= e($r['year']) ?></td>
        <td><?= e($r['isbn']) ?></td>
        <td style="width:160px">
<select class="form-select form-select-sm status-select" data-id="<?= (int)$r['id'] ?>" data-csrf="<?= e(csrf_token()) ?>">
  <?php $st = $r['status'] ?? 'non_letto'; ?>
  <option value="non_letto"  <?= $st==='non_letto'?'selected':'' ?>>Non letto</option>
  <option value="in_lettura" <?= $st==='in_lettura'?'selected':'' ?>>In lettura</option>
  <option value="letto"      <?= $st==='letto'?'selected':'' ?>>Letto</option>
</select>
<div class="small text-muted mt-1 status-hint d-none">Salvato ✓</div>
        </td>
        <td><?= (int)($r['views'] ?? 0) ?></td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-primary" href="book.php?id=<?= (int)$r['id'] ?>">Apri</a>
          <a class="btn btn-sm btn-outline-secondary" href="books-edit.php?id=<?= (int)$r['id'] ?>">Modifica</a>
          <form action="books-delete.php" method="post" class="d-inline" onsubmit="return confirm('Eliminare definitivamente questo libro?');">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit">Elimina</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="text-center text-muted py-4">Nessun libro trovato.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<nav aria-label="Paginazione" class="mt-3">
  <ul class="pagination">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
      <?php $qstr = $_GET; $qstr['page'] = $i; ?>
      <li class="page-item <?= $i===$page?'active':'' ?>">
        <a class="page-link" href="?<?= http_build_query($qstr) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<script>
  // Opzioni per il secondo menu, provenienti dal server
  window.FILTER_OPTIONS = JSON.parse(<?= json_encode($FILTER_OPTIONS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
  (function(){
    const bySel  = document.getElementById('filter_by');
    const valSel = document.getElementById('filter_value');
    function rebuildValues(){
      const key = bySel.value || 'title';
      const items = (window.FILTER_OPTIONS && window.FILTER_OPTIONS[key]) ? window.FILTER_OPTIONS[key] : [];
      while(valSel.firstChild){ valSel.removeChild(valSel.firstChild); }
      const ph = document.createElement('option');
      ph.value = ''; ph.textContent = items.length ? '— Scegli —' : '— Nessun valore —';
      valSel.appendChild(ph);
      items.forEach(v => {
        const opt = document.createElement('option');
        opt.value = v;
        opt.textContent = v;
        valSel.appendChild(opt);
      });
      // Preseleziona eventuale valore corrente
      const currentVal = "<?= e($filter_value) ?>";
      if (currentVal) {
        Array.from(valSel.options).forEach(o => { if (o.value === currentVal) o.selected = true; });
      }
    }
    if (bySel && valSel) {
      bySel.addEventListener('change', rebuildValues);
      rebuildValues();
    }
  })();
</script>

<?php include __DIR__ . '/sezioni/footer.php'; ?>
