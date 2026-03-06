<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';

/**
 * Admin – dashboard / distribution section.
 * Families, orphans, and reports now have their own standalone pages.
 * Requests for those sections are redirected automatically.
 */

$ORG_NAME = '666666 زكاة مخيم حطين (نسخة جديدة)';

$section = trim($_GET['section'] ?? 'dashboard');
$allowed = ['dashboard', 'distribution'];
if (!in_array($section, $allowed, true)) {
  // Redirect legacy section URLs to the new standalone pages
  $redirectMap = [
    'families' => 'families.php',
    'orphans'  => 'orphans.php',
    'reports'  => 'reports.php',
  ];
  if (isset($redirectMap[$section])) {
    redirect($redirectMap[$section]);
  }
  $section = 'dashboard';
}

$ok  = flash_get('ok') ?? '';
$err = flash_get('err') ?? '';

function admin_url(string $section, array $over = []): string {
  $q = array_merge($_GET, $over, ['section' => $section]);
  foreach ($q as $k => $v) {
    if ($v === null || $v === '') unset($q[$k]);
  }
  return 'admin.php?' . http_build_query($q);
}
function admin_redirect(string $section, array $params = []): void {
  redirect('admin.php?' . http_build_query(array_merge(['section' => $section], $params)));
}

/** Load data */
csrf_init();

$titleMap = [
  'dashboard'    => 'لوحة التحكم',
  'distribution' => 'التوزيع والطباعة',
];
$pageTitle = $titleMap[$section] ?? 'لوحة التحكم';

/** Decide active entity for print button */
$activePrintEntity = 'families';
$activePrintLabel  = 'الأسر الفقيرة';
$activePage = $section;
?>
<?php include __DIR__ . '/partials/page-top.php'; ?>

        <?php if ($section === 'distribution'): ?>
          <div class="cardx p-5">
            <p class="text-sm text-slate-600">
              استخدم الأزرار التالية لطباعة كشف 30/صفحة.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
              <a class="btnxP" target="_blank" href="<?= e(print_url('families', ['page'=>1,'archived'=>'0'])) ?>">طباعة الأسر (صفحة 1)</a>
              <a class="btnx" target="_blank" href="<?= e(print_url('families', ['page'=>2,'archived'=>'0'])) ?>">الأسر (صفحة 2)</a>
              <a class="btnx" target="_blank" href="<?= e(print_url('families', ['page'=>3,'archived'=>'0'])) ?>">الأسر (صفحة 3)</a>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
              <a class="btnxP" target="_blank" href="<?= e(print_url('orphans', ['page'=>1,'archived'=>'0'])) ?>">طباعة الأيتام (صفحة 1)</a>
              <a class="btnx" target="_blank" href="<?= e(print_url('orphans', ['page'=>2,'archived'=>'0'])) ?>">الأيتام (صفحة 2)</a>
              <a class="btnx" target="_blank" href="<?= e(print_url('orphans', ['page'=>3,'archived'=>'0'])) ?>">الأيتام (صفحة 3)</a>
            </div>
          </div>

        <?php else: /* dashboard */ ?>
          <?php
          $kAll = get_kpis();
          $kF   = $kAll['kFamilies'];
          $kO   = $kAll['kOrphans'];
          $kS   = $kAll['kSponsorships'];
          ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
            <div class="cardx p-4">
              <div class="text-xs text-slate-500">الأسر الفقيرة (نشط)</div>
              <div class="text-3xl font-black mt-1"><?= (int)$kF['active_count'] ?></div>
              <div class="text-xs text-slate-400 mt-1">مؤرشف: <?= (int)$kF['archived_count'] ?></div>
            </div>
            <div class="cardx p-4">
              <div class="text-xs text-slate-500">الأيتام (نشط)</div>
              <div class="text-3xl font-black mt-1"><?= (int)$kO['active_count'] ?></div>
              <div class="text-xs text-slate-400 mt-1">مؤرشف: <?= (int)$kO['archived_count'] ?></div>
            </div>
            <div class="cardx p-4">
              <div class="text-xs text-slate-500">كفالة الأيتام (نشط)</div>
              <div class="text-3xl font-black mt-1"><?= (int)$kS['active_count'] ?></div>
              <div class="text-xs text-slate-400 mt-1">مؤرشف: <?= (int)$kS['archived_count'] ?></div>
            </div>
          </div>

          <div class="cardx p-5">
            <div class="font-black mb-3">الوصول السريع</div>
            <div class="flex flex-wrap gap-2">
              <a class="btnxP" href="families.php">إدارة الأسر الفقيرة</a>
              <a class="btnxP" href="orphans.php">إدارة الأيتام</a>
              <a class="btnxP" href="orphan-sponsorships.php">كفالة الأيتام</a>
              <a class="btnxP" href="reports.php">التقارير</a>
              <a class="btnx" href="admin.php?section=distribution">التوزيع والطباعة</a>
            </div>
          </div>
        <?php endif; ?>

      </main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
