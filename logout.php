<?php
require_once __DIR__ . '/sezioni/session.php';
logout_user();
header('Location: index.php');
exit;
