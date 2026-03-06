<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';

/**
 * Public Display Page (Tailwind + premium CSS)
 * - entity switcher: families | orphans
 * - DEFAULT after any reload: families
 *
 * Important:
 * - We intentionally DO NOT persist entity in the URL unless user selects it.
 * - If entity not provided => families.
 */

$entity = strtolower(trim($_GET['entity'] ?? 'families'));
if (!in_array($entity, ['families', 'orphans'], true)) $entity = 'families';

/** filters */
$q = trim($_GET['q'] ?? '');
$archived = ($_GET['archived'] ?? '0') === '1' ? 1 : 0;
$showDesc = ($_GET['show_desc'] ?? '1') === '1';
$page = clamp_int($_GET['page'] ?? 1, 1, 999999, 1);
$perPage = clamp_int($_GET['per_page'] ?? 15, 5, 50, 15);

/** families-only filter */
$social = trim($_GET['social_status'] ?? '');

$params = [':archived' => $archived];
$where = "is_archived = :archived";

if ($q !== '') {
  // families has status_description; orphans might not, so use conditional query
  if ($entity === 'families') {
    $where .= " AND (full_name LIKE :q OR national_id LIKE :q OR phone LIKE :q OR status_description LIKE :q)";
  } else {
    $where .= " AND (full_name LIKE :q OR national_id LIKE :q OR phone LIKE :q)";
  }
  $params[':q'] = '%' . $q . '%';
}

if ($entity === 'families' && $social !== '' && is_valid_social_status($social)) {
  $where .= " AND social_status = :social";
  $params[':social'] = $social;
}

/** count */
$stmt = db()->prepare("SELECT COUNT(*) c FROM {$entity} WHERE $where");
$stmt->execute($params);
$total = (int)$stmt->fetch()['c'];

$offset = ($page - 1) * $perPage;

