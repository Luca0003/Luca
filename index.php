<?php
require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/api.php';
include __DIR__ . '/sezioni/header.php';

$pdo = db();

/* === TOP 10 DA NYT (normalizzo per tabella) === */
$nyt    = nyt_get('/lists/current/hardcover-fiction.json');
$topRaw = $nyt['results']['books'] ?? [];
$top    = [];
$i = 1;
foreach ($topRaw as $b) {
  $top[] = [
    'id'         => $i,
    'title'      => $b['title']  ?? 'Senza titolo',
    'author'     => $b['author'] ?? 'Sconosciuto',
    'cover_path' => $b['book_image'] ?? null,
    'views'      => (int)($b['rank'] ?? 0),
  ];
  $i++;
}
$top = array_slice($top, 0, 10);
?>
<section class="hero rounded-4 p-5 mb-4 bg-white shadow-sm">
  <div class="row align-items-center">
    <div class="col-lg-7">
      <h1 class="display-6 fw-bold mb-3">Benvenuto in <span class="text-primary">BookShelf</span></h1>
      <p class="lead text-secondary">Un catalogo ordinato, una mente leggera. Aggiungi, cerca e gestisci i tuoi libri con un'interfaccia pulita e professionale.</p>
      <div class="d-flex gap-2 mt-3 flex-wrap">
        <a href="books-create.php" class="btn btn-primary btn-lg"><i class="fa-solid fa-plus me-2"></i>Nuovo libro</a>
        <a href="books.php" class="btn btn-outline-primary btn-lg"><i class="fa-solid fa-layer-group me-2"></i>Vai al catalogo</a>
        <a href="books.php?sort=created_at&order=desc" class="btn btn-outline-secondary btn-lg"><i class="fa-regular fa-clock me-2"></i>Ultimi</a>
        <a href="books.php?sort=author&order=asc" class="btn btn-outline-secondary btn-lg"><i class="fa-solid fa-feather-pointed me-2"></i>Autori</a>
      </div>
      <p class="mt-3 mb-0"><span class="badge badge-soft rounded-pill px-3 py-2"><i class="fa-regular fa-circle-check me-1"></i>Dati salvati in MySQL </span></p>
    </div>
    <div class="col-lg-5 text-center">
      <i class="fa-solid fa-books" style="font-size: 7rem; opacity:.15;"></i>
    </div>
  </div>
</section>

