<?php
declare(strict_types=1);

// تحسينات جلسة (تقلل مشاكل CSRF)
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
// على HTTPS عادةً:
ini_set('session.cookie_samesite', 'Lax');

session_start();

require_once __DIR__ . '/db.php';

function e(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function social_status_options(): array {
  return [
    'أرملة',
    'مطلقة',
    'يتيم الأب',
    'مريض مزمن',
    'عاطل عن العمل',
    'ذوي إعاقة',
    'كبير سن',
    'أخرى',
  ];
}

function is_valid_social_status(string $value): bool {
  return in_array($value, social_status_options(), true);
}

function clamp_int($v, int $min, int $max, int $default): int {
  $v = filter_var($v, FILTER_VALIDATE_INT);
  if ($v === false) return $default;
  return max($min, min($max, (int)$v));
}

/** Flash messages */
function flash_set(string $key, string $msg): void {
  $_SESSION['flash'][$key] = $msg;
}
function flash_get(string $key): ?string {
  $m = $_SESSION['flash'][$key] ?? null;
  unset($_SESSION['flash'][$key]);
  return $m;
}

/** CSRF */
function csrf_init(): void {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
  }
}
function csrf_field(): string {
  csrf_init();
  return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';
}
function csrf_verify(): void {
  $t = $_POST['csrf'] ?? '';
  if (!$t || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
    http_response_code(419);
    exit('CSRF token mismatch');
  }
}

function redirect(string $url): never {
  header('Location: ' . $url);
  exit;
}

function current_url(array $overrides = []): string {
  $base = strtok($_SERVER['REQUEST_URI'], '?');
  $q = array_merge($_GET, $overrides);

  // تنظيف مفاتيح null حتى لا تظهر edit=
  foreach ($q as $k => $v) {
    if ($v === null) unset($q[$k]);
  }

  $qs = http_build_query($q);
  return $base . ($qs ? '?' . $qs : '');
}

function render_pagination(int $page, int $perPage, int $total): string {
  $pages = (int)ceil(max(0, $total) / $perPage);
  $pages = max(1, $pages);
  if ($pages <= 1) return '';

  $start = max(1, $page - 3);
  $end = min($pages, $page + 3);

  $html = '<div class="row" style="margin-top:12px">';
  $html .= '<a class="btn" href="'.e(current_url(['page'=>1])).'">الأولى</a>';
  for ($p=$start; $p<=$end; $p++) {
    $cls = ($p === $page) ? 'btn btn-primary' : 'btn';
    $html .= '<a class="'. $cls .'" href="'.e(current_url(['page'=>$p])).'">'. $p .'</a>';
  }
  $html .= '<a class="btn" href="'.e(current_url(['page'=>$pages])).'">الأخيرة</a>';
  $html .= '</div>';

  return $html;
}