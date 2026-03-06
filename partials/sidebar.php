<?php
/**
 * Shared sidebar navigation
 * Expected: $activePage string – 'dashboard'|'families'|'orphans'|'sponsorships'|'reports'
 */
$_kpis = get_kpis();
$_kF = $_kpis['kFamilies'];
$_kO = $_kpis['kOrphans'];
$_kS = $_kpis['kSponsorships'];
$_ORG_DISPLAY_SB = '666666 زكاة مخيم حطين (نسخة جديدة)';
?>
      <!-- SIDEBAR RIGHT -->
      <aside class="col-span-12 lg:col-span-3 order-1 lg:order-2">
        <div class="sticky top-24 side rounded-[26px] p-4 text-white">
          <div class="flex items-center justify-between">
            <div>
              <div class="font-black"><?= e($_ORG_DISPLAY_SB) ?></div>
              <div class="text-xs text-white/70 mt-1">قائمة العمليات</div>
            </div>
            <div class="h-10 w-10 rounded-2xl bg-white/10 grid place-items-center font-black">⋮</div>
          </div>

          <div class="mt-4 space-y-1">
            <div class="text-[11px] text-white/50 px-2 pt-2">الرئيسية</div>
            <a href="admin.php"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
              <span>لوحة التحكم</span>
              <?= pill('Home', 'muted') ?>
            </a>

            <div class="text-[11px] text-white/50 px-2 pt-2">المستفيدون</div>
            <a href="families.php"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= ($activePage ?? '') === 'families' ? 'active' : '' ?>">
              <span>الأسر الفقيرة</span>
              <?= pill((string)((int)$_kF['active_count']), 'info') ?>
            </a>
            <a href="orphans.php"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= ($activePage ?? '') === 'orphans' ? 'active' : '' ?>">
              <span>الأيتام</span>
              <?= pill((string)((int)$_kO['active_count']), 'info') ?>
            </a>
            <a href="orphan-sponsorships.php"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= ($activePage ?? '') === 'sponsorships' ? 'active' : '' ?>">
              <span>كفالة الأيتام</span>
              <?= pill((string)((int)$_kS['active_count']), 'info') ?>
            </a>

            <div class="text-[11px] text-white/50 px-2 pt-2">التوزيع والطباعة</div>
            <a href="admin.php?section=distribution"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= ($activePage ?? '') === 'distribution' ? 'active' : '' ?>">
              <span>كشف توزيع (30/صفحة)</span>
              <?= pill('Print', 'muted') ?>
            </a>

            <div class="text-[11px] text-white/50 px-2 pt-2">التقارير</div>
            <a href="reports.php"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= ($activePage ?? '') === 'reports' ? 'active' : '' ?>">
              <span>التقارير</span>
              <?= pill('Reports', 'muted') ?>
            </a>

            <div class="text-[11px] text-white/50 px-2 pt-2">أدوات</div>
            <a target="_blank" href="check.php"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between">
              <span>فحص الاتصال</span>
              <?= pill('Tool', 'muted') ?>
            </a>
          </div>

          <div class="mt-4 rounded-2xl bg-white/5 p-3 text-xs text-white/70">
            ملاحظة: تسجيل الدخول/الصلاحيات سنضيفها لاحقًا.
          </div>
        </div>
      </aside>
    </div><!-- /grid -->
  </div><!-- /max-w-7xl -->
</body>
</html>
