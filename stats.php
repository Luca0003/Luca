<?php
// Stats page per Bookshelf 2.5 - percorsi RELATIVI alla cartella del progetto
require_once __DIR__ . '/sezioni/session.php';
require_once __DIR__ . '/sezioni/util.php';
require_once __DIR__ . '/config/db.php';

require_login();
include __DIR__ . '/sezioni/header.php';

$pdo = db();
$u = current_user();
$uid = $u ? (int)$u['id'] : 0;

// Totali
$stmt = $pdo->prepare('SELECT COUNT(*) AS tot, COALESCE(SUM(views),0) AS tot_views, COALESCE(AVG(views),0) AS avg_views FROM books WHERE user_id = ?');
$stmt->execute([$uid]);
$tot = $stmt->fetch() ?: ['tot'=>0,'tot_views'=>0,'avg_views'=>0];

// Per genere
$genres = $pdo->prepare('SELECT COALESCE(genre, "Sconosciuto") AS label, COUNT(*) AS c FROM books WHERE user_id = ? GROUP BY COALESCE(genre, "Sconosciuto") ORDER BY c DESC');
$genres->execute([$uid]);
$genres = $genres->fetchAll();

// Top autori
$authors = $pdo->prepare('SELECT author AS label, COUNT(*) AS c FROM books WHERE user_id = ? GROUP BY author ORDER BY c DESC LIMIT 10');
$authors->execute([$uid]);
$authors = $authors->fetchAll();

// Aggiunte negli ultimi 12 mesi
$monthly = $pdo->prepare("
SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
FROM books
WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY ym
ORDER BY ym
");
$monthly->execute([$uid]);
$monthly = $monthly->fetchAll();

// Per anno di pubblicazione
$years = $pdo->prepare('SELECT year AS label, COUNT(*) AS c FROM books WHERE user_id = ? AND year IS NOT NULL GROUP BY year ORDER BY year');
$years->execute([$uid]);
$years = $years->fetchAll();

// Più visti
$mostViewed = $pdo->prepare('SELECT id, title, author, views FROM books WHERE user_id = ? ORDER BY views DESC, id DESC LIMIT 10');
$mostViewed->execute([$uid]);
$mostViewed = $mostViewed->fetchAll();
?>
<section class="mb-4 d-flex align-items-center justify-content-between">
  <h1 class="h3 mb-0"><i class="fa-solid fa-chart-simple me-2"></i>Statistiche</h1>
  <div class="d-flex gap-2">
    <a href="books-create.php" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Aggiungi</a>
    <a href="books.php" class="btn btn-outline-secondary">Catalogo</a>
  </div>
</section>

<div class="row g-3 mb-4">
  <div class="col-12 col-md-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted">Totale libri</div>
        <div class="display-6"><?php echo (int)$tot['tot']; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted">Visualizzazioni totali</div>
        <div class="display-6"><?php echo (int)$tot['tot_views']; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted">Media visualizzazioni / libro</div>
        <div class="display-6"><?php echo number_format((float)$tot['avg_views'], 1, ',', '.'); ?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong>Aggiunte (ultimi 12 mesi)</strong></div>
      <div class="card-body"><canvas id="chartMonthly"></canvas></div>
    </div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong>Libri per genere</strong></div>
      <div class="card-body"><canvas id="chartGenres"></canvas></div>
    </div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong>Libri per autore (Top 10)</strong></div>
      <div class="card-body"><canvas id="chartAuthors"></canvas></div>
    </div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-white"><strong>Libri per anno di pubblicazione</strong></div>
      <div class="card-body"><canvas id="chartYears"></canvas></div>
    </div>
  </div>
</div>

<div class="card shadow-sm mt-4">
  <div class="card-header bg-white"><strong>Più visti</strong></div>
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Titolo</th><th>Autore</th><th class="text-end">Views</th></tr>
      </thead>
      <tbody>
      <?php foreach ($mostViewed as $r): ?>
        <tr>
          <td><a href="book.php?id=<?= (int)$r['id'] ?>" class="text-decoration-none"><?php echo e($r['title']); ?></a></td>
          <td><?php echo e($r['author']); ?></td>
          <td class="text-end"><?php echo (int)$r['views']; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const monthly = <?php echo json_encode($monthly, JSON_UNESCAPED_UNICODE); ?>;
const genres = <?php echo json_encode($genres, JSON_UNESCAPED_UNICODE); ?>;
const authors = <?php echo json_encode($authors, JSON_UNESCAPED_UNICODE); ?>;
const years = <?php echo json_encode($years, JSON_UNESCAPED_UNICODE); ?>;

function toLabelsCounts(arr, labelKey='label') {
  const labels = [], counts = [];
  for (const r of arr) { labels.push(r[labelKey] || r['ym']); counts.push(+r['c']); }
  return {labels, counts};
}

// Monthly
(function(){
  const {labels, counts} = toLabelsCounts(monthly, 'ym');
  const ctx = document.getElementById('chartMonthly');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: { labels, datasets: [{ label: 'Aggiunte', data: counts, tension: 0.3, fill: false }] },
    options: { scales: { y: { beginAtZero: true, precision: 0 } } }
  });
})();

// Genres
(function(){
  const {labels, counts} = toLabelsCounts(genres);
  const ctx = document.getElementById('chartGenres');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Libri', data: counts }] },
    options: { indexAxis: 'y', scales: { x: { beginAtZero: true, precision: 0 } } }
  });
})();

// Authors
(function(){
  const {labels, counts} = toLabelsCounts(authors);
  const ctx = document.getElementById('chartAuthors');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Libri', data: counts }] },
    options: { indexAxis: 'y', scales: { x: { beginAtZero: true, precision: 0 } } }
  });
})();

// Years
(function(){
  const {labels, counts} = toLabelsCounts(years);
  const ctx = document.getElementById('chartYears');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Libri', data: counts }] },
    options: { scales: { y: { beginAtZero: true, precision: 0 } } }
  });
})();
</script>

<?php include __DIR__ . '/sezioni/footer.php'; ?>
