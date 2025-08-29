<?php
require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
include __DIR__ . '/sezioni/header.php';

$pdo = db();

$q     = trim($_GET['q'] ?? '');
$sort  = $_GET['sort'] ?? 'created_at';
$order = (strtolower($_GET['order'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

$validSort = ['title','author','year','created_at','views'];
if (!in_array($sort, $validSort, true)) { $sort = 'created_at'; }

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where  = '1=1';
$params = [];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    // NOTA: placeholder diversi per evitare HY093
    $where  = '(title LIKE :q1 OR author LIKE :q2 OR isbn LIKE :q3)';
    $params = [
        ':q1' => $like,
        ':q2' => $like,
        ':q3' => $like,
    ];
}

// Conteggio totale

// --- VISIBILITÀ PER UTENTE ---
$u = current_user();
$uid = $u['id'] ?? null;
if ($uid === null) {
    // Non loggato: catalogo pubblico ma vuoto
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
// --- FINE REGOLA ---

$sqlCount = "SELECT COUNT(*) AS c FROM books WHERE $where";
$stmt = $pdo->prepare($sqlCount);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->execute();
$total = (int)$stmt->fetchColumn();

// IMPORTANTISSIMO: niente placeholder in LIMIT/OFFSET con prepare nativo
$perPageInt = (int)$perPage;
$offsetInt  = (int)$offset;

$sql = "SELECT * FROM books
        WHERE $where
        ORDER BY $sort $order
        LIMIT $perPageInt OFFSET $offsetInt";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->execute();

$rows = $stmt->fetchAll();
$totalPages = max(1, (int)ceil($total / $perPage));
?>
<div class="d-flex align-items-center mb-3">
  <h2 class="mb-0">Catalogo</h2>
  <span class="ms-2 badge text-bg-light border"><?= $total ?> totali</span>
  <div class="ms-auto">
    <a href="books-create.php" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Nuovo</a>
  </div>
</div>

<form class="row g-2 mb-3" method="get">
  <div class="col-md-6">
    <input type="text" class="form-control" name="q" placeholder="Cerca titolo, autore, ISBN…" value="<?= e($q) ?>">
  </div>
  <div class="col-md-3">
    <select class="form-select" name="sort">
      <?php $opts = ['created_at'=>'Data inserimento','title'=>'Titolo','author'=>'Autore','year'=>'Anno','views'=>'Visualizzazioni']; ?>
      <?php foreach ($opts as $k=>$label): ?>
        <option value="<?= $k ?>" <?= $k===$sort?'selected':'' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-2">
    <select class="form-select" name="order">
      <option value="desc" <?= $order==='DESC'?'selected':'' ?>>Discendente</option>
      <option value="asc"  <?= $order==='ASC'?'selected':''  ?>>Ascendente</option>
    </select>
  </div>
  <div class="col-md-1 d-grid">
    <button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-filter"></i></button>
  </div>
</form>

<div class="table-responsive bg-white rounded-3 shadow-sm">
  <table class="table align-middle mb-0">
    <thead class="table-light">
      <tr data-book="<?= (int)$r['id'] ?>">
        <th>Copertina</th>
        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'title','order'=>$order==='ASC'?'desc':'asc','page'=>1])) ?>" class="text-decoration-none">Titolo</a></th>
        <th><a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'author','order'=>$order==='ASC'?'desc':'asc','page'=>1])) ?>" class="text-decoration-none">Autore</a></th>
        <th>Genere</th>
        <th>Stato</th>
        <th>Anno</th>
        <th>ISBN</th>
        <th class="text-end">Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr data-book="<?= (int)$r['id'] ?>"><td colspan="8" class="text-center py-4 text-secondary">Nessun risultato.</td></tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr data-book="<?= (int)$r['id'] ?>">
          <td>
            <?php if (!empty($r['cover_path'])): ?>
              <img class="cover-thumb-lg" src="<?= e($r['cover_path']) ?>" alt="Copertina">
            <?php else: ?>
              <div class="cover-thumb d-inline-flex align-items-center justify-content-center text-muted">
                <i class="fa-regular fa-image"></i>
              </div>
            <?php endif; ?>
          </td>
          <td><a href="book.php?id=<?= (int)$r['id'] ?>" class="fw-semibold"><?= e($r['title']) ?></a></td>
          <td><?= e($r['author']) ?></td>
          <td><?= e($r['genre'] ?? '') ?></td>
          <td>
            <?php $labels=['non_letto'=>'Non letto','in_lettura'=>'In lettura','letto'=>'Letto']; $classes=['non_letto'=>'status-non_letto','in_lettura'=>'status-in_lettura','letto'=>'status-letto']; $st=$r['status']??'non_letto'; $next=($st==='non_letto'?'in_lettura':($st==='in_lettura'?'letto':'non_letto')); ?>
            <span class="badge rounded-pill js-status <?= $classes[$st] ?>"><?= $labels[$st] ?></span>
            <button class="btn btn-sm btn-outline-primary ms-2 btn-status" data-action="toggle-status" data-id="<?= (int)$r['id'] ?>" data-next="<?= $next ?>"><i class="fa-solid fa-retweet me-1"></i>Segna: <?= $labels[$next] ?></button>
          </td>
          <td><?= e($r['year'] ?? '') ?></td>
          <td><?= e($r['isbn'] ?? '') ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="books-edit.php?id=<?= (int)$r['id'] ?>"><i class="fa-regular fa-pen-to-square"></i></a>
            <form class="d-inline" method="post" action="books-delete.php" onsubmit="return confirm('Eliminare definitivamente questo libro?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger"><i class="fa-regular fa-trash-can"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<nav aria-label="Paginazione" class="mt-3">
  <ul class="pagination">
    <?php for ($i=1; $i<=$totalPages; $i++): ?>
      <?php $qstr = $_GET; $qstr['page'] = $i; ?>
      <li class="page-item <?= $i===$page?'active':'' ?>"><a class="page-link" href="?<?= http_build_query($qstr) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php include __DIR__ . '/sezioni/footer.php'; ?>