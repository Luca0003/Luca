<?php
// NYT Books API (Top 10 reali)
define('NYT_API_BASE', 'https://api.nytimes.com/svc/books/v3');
define('NYT_API_KEY', 'Fx20CmTM3aZz3iGOAMu6awwYZrabdfj4');

// Google Books API (per Suggeriti basati su ISBN locali)
define('GB_API_BASE', 'https://www.googleapis.com/books/v1');
define('GB_API_KEY', ''); // opzionale
define('SUGGEST_API_BASE', defined('BOOKS_API_BASE') ? BOOKS_API_BASE : (getenv('SUGGEST_API_BASE') ?: 'https://api.example.com'));
define('SUGGEST_API_KEY', defined('BOOKS_API_KEY') ? BOOKS_API_KEY : (getenv('SUGGEST_API_KEY') ?: ''));
