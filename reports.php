<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';

ensure_tables();

$ok  = flash_get('ok') ?? '';
$err = flash_get('err') ?? '';

csrf_init();

/* ── Load KPI data ───────────────────────────────────────────── */
$kpis = get_kpis();
$kF   = $kpis['kFamilies'];
$kO   = $kpis['kOrphans'];
$kS   = $kpis['kSponsorships'];

/* ── Families by social status ──────────────────────────────── */
$familiesByStatus = db()->query("
  SELECT social_status, COUNT(*) AS cnt
  FROM families
  WHERE is_archived=0
  GROUP BY social_status
  ORDER BY cnt DESC
")->fetchAll();

/* ── Monthly receipts summary (last 6 months) ──────────────── */
$receiptsSummary = db()->query("
  SELECT beneficiary_type, period_month, COUNT(*) AS cnt
  FROM monthly_receipts
  WHERE period_month >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 6 MONTH), '%Y-%m')
  GROUP BY beneficiary_type, period_month
  ORDER BY period_month DESC, beneficiary_type
")->fetchAll();

/* ── Recent receipts ─────────────────────────────────────────── */
$recentReceipts = db()->query("
  SELECT mr.*, 
    CASE mr.beneficiary_type
      WHEN 'family'       THEN (SELECT full_name FROM families WHERE id=mr.beneficiary_id)
      WHEN 'orphan'       THEN (SELECT full_name FROM orphans WHERE id=mr.beneficiary_id)
      WHEN 'sponsorship'  THEN (SELECT full_name FROM orphan_sponsorships WHERE id=mr.beneficiary_id)
    END AS beneficiary_name
  FROM monthly_receipts mr
  ORDER BY mr.created_at DESC
  LIMIT 30
")->fetchAll();

$pageTitle  = 'التقارير';
$activePage = 'reports';
$activePrintEntity = null;
$activePrintLabel  = null;

include __DIR__ . '/partials/page-top.php';
?>

<!-- ── KPI Cards ─────────────────────────────────────────────── -->
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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <!-- ── Families by social status ───────────────────────────── -->
  <div class="cardx p-5">
    <div class="font-black mb-3">الأسر حسب الحالة الاجتماعية</div>
    <?php if ($familiesByStatus): ?>
      <table class="min-w-full text-sm tablex">
        <thead>
          <tr class="text-slate-600">
            <th class="px-4 py-3 text-right font-bold">الحالة الاجتماعية</th>
            <th class="px-4 py-3 text-right font-bold">العدد</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($familiesByStatus as $r): ?>
            <tr>
              <td class="px-4 py-3"><?= e($r['social_status']) ?></td>
              <td class="px-4 py-3 font-bold"><?= (int)$r['cnt'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="text-slate-500 text-sm">لا توجد بيانات</div>
    <?php endif; ?>
  </div>

  <!-- ── Monthly receipts summary ─────────────────────────────── -->
  <div class="cardx p-5">
    <div class="font-black mb-3">ملخص الاستلام الشهري (آخر 6 أشهر)</div>
    <?php
    $typeLabels = ['family'=>'الأسر', 'orphan'=>'الأيتام', 'sponsorship'=>'كفالة الأيتام'];
    ?>
    <?php if ($receiptsSummary): ?>
      <table class="min-w-full text-sm tablex">
        <thead>
          <tr class="text-slate-600">
            <th class="px-4 py-3 text-right font-bold">الفترة</th>
            <th class="px-4 py-3 text-right font-bold">النوع</th>
            <th class="px-4 py-3 text-right font-bold">عدد السجلات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($receiptsSummary as $r): ?>
            <tr>
              <td class="px-4 py-3 font-bold"><?= e($r['period_month']) ?></td>
              <td class="px-4 py-3"><?= e($typeLabels[$r['beneficiary_type']] ?? $r['beneficiary_type']) ?></td>
              <td class="px-4 py-3"><?= (int)$r['cnt'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="text-slate-500 text-sm">لا توجد بيانات استلام شهري بعد</div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Recent monthly receipts ──────────────────────────────── -->
<div class="cardx overflow-hidden">
  <div class="px-5 py-4 border-b bg-slate-50 flex items-center justify-between">
    <div class="font-black">آخر سجلات الاستلام الشهري</div>
    <div class="text-xs text-slate-500">آخر 30 سجل</div>
  </div>
  <?php if ($recentReceipts): ?>
  <div class="overflow-x-auto max-h-[480px]">
    <table class="min-w-full text-sm tablex">
      <thead>
        <tr class="text-slate-600">
          <th class="px-4 py-3 text-right font-bold">الفترة</th>
          <th class="px-4 py-3 text-right font-bold">النوع</th>
          <th class="px-4 py-3 text-right font-bold">المستفيد</th>
          <th class="px-4 py-3 text-right font-bold">المواد المستلمة</th>
          <th class="px-4 py-3 text-right font-bold">ملاحظات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentReceipts as $r): ?>
          <tr>
            <td class="px-4 py-3 font-bold"><?= e($r['period_month']) ?></td>
            <td class="px-4 py-3"><?= e($typeLabels[$r['beneficiary_type']] ?? $r['beneficiary_type']) ?></td>
            <td class="px-4 py-3"><?= e((string)($r['beneficiary_name'] ?? '')) ?></td>
            <td class="px-4 py-3"><?= e($r['items']) ?></td>
            <td class="px-4 py-3 text-slate-500 text-xs"><?= e((string)($r['notes'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="px-5 py-6 text-center text-slate-500">لا توجد سجلات استلام بعد</div>
  <?php endif; ?>
</div>

<!-- ── Print links ────────────────────────────────────────────── -->
<div class="cardx p-5">
  <div class="font-black mb-3">الطباعة</div>
  <div class="flex flex-wrap gap-2">
    <a class="btnxP" target="_blank" href="<?= e(print_url('families', ['page'=>1,'archived'=>'0'])) ?>">طباعة الأسر</a>
    <a class="btnxP" target="_blank" href="<?= e(print_url('orphans', ['page'=>1,'archived'=>'0'])) ?>">طباعة الأيتام</a>
    <a class="btnx" target="_blank" href="<?= e(print_url('families', ['page'=>1,'archived'=>'1'])) ?>">طباعة الأسر المؤرشفة</a>
    <a class="btnx" target="_blank" href="<?= e(print_url('orphans', ['page'=>1,'archived'=>'1'])) ?>">طباعة الأيتام المؤرشفين</a>
  </div>
</div>

      </main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