/** select */
if ($entity === 'families') {
  $stmt = db()->prepare("
    SELECT id, full_name, national_id, phone, social_status, status_description, is_archived, created_at
    FROM families
    WHERE $where
    ORDER BY id DESC
    LIMIT $perPage OFFSET $offset
  ");
} else {
  // orphans may not have social_status/status_description
  $stmt = db()->prepare("
    SELECT id, full_name, national_id, phone, is_archived, created_at
    FROM orphans
    WHERE $where
    ORDER BY id DESC
    LIMIT $perPage OFFSET $offset
  ");
}
$stmt->execute($params);
$rows = $stmt->fetchAll();

/** KPIs */
$kFamilies = db()->query("SELECT
  SUM(CASE WHEN is_archived=0 THEN 1 ELSE 0 END) active_count,
  SUM(CASE WHEN is_archived=1 THEN 1 ELSE 0 END) archived_count
  FROM families")->fetch() ?: ['active_count'=>0,'archived_count'=>0];

$kOrphans = db()->query("SELECT
  SUM(CASE WHEN is_archived=0 THEN 1 ELSE 0 END) active_count,
  SUM(CASE WHEN is_archived=1 THEN 1 ELSE 0 END) archived_count
  FROM orphans")->fetch() ?: ['active_count'=>0,'archived_count'=>0];

/**
 * URL builder:
 * - If $over includes 'entity'=>null, it will REMOVE entity from url (forces default families on reload)
 */
function qs(array $over = []): string {
  $base = strtok($_SERVER['REQUEST_URI'], '?') ?: 'index.php';
  $q = array_merge($_GET, $over);

  // remove empty/null keys
  foreach ($q as $k => $v) {
    if ($v === null || $v === '') unset($q[$k]);
  }

  // IMPORTANT: if entity not set, do not keep it (default families)
  // This makes any plain reload return to families.
  if (!isset($over['entity'])) {
    unset($q['entity']);
  }

  $query = http_build_query($q);
  return $base . ($query ? '?' . $query : '');
}

$isFamilies = ($entity === 'families');
$title = $isFamilies ? 'عرض الأسر الفقيرة' : 'عرض الأيتام';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> - <?= e($title) ?></title>

  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    body{
      background:
        radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.18), transparent 60%),
        radial-gradient(800px 400px at 10% 0%, rgba(168,85,247,.14), transparent 55%),
        radial-gradient(900px 500px at 60% 90%, rgba(16,185,129,.10), transparent 60%),
        linear-gradient(180deg, #f8fafc 0%, #f3f4f6 100%);
    }
    .glass{
      background: rgba(255,255,255,.70);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border: 1px solid rgba(255,255,255,.60);
    }
    .cardx{
      border-radius: 22px;
      box-shadow: 0 20px 60px rgba(2,6,23,.10);
      border: 1px solid rgba(148,163,184,.25);
      background: rgba(255,255,255,.88);
    }
    .inpx{
      border-radius: 18px;
      border: 1px solid rgba(148,163,184,.35);
      background: rgba(255,255,255,.80);
      padding: 10px 12px;
      outline: none;
      width: 100%;
    }
    .inpx:focus{
      border-color: rgba(59,130,246,.6);
      box-shadow: 0 0 0 4px rgba(59,130,246,.18);
    }
    .btnx{
      border-radius: 18px;
      padding: 10px 14px;
      font-weight: 900;
      font-size: 13px;
      border: 1px solid rgba(148,163,184,.35);
      background: rgba(255,255,255,.85);
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:.35rem;
    }
    .btnx:hover{background: rgba(255,255,255,1)}
    .btnxP{
      border-radius: 18px;
      padding: 10px 14px;
      font-weight: 900;
      font-size: 13px;
      color:#fff;
      border: 1px solid rgba(59,130,246,.40);
      background: linear-gradient(90deg, #2563eb, #7c3aed);
      box-shadow: 0 14px 30px rgba(37,99,235,.18);
      display:inline-flex;
      align-items:center;
      justify-content:center;
    }
    .btnxP:hover{filter: brightness(1.05)}
    .tablex th{
      background: linear-gradient(180deg, rgba(241,245,249,.95), rgba(255,255,255,.95));
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .tablex tr:nth-child(even) td{background: rgba(248,250,252,.75)}
    .tablex tr:hover td{background: rgba(219,234,254,.45)}
  </style>
</head>

<body class="min-h-screen text-slate-900">
  <!-- Top bar -->
  <header class="sticky top-0 z-40">
    <div class="mx-auto max-w-7xl px-4 py-3">
      <div class="glass rounded-[22px] px-4 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-2xl bg-slate-950 text-white grid place-items-center font-black">Z</div>
          <div class="leading-tight">
            <div class="font-black"><?= e(APP_NAME) ?></div>
            <div class="text-xs text-slate-500"><?= e($title) ?></div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- IMPORTANT: reload without entity => defaults to families -->
          <a href="./" class="btnx">الافتراضي (الأسر)</a>
          <a href="admin.php" class="btnxP">لوحة 999999999988إدارة</a>
        </div>
      </div>
    </div>
  </header>

  <main class="mx-auto max-w-7xl px-4 py-6 space-y-4">

    <!-- Entity switcher -->
    <section class="cardx p-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="text-sm font-black">اختر القائمة</div>
          <div class="text-xs text-slate-500">عند إعادة تحميل الصفحة يرجع تلقائيًا إلى (الأسر الفقيرة)</div>
        </div>

        <div class="flex gap-2">
          <a class="<?= $isFamilies ? 'btnxP' : 'btnx' ?>"
             href="<?= e(qs(['entity' => null])) ?>">
            الأسر الفقيرة
          </a>

          <a class="<?= !$isFamilies ? 'btnxP' : 'btnx' ?>"
             href="<?= e(qs(['entity' => 'orphans', 'page' => 1])) ?>">
            الأيتام
          </a>
        </div>
      </div>
    </section>

    <!-- KPIs -->
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
      <div class="cardx p-4">
        <div class="text-xs text-slate-500">الأسر (نشط)</div>
        <div class="text-3xl font-black mt-1"><?= (int)$kFamilies['active_count'] ?></div>
      </div>
      <div class="cardx p-4">
        <div class="text-xs text-slate-500">الأسر (مؤرشف)</div>
        <div class="text-3xl font-black mt-1"><?= (int)$kFamilies['archived_count'] ?></div>
      </div>
      <div class="cardx p-4">
        <div class="text-xs text-slate-500">الأيتام (نشط)</div>
        <div class="text-3xl font-black mt-1"><?= (int)$kOrphans['active_count'] ?></div>
      </div>
      <div class="cardx p-4">
        <div class="text-xs text-slate-500"><?= $isFamilies ? 'نتائج الأسر' : 'نتائج الأيتام' ?></div>
        <div class="text-3xl font-black mt-1"><?= (int)$total ?></div>
      </div>
    </section>

    <!-- Filters -->
    <section class="cardx p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-xl font-black">بحث وفلترة</h1>
          <p class="text-sm text-slate-500 mt-1">الفلترة تتغير حسب القائمة المختارة</p>
        </div>

        <div class="flex flex-wrap gap-2">
          <a href="<?= $isFamilies ? './' : e(qs(['entity'=>'orphans','q'=>null,'archived'=>null,'page'=>null])) ?>" class="btnx">مسح الفلاتر</a>
          <button form="filters" type="submit" class="btnxP">بحث</button>
        </div>
      </div>

      <form id="filters" method="get" class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
        <!-- Keep entity only when user selected orphans; families is default without entity -->
        <?php if (!$isFamilies): ?>
          <input type="hidden" name="entity" value="orphans">
        <?php endif; ?>

        <div class="xl:col-span-2">
          <label class="text-sm font-bold">بحث عام</label>
          <input class="inpx mt-1" name="q" value="<?= e($q) ?>" placeholder="اسم/هوية/هاتف<?= $isFamilies ? '/وصف' : '' ?>">
        </div>

        <?php if ($isFamilies): ?>
          <div>
            <label class="text-sm font-bold">الحالة الاجتماعية</label>
            <select class="inpx mt-1" name="social_status">
              <option value="">عرض الكل</option>
              <?php foreach (social_status_options() as $opt): ?>
                <option value="<?= e($opt) ?>" <?= $social === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div>
          <label class="text-sm font-bold">النوع</label>
          <select class="inpx mt-1" name="archived">
            <option value="0" <?= $archived===0?'selected':'' ?>>نشط</option>
            <option value="1" <?= $archived===1?'selected':'' ?>>مؤرشف</option>
          </select>
        </div>

        <?php if ($isFamilies): ?>
          <div>
            <label class="text-sm font-bold">وصف الحال  </label>
            <select class="inpx mt-1" name="show_desc">
              <option value="1" <?= $showDesc?'selected':'' ?>>إظهار</option>
              <option value="0" <?= !$showDesc?'selected':'' ?>>إخفاء</option>
            </select>
          </div>
        <?php endif; ?>

        <div>
          <label class="text-sm font-bold">عدد/صفحة</label>
          <select class="inpx mt-1" name="per_page">
            <?php foreach ([10,15,20,30,50] as $n): ?>
              <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex items-end gap-2">
          <a class="btnx w-full text-center" href="<?= e(qs(['page'=>max(1,$page-1)])) ?>">السابق</a>
          <a class="btnx w-full text-center" href="<?= e(qs(['page'=>$page+1])) ?>">التالي</a>
        </div>
      </form>
    </section>

    <!-- Results -->
    <section class="cardx overflow-hidden">
      <div class="px-5 py-4 border-b bg-slate-50 flex flex-wrap items-center justify-between gap-2">
        <div class="font-black"><?= $isFamilies ? 'قائمة الأسر' : 'قائمة الأيتام' ?></div>
        <div class="text-xs text-slate-500">العدد: <?= (int)$total ?></div>
      </div>

      <!-- Mobile cards -->
      <div class="block lg:hidden divide-y">
        <?php foreach ($rows as $r): ?>
          <div class="p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-black"><?= e($r['full_name']) ?></div>
                <div class="text-xs text-slate-500 mt-1">
                  الهوية: <?= e((string)($r['national_id'] ?? '')) ?> — الهاتف: <?= e((string)($r['phone'] ?? '')) ?>
                </div>
              </div>
              <?php if ($isFamilies): ?>
                <a class="btnx" target="_blank"
                   href="print.php?id=<?= (int)$r['id'] ?>&show_desc=<?= $showDesc ? '1' : '0' ?>">طباعة</a>
              <?php endif; ?>
            </div>

            <?php if ($isFamilies): ?>
              <div class="mt-3 flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full border px-2 py-1 text-[11px] font-bold bg-indigo-50 border-indigo-100 text-indigo-800">
                  <?= e($r['social_status']) ?>
                </span>
                <span class="inline-flex items-center rounded-full border px-2 py-1 text-[11px] font-bold bg-slate-50 border-slate-200 text-slate-700">
                  <?= e((string)$r['created_at']) ?>
                </span>
              </div>

              <?php if ($showDesc): ?>
                <div class="mt-3 text-sm text-slate-700 whitespace-pre-wrap">
                  <?= e((string)($r['status_description'] ?? '')) ?>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
          <div class="p-6 text-center text-slate-500">لا توجد نتائج</div>
        <?php endif; ?>
      </div>

      <!-- Desktop table -->
      <div class="hidden lg:block">
        <div class="overflow-x-auto max-h-[560px]">
          <table class="min-w-full text-sm tablex">
            <thead>
              <tr class="text-slate-600">
                <th class="px-4 py-3 text-right font-bold">#</th>
                <th class="px-4 py-3 text-right font-bold">الاسم</th>
                <th class="px-4 py-3 text-right font-bold">الهوية</th>
                <th class="px-4 py-3 text-right font-bold">الهاتف</th>

                <?php if ($isFamilies): ?>
                  <th class="px-4 py-3 text-right font-bold">الحالة</th>
                  <?php if ($showDesc): ?>
                    <th class="px-4 py-3 text-right font-bold">وصف الحالة</th>
                  <?php endif; ?>
                  <th class="px-4 py-3 text-right font-bold">تاريخ الإدخال</th>
                  <th class="px-4 py-3 text-right font-bold">طباعة</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="px-4 py-3"><?= (int)$r['id'] ?></td>
                  <td class="px-4 py-3 font-black"><?= e($r['full_name']) ?></td>
                  <td class="px-4 py-3"><?= e((string)($r['national_id'] ?? '')) ?></td>
                  <td class="px-4 py-3"><?= e((string)($r['phone'] ?? '')) ?></td>

                  <?php if ($isFamilies): ?>
                    <td class="px-4 py-3">
                      <span class="inline-flex items-center rounded-full border px-2 py-1 text-[11px] font-bold bg-indigo-50 border-indigo-100 text-indigo-800">
                        <?= e($r['social_status']) ?>
                      </span>
                    </td>

                    <?php if ($showDesc): ?>
                      <td class="px-4 py-3 text-slate-700 whitespace-pre-wrap"><?= e((string)($r['status_description'] ?? '')) ?></td>
                    <?php endif; ?>

                    <td class="px-4 py-3 text-slate-600"><?= e((string)$r['created_at']) ?></td>

                    <td class="px-4 py-3">
                      <a class="btnx" target="_blank"
                         href="print.php?id=<?= (int)$r['id'] ?>&show_desc=<?= $showDesc ? '1' : '0' ?>">
                        طباعة
                      </a>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>

              <?php if (!$rows): ?>
                <tr><td class="px-4 py-6 text-center text-slate-500" colspan="<?= $isFamilies ? ($showDesc ? 8 : 7) : 4 ?>">لا توجد نتائج</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="px-5 py-4 border-t bg-slate-50">
        <?= render_pagination($page, $perPage, $total) ?>
        <div class="text-xs text-slate-500 mt-2">
          عند إعادة تحميل الصفحة بدون رابط خاص ترجع تلقائيًا إلى (الأسر الفقيرة).
        </div>
      </div>
    </section>
  </main>
</body>
</html>
