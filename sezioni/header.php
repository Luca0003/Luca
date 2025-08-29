<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/util.php';
$u = current_user();
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>BookShelf</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="assets/styles.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg border-bottom bg-white">
  <div class="container">
    <a class="navbar-brand fw-semibold d-flex align-items-center" href="index.php">
      <i class="fa-solid fa-book-open-reader me-2 text-primary"></i>BookShelf
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsExample">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarsExample">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
              <li class="nav-item"><a class="nav-link fw-medium" href="books.php"><i class="fa-solid fa-layer-group me-1"></i>Catalogo</a></li>
              <li class="nav-item"><a class="nav-link" href="books-create.php"><i class="fa-solid fa-plus me-1"></i>Aggiungi</a></li>
              <li class="nav-item"><a class="nav-link" href="stats.php"><i class="fa-solid fa-chart-simple me-1"></i>Statistiche</a></li>
</ul>
      <form class="d-flex me-3" role="search" method="get" action="books.php">
        <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Cerca titolo, autore, ISBN" aria-label="Cerca">
        <button class="btn btn-sm btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
      </form>
      <?php if ($u): ?>
        <div class="dropdown">
          <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fa-regular fa-user me-1"></i><?= e($u['name'] ?: $u['email']) ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="books-create.php"><i class="fa-solid fa-plus me-2"></i>Nuovo libro</a></li>
            <li><a class="dropdown-item" href="settings.php"><i class="fa-solid fa-gear me-2"></i>Impostazioni</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Esci</a></li>
          </ul>
        </div>
      <?php else: ?>
        <a class="btn btn-outline-secondary me-2" href="login.php">Accedi</a>
        <a class="btn btn-primary" href="register.php">Registrati</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<main class="py-4">
  <div class="container">
    <?php if ($msg = flash('success')): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-regular fa-circle-check me-1"></i> <?= e($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-regular fa-circle-xmark me-1"></i> <?= e($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