<h3 class="h5 mb-3">Top 10 in tendenza</h3>
<div class="bg-white rounded-3 shadow-sm p-3 mb-4">
  <?php if (!$top) { ?>
    <div class="text-secondary">Ancora nessun dato di tendenza.</div>
  <?php } else { ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Copertina</th><th>Titolo</th><th>Autore</th><th class="text-end">Rank</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($top as $b) { ?>
            <tr>
              <td class="fw-semibold"><?= $i++ ?></td>
              <td>
                <?php if (!empty($b['cover_path'])) { ?>
                  <img class="cover-thumb" src="<?= e($b['cover_path']) ?>" alt="Copertina">
                <?php } else { ?>
                  <div class="cover-thumb d-inline-flex align-items-center justify-content-center text-muted">
                    <i class="fa-regular fa-image"></i>
                  </div>
                <?php } ?>
              </td>
              <td class="fw-semibold"><?= e($b['title']) ?></td>
              <td><?= e($b['author']) ?></td>
              <td class="text-end"><?= (int)$b['views'] ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  <?php } ?>
</div>

<section class="py-4 bg-white border-top border-bottom">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h3 class="mb-0">Top 10 libri preferiti dai lettori da sempre</h3>
      <small class="text-muted">Fonte: NYT Books API o fallback</small>
    </div>
    <div id="top10-box" class="top10-grid"></div>
  </div>
</section>

<section class="py-4">
  <div class="container">
    <h3 class="mb-3">In lettura ora</h3>
    <?php
      $u = current_user();
$uid = $u['id'] ?? null;
if ($uid === null) {
  $reading = [];
} else {
  $stmt = $pdo->prepare("SELECT * FROM books WHERE status='in_lettura' AND user_id = ? ORDER BY updated_at DESC LIMIT 6");
  $stmt->execute([$uid]);
  $reading = $stmt->fetchAll();
}
      if ($reading) {
    ?>
    <div class="row g-3">
      <?php foreach ($reading as $b) { ?>
      <div class="col-sm-6 col-lg-4">
        <div class="card h-100 shadow-sm" data-book="<?= (int)$b['id'] ?>">
          <?php if (!empty($b['cover_path'])) { ?><img class="card-img-top" src="<?= e($b['cover_path']) ?>" alt="Copertina"><?php } ?>
          <div class="card-body">
            <h6 class="card-title mb-1"><a href="book.php?id=<?= (int)$b['id'] ?>" class="text-decoration-none"><?= e($b['title']) ?></a></h6>
            <div class="text-secondary small mb-2">
              <?= e($b['author']) ?>
              <?php if (!empty($b['genre'])) { ?> · <span class="badge text-bg-light"><?= e($b['genre']) ?></span><?php } ?>
            </div>
            <?php $labels=['non_letto'=>'Non letto','in_lettura'=>'In lettura','letto'=>'Letto']; $classes=['non_letto'=>'status-non_letto','in_lettura'=>'status-in_lettura','letto'=>'status-letto']; $st=$b['status']??'non_letto'; $next=($st==='non_letto'?'in_lettura':($st==='in_lettura'?'letto':'non_letto')); ?>
            <span class="badge rounded-pill js-status <?= $classes[$st] ?>"><?= $labels[$st] ?></span>
            <button class="btn btn-sm btn-outline-primary ms-2 btn-status" data-action="toggle-status" data-id="<?= (int)$b['id'] ?>" data-next="<?= $next ?>"><i class="fa-solid fa-retweet me-1"></i>Segna: <?= $labels[$next] ?></button>
          </div>
        </div>
      </div>
      <?php } ?>
    </div>
    <?php } else { ?>
      <div class="text-secondary">Nessun libro attualmente in lettura.</div>
    <?php } ?>
  </div>
</section>

<!-- ===================== SEZIONE MODERNA: SUGGERITI + STATISTICHE ===================== -->
<section class="py-4">
  <div class="container">
    <style>
      .cover-2x3 { position: relative; width: 100%; padding-top: 150%; overflow: hidden; border-radius: .75rem; background: #f6f7f9; }
      .cover-2x3 > img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
      .book-card { transition: transform .15s ease, box-shadow .15s ease; border-radius: .9rem; background: #fff; padding: .5rem; }
      .book-card:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(0,0,0,.06); }
      .kpi { font-weight: 700; font-size: 1.1rem; }
      .progress { height: .5rem; background: #eef0f3; }
      .progress-bar { background: #0d6efd; }
      @media (prefers-color-scheme: dark) {
        .cover-2x3 { background: #1f2227; }
        .book-card { background: #14161a; }
        .progress { background: #2a2e35; }
      }
    </style>

    <div class="row g-4 align-items-stretch">
      <!-- SUGGERITI PER TE (API) -->
      <div class="col-xl-8">
        <div class="card h-100 shadow-sm">
          <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
            <div>
              <h5 class="mb-0">Suggeriti per te</h5>
              <small class="text-secondary">Dalla tua API, basati sui tuoi gusti</small>
            </div>
            <span class="badge text-bg-light border">API</span>
          </div>
          <div class="card-body">
            <?php
              // Seed dai tuoi dati (solo per "gusti"); i libri arrivano dalla tua API
              $rows = $pdo->query("SELECT author, genre FROM books WHERE (author IS NOT NULL AND author<>'') OR (genre IS NOT NULL AND genre<>'')")->fetchAll(PDO::FETCH_ASSOC);
              $authors = []; $genres = [];
              foreach ($rows as $r) { if (!empty($r['author'])) $authors[] = $r['author']; if (!empty($r['genre'])) $genres[] = $r['genre']; }
              $authorsCounts = array_count_values(array_map('strtolower', $authors)); arsort($authorsCounts);
              $genresCounts  = array_count_values(array_map('strtolower', $genres));  arsort($genresCounts);
              $topAuthors = array_slice(array_keys($authorsCounts), 0, 4);
              $topGenres  = array_slice(array_keys($genresCounts),  0, 4);

              $params = ['limit' => 12];
              if (!empty($topAuthors)) $params['seed_author'] = implode(',', $topAuthors);
              if (!empty($topGenres))  $params['seed_genre']  = implode(',', $topGenres);

              $apiList = function_exists('suggest_get') ? suggest_get($params) : [];
              $cards = [];
              if (is_array($apiList)) {
                foreach ($apiList as $it) {
                  $title = trim((string)($it['title'] ?? ($it['name'] ?? '')));
                  if ($title === '') continue;
                  $authorVal = $it['author'] ?? ($it['authors'] ?? '');
                  if (is_array($authorVal)) $authorVal = implode(', ', $authorVal);
                  $author = trim((string)$authorVal);
                  $img    = $it['cover_url'] ?? ($it['image'] ?? ($it['cover'] ?? ''));
                  $cards[] = ['title'=>$title, 'author'=>$author, 'cover_url'=>$img];
                  if (count($cards) >= 8) break; // 8 card: griglia 2×4 pulita
                }
              }

              // Fallback: Google Books con stessi seed (per non restare vuoti)
              if (empty($cards)) {
                $fallback = [];
                foreach (array_slice($topGenres,0,2) as $g) {
                  $res = gb_get('/volumes', ['q'=>'subject:'.$g, 'maxResults'=>10, 'printType'=>'books', 'orderBy'=>'relevance']);
                  foreach (($res['items'] ?? []) as $it) {
                    $vi = $it['volumeInfo'] ?? [];
                    $t  = trim((string)($vi['title'] ?? ''));
                    $auArr = $vi['authors'] ?? [];
                    $au = is_array($auArr) ? implode(', ', $auArr) : (string)$auArr;
                    $img = $vi['imageLinks']['thumbnail'] ?? '';
                    if ($t !== '') $fallback[] = ['title'=>$t, 'author'=>$au, 'cover_url'=>$img];
                    if (count($fallback) >= 8) break 2;
                  }
                }
                if (count($fallback) < 8) {
                  foreach (array_slice($topAuthors,0,2) as $a) {
                    $res = gb_get('/volumes', ['q'=>'inauthor:"'.$a.'"', 'maxResults'=>10, 'printType'=>'books', 'orderBy'=>'relevance']);
                    foreach (($res['items'] ?? []) as $it) {
                      $vi = $it['volumeInfo'] ?? [];
                      $t  = trim((string)($vi['title'] ?? ''));
                      $auArr = $vi['authors'] ?? [];
                      $au = is_array($auArr) ? implode(', ', $auArr) : (string)$auArr;
                      $img = $vi['imageLinks']['thumbnail'] ?? '';
                      if ($t !== '') $fallback[] = ['title'=>$t, 'author'=>$au, 'cover_url'=>$img];
                      if (count($fallback) >= 8) break 2;
                    }
                  }
                }
                $cards = array_slice($fallback, 0, 8);
              }
            ?>

            <?php if (!empty($cards)) { ?>
            <div class="row row-cols-2 row-cols-md-4 row-cols-lg-4 g-3">
              <?php foreach ($cards as $b) { ?>
              <div class="col">
                <div class="book-card h-100">
                  <div class="cover-2x3 mb-2">
                    <img src="<?= e($b['cover_url'] ?: 'uploads/book1.jpg') ?>" alt="<?= e($b['title']) ?>">
                  </div>
                  <div class="small fw-semibold text-truncate" title="<?= e($b['title']) ?>"><?= e($b['title']) ?></div>
                  <div class="small text-secondary text-truncate" title="<?= e($b['author']) ?>"><?= e($b['author']) ?></div>
                </div>
              </div>
              <?php } ?>
            </div>
            <?php } else { ?>
              <div class="text-secondary">Nessun suggerimento disponibile dall'API.</div>
            <?php } ?>
          </div>
        </div>
      </div>

      <!-- STATISTICHE -->
      <div class="col-xl-4">
        <div class="card h-100 shadow-sm">
          <div class="card-header bg-white border-0">
            <h5 class="mb-0">Statistiche</h5>
          </div>
          <div class="card-body">
            <?php
              $u = current_user();
              $uid = $u['id'] ?? null;
              if ($uid === null) {
                $tot = 0;
                $let = 0;
                $inl = 0;
                $non = 0;
              } else {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE user_id = ?");
                $stmt->execute([$uid]);
                $tot = (int)$stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE user_id = ? AND status='letto'");
                $stmt->execute([$uid]);
                $let = (int)$stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE user_id = ? AND status='in_lettura'");
                $stmt->execute([$uid]);
                $inl = (int)$stmt->fetchColumn();

                $non = max(0, $tot - $let - $inl);
              }
              $pct = function($n,$t){ return $t>0 ? round($n*100/$t) : 0; };
            ?>
            <div class="mb-3 d-flex justify-content-between align-items-center">
              <span>Totale libri</span><span class="kpi"><?= $tot ?></span>
            </div>

            <div class="mb-2">
              <div class="d-flex justify-content-between small"><span>Letti</span><span><?= $let ?> (<?= $pct($let,$tot) ?>%)</span></div>
              <div class="progress"><div class="progress-bar" style="width: <?= $pct($let,$tot) ?>%"></div></div>
            </div>
            <div class="mb-2">
              <div class="d-flex justify-content-between small"><span>In lettura</span><span><?= $inl ?> (<?= $pct($inl,$tot) ?>%)</span></div>
              <div class="progress"><div class="progress-bar" style="width: <?= $pct($inl,$tot) ?>%"></div></div>
            </div>
            <div>
              <div class="d-flex justify-content-between small"><span>Da leggere</span><span><?= $non ?> (<?= $pct($non,$tot) ?>%)</span></div>
              <div class="progress"><div class="progress-bar" style="width: <?= $pct($non,$tot) ?>%"></div></div>
            </div>
          </div>
        </div>
      </div>
      <!-- /STATISTICHE -->
    </div>
  </div>
</section>
<!-- ===================== /SEZIONE MODERNA ===================== -->

<section class="py-4 bg-white border-top">
  <div class="container">
    <h5>Citazione del giorno</h5>
    <?php
      $q = [
        'Un lettore vive mille vite prima di morire. — George R.R. Martin',
        'Leggere è andare incontro a qualcosa che sta per essere. — Italo Calvino',
        'I libri sono specchi: riflettono ciò che abbiamo dentro. — Carlos Ruiz Zafón',
        'Non ci sono amici più leali di un libro. — Ernest Hemingway'
      ];
      shuffle($q);
    ?>
    <blockquote class="blockquote mb-0"><p class="mb-0"><?= e($q[0]) ?></p></blockquote>
  </div>
</section>

<?php include __DIR__ . '/sezioni/footer.php'; ?>
