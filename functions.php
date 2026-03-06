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

/** UI badge helper */
function pill(string $text, string $variant): string {
  $map = [
    'ok'   => 'bg-emerald-500/15 text-emerald-200 border-emerald-500/25',
    'warn' => 'bg-amber-500/15 text-amber-200 border-amber-500/25',
    'info' => 'bg-sky-500/15 text-sky-200 border-sky-500/25',
    'muted'=> 'bg-white/10 text-white/75 border-white/10',
  ];
  $cls = $map[$variant] ?? $map['muted'];
  return '<span class="inline-flex items-center rounded-full border px-2 py-1 text-[11px] font-bold '.$cls.'">'.e($text).'</span>';
}

/** KPI counts for all beneficiary tables */
function get_kpis(): array {
  $kFamilies = db()->query("SELECT
    SUM(CASE WHEN is_archived=0 THEN 1 ELSE 0 END) active_count,
    SUM(CASE WHEN is_archived=1 THEN 1 ELSE 0 END) archived_count
    FROM families")->fetch() ?: ['active_count'=>0,'archived_count'=>0];

  $kOrphans = db()->query("SELECT
    SUM(CASE WHEN is_archived=0 THEN 1 ELSE 0 END) active_count,
    SUM(CASE WHEN is_archived=1 THEN 1 ELSE 0 END) archived_count
    FROM orphans")->fetch() ?: ['active_count'=>0,'archived_count'=>0];

  // sponsorships table may not exist yet – handle gracefully
  try {
    $kSponsorships = db()->query("SELECT
      SUM(CASE WHEN is_archived=0 THEN 1 ELSE 0 END) active_count,
      SUM(CASE WHEN is_archived=1 THEN 1 ELSE 0 END) archived_count
      FROM orphan_sponsorships")->fetch() ?: ['active_count'=>0,'archived_count'=>0];
  } catch (Throwable $e) {
    $kSponsorships = ['active_count'=>0,'archived_count'=>0];
  }

  return compact('kFamilies', 'kOrphans', 'kSponsorships');
}

/** Build URL for any page file */
function page_url(string $file, array $params = []): string {
  foreach ($params as $k => $v) {
    if ($v === null || $v === '') unset($params[$k]);
  }
  return $file . ($params ? '?' . http_build_query($params) : '');
}

/** Build print URL */
function print_url(string $entity, array $over = []): string {
  $params = [
    'entity'   => $entity,
    'page'     => $_GET['page'] ?? 1,
    'archived' => $_GET['archived'] ?? '0',
  ];
  if (!empty($_GET['q'])) $params['q'] = (string)$_GET['q'];
  if ($entity === 'families' && !empty($_GET['social_status'])) {
    $params['social_status'] = (string)$_GET['social_status'];
  }
  $params = array_merge($params, $over);
  foreach ($params as $k => $v) {
    if ($v === null || $v === '') unset($params[$k]);
  }
  return 'print-distribution.php?' . http_build_query($params);
}

/** Ensure required tables exist */
function ensure_tables(): void {
  $pdo = db();

  $pdo->exec("CREATE TABLE IF NOT EXISTS orphan_sponsorships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    national_id VARCHAR(50) NULL,
    phone VARCHAR(50) NULL,
    social_status VARCHAR(100) NOT NULL DEFAULT 'أخرى',
    status_description TEXT NULL,
    sponsor_name VARCHAR(255) NULL,
    sponsorship_amount DECIMAL(10,2) NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_national_id (national_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS monthly_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    beneficiary_type ENUM('family','orphan','sponsorship') NOT NULL,
    beneficiary_id INT NOT NULL,
    period_month VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    items TEXT NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ben (beneficiary_type, beneficiary_id),
    INDEX idx_period (period_month)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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