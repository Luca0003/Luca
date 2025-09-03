<?php
function e(?string $s) : string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path) : void { header('Location: ' . $path); exit; }
function valid_year($y) : bool { if ($y === null || $y === '') return true; return preg_match('/^\d{1,4}$/', (string)$y) === 1; }


/**
 * Effettua una GET all'API esterna e ritorna array associativo.
 * @param string $endpoint Es. '/top10'
 * @param array $params Querystring es. ['limit'=>10]
 */
function api_get(string $endpoint, array $params = []) : array {
  require_once __DIR__ . '/../config/api.php';
  $qs = http_build_query($params);
  $url = rtrim(BOOKS_API_BASE,'/') . $endpoint . ($qs ? ('?' . $qs) : '');

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'Authorization: Bearer ' . BOOKS_API_KEY
    ],
    CURLOPT_TIMEOUT => 6,
  ]);
  $res = curl_exec($ch);
  if ($res === false) {
    return [];
  }
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  if ($code >= 200 && $code < 300) {
    $data = json_decode($res, true);
    return is_array($data) ? $data : [];
  }
  return [];
}


function http_get_json(string $url, array $headers = []) : array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    CURLOPT_TIMEOUT => 12,
  ]);
  $res = curl_exec($ch);
  if ($res === false) { curl_close($ch); return []; }
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  if ($code >= 200 && $code < 300) {
    $data = json_decode($res, true);
    return is_array($data) ? $data : [];
  }
  return [];
}


function nyt_get(string $endpoint, array $params = []) : array {
  require_once __DIR__ . '/../config/api.php';
  $qs = http_build_query(array_merge($params, ['api-key' => NYT_API_KEY]));
  $url = rtrim(NYT_API_BASE,'/') . $endpoint . '?' . $qs;
  return http_get_json($url);
}


function gb_get(string $endpoint, array $params = []) : array {
  require_once __DIR__ . '/../config/api.php';
  if (!empty(GB_API_KEY)) $params['key'] = GB_API_KEY;
  $qs = http_build_query($params);
  $url = rtrim(GB_API_BASE,'/') . $endpoint . ($qs ? ('?' . $qs) : '');
  return http_get_json($url);
}


/**
 * GET generica su base+endpoint con supporto API key (header Bearer o query 'api-key').
 */
function ext_get(string $base, string $endpoint, array $params = [], string $apiKey = '') : array {
  require_once __DIR__ . '/../config/api.php';
  $qs = http_build_query($params);
  $url = rtrim($base,'/') . $endpoint . ($qs ? ('?' . $qs) : '');
  $headers = ['Accept: application/json'];
  if (!empty($apiKey)) { $headers[] = 'Authorization: Bearer ' . $apiKey; }
  // Passo comunque la chiave come query 'api-key' per compatibilità
  if (!empty($apiKey) && strpos($url, 'api-key=') === false) {
    $url .= (strpos($url, '?') === false ? '?' : '&') . 'api-key=' . urlencode($apiKey);
  }
  return http_get_json($url, $headers);
}

/**
 * Wrapper per suggerimenti: prova /suggest e poi /recommend.
 */
function suggest_get(array $params = []) : array {
  require_once __DIR__ . '/../config/api.php';
  // tentativo 1: /suggest
  $res = ext_get(SUGGEST_API_BASE, '/suggest', $params, SUGGEST_API_KEY);
  if (!is_array($res) || empty($res)) {
    // tentativo 2: /recommend
    $res = ext_get(SUGGEST_API_BASE, '/recommend', $params, SUGGEST_API_KEY);
  }
  return is_array($res) ? $res : [];
}


// --- Sticky form helpers ---
if (!function_exists('old')) {
    function old($key, $default = '') {
        if (isset($_POST[$key])) return $_POST[$key];
        if (isset($_SESSION['old'][$key])) return $_SESSION['old'][$key];
        return $default;
    }
}
if (!function_exists('old_checked')) {
    function old_checked($key, $value) {
        $val = $_POST[$key] ?? ($_SESSION['old'][$key] ?? null);
        return ($val == $value) ? 'checked' : '';
    }
}
if (!function_exists('old_selected')) {
    function old_selected($key, $value) {
        $val = $_POST[$key] ?? ($_SESSION['old'][$key] ?? null);
        return ($val == $value) ? 'selected' : '';
    }
}
// --- end helpers ---

