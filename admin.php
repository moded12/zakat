<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';

/**
 * Premium Admin UI (Tailwind CDN + custom CSS)
 * - Sidebar RIGHT (sticky)
 * - Content LEFT
 * - Sections in same admin.php via ?section=
 * - Full CRUD-like flows for:
 *    - families: add/edit/search/archive
 *    - orphans:  add/edit/search/archive
 * - Print buttons always print ONLY the active entity (families/orphans)
 *   by passing: print-distribution.php?entity=families|orphans
 */

$ORG_NAME = ' 888 ( جديد ) لجنة زكاة مخيم حطين';

$section = trim($_GET['section'] ?? 'dashboard');
$allowed = ['dashboard', 'families', 'orphans', 'distribution', 'reports'];
if (!in_array($section, $allowed, true)) $section = 'dashboard';

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

/** Build print url for active entity including current filters when useful */
function print_url(string $entity, array $over = []): string {
  $base = 'print-distribution.php';

  $params = [
    'entity' => $entity,
    'page' => $_GET['page'] ?? 1,
    // printing default only active (archived=0). You can change in UI later
    'archived' => $_GET['archived'] ?? '0',
  ];

  // pass q filter if present (works for families/orphans)
  if (!empty($_GET['q'])) $params['q'] = (string)$_GET['q'];

  // pass social_status only for families
  if ($entity === 'families' && !empty($_GET['social_status'])) {
    $params['social_status'] = (string)$_GET['social_status'];
  }

  $params = array_merge($params, $over);

  foreach ($params as $k => $v) {
    if ($v === null || $v === '') unset($params[$k]);
  }

  return $base . '?' . http_build_query($params);
}

/** POST routing */
$postEntity = trim($_POST['entity'] ?? '');
$action = trim($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();

  try {
    if (!in_array($postEntity, ['families', 'orphans'], true)) {
      throw new RuntimeException('Entity غير صحيح');
    }

    if (in_array($action, ['archive', 'unarchive'], true)) {
      if ($id <= 0) throw new RuntimeException('ID غير صحيح');
      $isArchived = ($action === 'archive') ? 1 : 0;

      $stmt = db()->prepare("UPDATE {$postEntity} SET is_archived=:a WHERE id=:id");
      $stmt->execute([':a' => $isArchived, ':id' => $id]);

      flash_set('ok', $action === 'archive' ? 'تمت الأرشفة' : 'تم إلغاء الأرشفة');
      admin_redirect($postEntity === 'families' ? 'families' : 'orphans');
    }

    if ($postEntity === 'families' && in_array($action, ['create', 'update'], true)) {
      $full_name = trim($_POST['full_name'] ?? '');
      $national_id = trim($_POST['national_id'] ?? '');
      $phone = trim($_POST['phone'] ?? '');
      $social_status = trim($_POST['social_status'] ?? '');
      $status_description = trim($_POST['status_description'] ?? '');

      if ($full_name === '' || $national_id === '' || $phone === '') {
        throw new RuntimeException('الرجاء تعبئة: الاسم / رقم الهوية / الهاتف');
      }
      if (!is_valid_social_status($social_status)) {
        throw new RuntimeException('الحالة الاجتماعية غير صحيحة');
      }

      if ($action === 'create') {
        $stmt = db()->prepare("
          INSERT INTO families (full_name, national_id, phone, social_status, status_description)
          VALUES (:n,:nid,:p,:s,:d)
        ");
        $stmt->execute([
          ':n' => $full_name,
          ':nid' => $national_id,
          ':p' => $phone,
          ':s' => $social_status,
          ':d' => ($status_description === '' ? null : $status_description),
        ]);
        flash_set('ok', 'تمت إضافة الأسرة');
        admin_redirect('families');
      } else {
        if ($id <= 0) throw new RuntimeException('ID غير صحيح');

        $stmt = db()->prepare("
          UPDATE families SET
            full_name=:n,
            national_id=:nid,
            phone=:p,
            social_status=:s,
            status_description=:d
          WHERE id=:id
        ");
        $stmt->execute([
          ':id' => $id,
          ':n' => $full_name,
          ':nid' => $national_id,
          ':p' => $phone,
          ':s' => $social_status,
          ':d' => ($status_description === '' ? null : $status_description),
        ]);
        flash_set('ok', 'تم تعديل الأسرة');
        admin_redirect('families');
      }
    }

    if ($postEntity === 'orphans' && in_array($action, ['create', 'update'], true)) {
      $full_name = trim($_POST['full_name'] ?? '');
      $national_id = trim($_POST['national_id'] ?? '');
      $phone = trim($_POST['phone'] ?? '');

      if ($full_name === '') throw new RuntimeException('الرجاء تعبئة: الاسم');

      if ($action === 'create') {
        $stmt = db()->prepare("INSERT INTO orphans (full_name, national_id, phone) VALUES (:n,:nid,:p)");
        $stmt->execute([
          ':n' => $full_name,
          ':nid' => ($national_id === '' ? null : $national_id),
          ':p' => ($phone === '' ? null : $phone),
        ]);
        flash_set('ok', 'تمت إضافة اليتيم');
        admin_redirect('orphans');
      } else {
        if ($id <= 0) throw new RuntimeException('ID غير صحيح');

        $stmt = db()->prepare("UPDATE orphans SET full_name=:n, national_id=:nid, phone=:p WHERE id=:id");
        $stmt->execute([
          ':id' => $id,
          ':n' => $full_name,
          ':nid' => ($national_id === '' ? null : $national_id),
          ':p' => ($phone === '' ? null : $phone),
        ]);
        flash_set('ok', 'تم تعديل بيانات اليتيم');
        admin_redirect('orphans');
      }
    }

    throw new RuntimeException('عملية غير مدعومة');

  } catch (Throwable $e) {
    $msg = $e->getMessage();
    if (strpos($msg, 'Duplicate') !== false || strpos($msg, '23000') !== false) {
      $msg = 'رقم الهوية موجود مسبقًا (غير مكرر).';
    }
    flash_set('err', $msg);

    $back = $postEntity === 'orphans' ? 'orphans' : ($postEntity === 'families' ? 'families' : $section);
    admin_redirect($back, ['edit' => ($id > 0 ? $id : null)]);
  }
}

/** Load data */
csrf_init();

/* KPIs */
$kFamilies = db()->query("SELECT
  SUM(CASE WHEN is_archived=0 THEN 1 ELSE 0 END) active_count,
  SUM(CASE WHEN is_archived=1 THEN 1 ELSE 0 END) archived_count
  FROM families")->fetch() ?: ['active_count'=>0,'archived_count'=>0];

$kOrphans = db()->query("SELECT
  SUM(CASE WHEN is_archived=0 THEN 1 ELSE 0 END) active_count,
  SUM(CASE WHEN is_archived=1 THEN 1 ELSE 0 END) archived_count
  FROM orphans")->fetch() ?: ['active_count'=>0,'archived_count'=>0];

/** Families data */
$f_edit_id = clamp_int($_GET['edit'] ?? 0, 0, 999999999, 0);
$f_edit_row = null;

$f_q = trim($_GET['q'] ?? '');
$f_social = trim($_GET['social_status'] ?? '');
$f_archivedRaw = $_GET['archived'] ?? '';
$f_archived = ($f_archivedRaw === '0') ? 0 : (($f_archivedRaw === '1') ? 1 : -1);
$f_showDesc = ($_GET['show_desc'] ?? '1') === '1';

$f_page = clamp_int($_GET['page'] ?? 1, 1, 999999, 1);
$f_perPage = clamp_int($_GET['per_page'] ?? 15, 5, 50, 15);

$f_rows = [];
$f_total = 0;

if ($section === 'families') {
  if ($f_edit_id > 0) {
    $stmt = db()->prepare("SELECT * FROM families WHERE id=:id LIMIT 1");
    $stmt->execute([':id' => $f_edit_id]);
    $f_edit_row = $stmt->fetch() ?: null;
  }

  $params = [];
  $where = "1=1";

  if ($f_archived === 0 || $f_archived === 1) {
    $where .= " AND is_archived = :archived";
    $params[':archived'] = $f_archived;
  }
  if ($f_social !== '' && is_valid_social_status($f_social)) {
    $where .= " AND social_status = :social";
    $params[':social'] = $f_social;
  }
  if ($f_q !== '') {
    $where .= " AND (full_name LIKE :q OR national_id LIKE :q OR phone LIKE :q OR status_description LIKE :q)";
    $params[':q'] = '%' . $f_q . '%';
  }

  $stmt = db()->prepare("SELECT COUNT(*) c FROM families WHERE $where");
  $stmt->execute($params);
  $f_total = (int)$stmt->fetch()['c'];

  $offset = ($f_page - 1) * $f_perPage;

  $stmt = db()->prepare("
    SELECT id, full_name, national_id, phone, social_status, status_description, is_archived, created_at
    FROM families
    WHERE $where
    ORDER BY id DESC
    LIMIT $f_perPage OFFSET $offset
  ");
  $stmt->execute($params);
  $f_rows = $stmt->fetchAll();
}

/** Orphans data */
$o_edit_id = clamp_int($_GET['edit'] ?? 0, 0, 999999999, 0);
$o_edit_row = null;

$o_q = trim($_GET['q'] ?? '');
$o_archivedRaw = $_GET['archived'] ?? '';
$o_archived = ($o_archivedRaw === '0') ? 0 : (($o_archivedRaw === '1') ? 1 : -1);

$o_page = clamp_int($_GET['page'] ?? 1, 1, 999999, 1);
$o_perPage = clamp_int($_GET['per_page'] ?? 15, 5, 50, 15);

$o_rows = [];
$o_total = 0;

if ($section === 'orphans') {
  if ($o_edit_id > 0) {
    $stmt = db()->prepare("SELECT * FROM orphans WHERE id=:id LIMIT 1");
    $stmt->execute([':id' => $o_edit_id]);
    $o_edit_row = $stmt->fetch() ?: null;
  }

  $params = [];
  $where = "1=1";

  if ($o_archived === 0 || $o_archived === 1) {
    $where .= " AND is_archived = :archived";
    $params[':archived'] = $o_archived;
  }
  if ($o_q !== '') {
    $where .= " AND (full_name LIKE :q OR national_id LIKE :q OR phone LIKE :q)";
    $params[':q'] = '%' . $o_q . '%';
  }

  $stmt = db()->prepare("SELECT COUNT(*) c FROM orphans WHERE $where");
  $stmt->execute($params);
  $o_total = (int)$stmt->fetch()['c'];

  $offset = ($o_page - 1) * $o_perPage;

  $stmt = db()->prepare("
    SELECT id, full_name, national_id, phone, is_archived, created_at
    FROM orphans
    WHERE $where
    ORDER BY id DESC
    LIMIT $o_perPage OFFSET $offset
  ");
  $stmt->execute($params);
  $o_rows = $stmt->fetchAll();
}

/** UI helper */
function pill(string $text, string $variant): string {
  $map = [
    'ok' => 'bg-emerald-500/15 text-emerald-200 border-emerald-500/25',
    'warn' => 'bg-amber-500/15 text-amber-200 border-amber-500/25',
    'info' => 'bg-sky-500/15 text-sky-200 border-sky-500/25',
    'muted' => 'bg-white/10 text-white/75 border-white/10',
  ];
  $cls = $map[$variant] ?? $map['muted'];
  return '<span class="inline-flex items-center rounded-full border px-2 py-1 text-[11px] font-bold '.$cls.'">'.e($text).'</span>';
}

$titleMap = [
  'dashboard' => 'لوحة التحكم',
  'families' => 'الأسر الفقيرة',
  'orphans' => 'الأيتام',
  'distribution' => 'التوزيع والطباعة',
  'reports' => 'التقارير',
];
$pageTitle = $titleMap[$section] ?? 'لوحة التحكم';

/** Decide active entity for print button */
$activePrintEntity = ($section === 'orphans') ? 'orphans' : 'families';
$activePrintLabel  = ($section === 'orphans') ? 'الأيتام' : 'الأسر الفقيرة';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> - لوحة الأدمن</title>

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    :root{
      --bg1:#0b1220;
      --bg2:#0f172a;
      --glow: 0 0 0 1px rgba(255,255,255,.06), 0 30px 80px rgba(2,6,23,.35);
    }
    body{
      background:
        radial-gradient(1200px 600px at 80% -10%, rgba(59,130,246,.20), transparent 60%),
        radial-gradient(800px 400px at 10% 0%, rgba(168,85,247,.18), transparent 55%),
        radial-gradient(900px 500px at 60% 90%, rgba(16,185,129,.12), transparent 60%),
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
    .side{
      background:
        radial-gradient(500px 300px at 30% 10%, rgba(59,130,246,.25), transparent 65%),
        radial-gradient(500px 300px at 80% 30%, rgba(168,85,247,.20), transparent 70%),
        linear-gradient(180deg, var(--bg1), var(--bg2));
      box-shadow: var(--glow);
      border: 1px solid rgba(255,255,255,.10);
    }
    .navitem{border: 1px solid transparent;}
    .navitem:hover{
      background: rgba(255,255,255,.06);
      border-color: rgba(255,255,255,.10);
      transform: translateY(-1px);
      transition: .15s ease;
    }
    .navitem.active{
      background: linear-gradient(90deg, rgba(59,130,246,.20), rgba(168,85,247,.16));
      border-color: rgba(59,130,246,.35);
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
      font-weight: 800;
      font-size: 13px;
      border: 1px solid rgba(148,163,184,.35);
      background: rgba(255,255,255,.85);
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

<body class="text-slate-900 min-h-screen">
  <!-- Top bar -->
  <header class="sticky top-0 z-40">
    <div class="mx-auto max-w-7xl px-4 py-3">
      <div class="glass rounded-[22px] px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-2xl bg-slate-950 text-white grid place-items-center font-black">Z</div>
          <div class="leading-tight">
            <div class="font-black"><?= e($ORG_NAME) ?></div>
            <div class="text-xs text-slate-500">لوحة الإدارة جديد</div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <a href="./" class="btnx">صفحة العرض</a>

          <!-- IMPORTANT: Print button uses ACTIVE ENTITY -->
          <a target="_blank" href="<?= e(print_url($activePrintEntity, ['page' => 1, 'archived' => '0'])) ?>" class="btnxP">
            طباعة كشف <?= e($activePrintLabel) ?> (30/صفحة)
          </a>
        </div>
      </div>
    </div>
  </header>

  <div class="mx-auto max-w-7xl px-4 py-6">
    <?php if ($ok): ?>
      <div class="mb-4 rounded-[20px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900"><?= e($ok) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
      <div class="mb-4 rounded-[20px] border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900"><?= e($err) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-12 gap-4">
      <!-- CONTENT LEFT -->
      <main class="col-span-12 lg:col-span-9 order-2 lg:order-1 space-y-4">
        <div class="cardx p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="text-xs text-slate-500">لوحة الأدمن / <?= e($pageTitle) ?></div>
              <h1 class="text-xl font-black mt-1"><?= e($pageTitle) ?></h1>
              <p class="text-sm text-slate-500 mt-1">كل العمليات تعمل داخل نفس الصفحة + السايدبار ثابت</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <a href="<?= e(admin_url('reports')) ?>" class="btnx">تقارير</a>
              <a href="<?= e(admin_url('distribution')) ?>" class="btnx">التوزيع والطباعة</a>
            </div>
          </div>
        </div>

        <?php if ($section === 'dashboard'): ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
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
              <div class="text-xs text-slate-500">الأيتام (مؤرشف)</div>
              <div class="text-3xl font-black mt-1"><?= (int)$kOrphans['archived_count'] ?></div>
            </div>
          </div>

          <div class="cardx p-5">
            <div class="flex flex-wrap gap-2">
              <a class="btnxP" href="<?= e(admin_url('families')) ?>">إدارة الأسر</a>
              <a class="btnxP" href="<?= e(admin_url('orphans')) ?>">إدارة الأيتام</a>
            </div>
          </div>

        <?php elseif ($section === 'distribution'): ?>
          <div class="cardx p-5">
            <p class="text-sm text-slate-600">
              استخدم الأزرار التالية لطباعة كشف 30/صفحة. اختر القسم من السايدبار أولًا (الأسر أو الأيتام)، ثم اطبع.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
              <a class="btnxP" target="_blank" href="<?= e(print_url($activePrintEntity, ['page'=>1, 'archived'=>'0'])) ?>">طباعة الصفحة 1</a>
              <a class="btnx" target="_blank" href="<?= e(print_url($activePrintEntity, ['page'=>2, 'archived'=>'0'])) ?>">طباعة الصفحة 2</a>
              <a class="btnx" target="_blank" href="<?= e(print_url($activePrintEntity, ['page'=>3, 'archived'=>'0'])) ?>">طباعة الصفحة 3</a>
            </div>
          </div>

        <?php elseif ($section === 'reports'): ?>
          <div class="cardx p-5">
            <div class="text-sm text-slate-600">قريبًا: تقارير متقدمة (حسب الحالة، حسب الأرشفة، طباعات، دفعات توزيع...)</div>
            <div class="mt-3 rounded-[18px] border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 text-sm">
              هذا القسم Placeholder وسنطوّره لاحقًا.
            </div>
          </div>

        <?php elseif ($section === 'orphans'): ?>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="cardx p-5">
              <div class="flex items-center justify-between">
                <div class="font-black"><?= $o_edit_row ? 'تعديل يتيم' : 'إضافة يتيم' ?></div>
                <a class="btnx" target="_blank" href="<?= e(print_url('orphans', ['page'=>1,'archived'=>'0'])) ?>">طباعة الأيتام</a>
              </div>

              <form method="post" class="space-y-3 mt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="entity" value="orphans">
                <?php if ($o_edit_row): ?>
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= (int)$o_edit_row['id'] ?>">
                <?php else: ?>
                  <input type="hidden" name="action" value="create">
                <?php endif; ?>

                <div>
                  <label class="text-sm font-bold">الاسم الرباعي</label>
                  <input class="inpx mt-1" name="full_name" required value="<?= e($o_edit_row['full_name'] ?? '') ?>">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="text-sm font-bold">رقم الهوية (اختياري)</label>
                    <input class="inpx mt-1" name="national_id" value="<?= e($o_edit_row['national_id'] ?? '') ?>">
                  </div>
                  <div>
                    <label class="text-sm font-bold">رقم الهاتف (اختياري)</label>
                    <input class="inpx mt-1" name="phone" value="<?= e($o_edit_row['phone'] ?? '') ?>">
                  </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                  <button class="btnxP" type="submit"><?= $o_edit_row ? 'حفظ' : 'إضافة' ?></button>
                  <?php if ($o_edit_row): ?>
                    <a class="btnx" href="<?= e(admin_url('orphans', ['edit' => null])) ?>">إلغاء</a>
                  <?php endif; ?>
                </div>
              </form>
            </div>

            <div class="cardx p-5">
              <div class="font-black mb-3">بحث</div>

              <form method="get" class="space-y-3">
                <input type="hidden" name="section" value="orphans">

                <div>
                  <label class="text-sm font-bold">بحث عام</label>
                  <input class="inpx mt-1" name="q" value="<?= e($o_q) ?>" placeholder="اسم/هوية/هاتف">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="text-sm font-bold">الأرشفة</label>
                    <select class="inpx mt-1" name="archived">
                      <option value="" <?= $o_archived===-1?'selected':'' ?>>الكل</option>
                      <option value="0" <?= $o_archived===0?'selected':'' ?>>نشط</option>
                      <option value="1" <?= $o_archived===1?'selected':'' ?>>مؤرشف</option>
                    </select>
                  </div>
                  <div>
                    <label class="text-sm font-bold">عدد/صفحة</label>
                    <select class="inpx mt-1" name="per_page">
                      <?php foreach ([10,15,20,30,50] as $n): ?>
                        <option value="<?= $n ?>" <?= $o_perPage===$n?'selected':'' ?>><?= $n ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                  <button class="btnx" type="submit">بحث</button>
                  <a class="btnx" href="<?= e(admin_url('orphans', ['q'=>null,'archived'=>null,'page'=>null,'per_page'=>null,'edit'=>null])) ?>">مسح</a>
                </div>
              </form>
            </div>
          </div>

          <div class="cardx overflow-hidden">
            <div class="px-5 py-4 border-b bg-slate-50 flex items-center justify-between">
              <div class="font-black">قائمة الأيتام</div>
              <div class="text-xs text-slate-500">العدد: <?= (int)$o_total ?></div>
            </div>

            <div class="overflow-x-auto max-h-[520px]">
              <table class="min-w-full text-sm tablex">
                <thead>
                  <tr class="text-slate-600">
                    <th class="px-4 py-3 text-right font-bold">#</th>
                    <th class="px-4 py-3 text-right font-bold">الاسم</th>
                    <th class="px-4 py-3 text-right font-bold">الهوية</th>
                    <th class="px-4 py-3 text-right font-bold">الهاتف</th>
                    <th class="px-4 py-3 text-right font-bold">الحالة</th>
                    <th class="px-4 py-3 text-right font-bold">إجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($o_rows as $r): ?>
                    <tr>
                      <td class="px-4 py-3"><?= (int)$r['id'] ?></td>
                      <td class="px-4 py-3 font-bold"><?= e($r['full_name']) ?></td>
                      <td class="px-4 py-3"><?= e((string)($r['national_id'] ?? '')) ?></td>
                      <td class="px-4 py-3"><?= e((string)($r['phone'] ?? '')) ?></td>
                      <td class="px-4 py-3">
                        <?php if ((int)$r['is_archived']): ?>
                          <?= pill('مؤرشف', 'warn') ?>
                        <?php else: ?>
                          <?= pill('نشط', 'ok') ?>
                        <?php endif; ?>
                      </td>
                      <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-2">
                          <a class="btnx" href="<?= e(admin_url('orphans', ['edit'=>$r['id']])) ?>">تعديل</a>

                          <?php if (!(int)$r['is_archived']): ?>
                            <form method="post">
                              <?= csrf_field() ?>
                              <input type="hidden" name="entity" value="orphans">
                              <input type="hidden" name="action" value="archive">
                              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                              <button class="btnx" type="submit">أرشفة</button>
                            </form>
                          <?php else: ?>
                            <form method="post">
                              <?= csrf_field() ?>
                              <input type="hidden" name="entity" value="orphans">
                              <input type="hidden" name="action" value="unarchive">
                              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                              <button class="btnx" type="submit">إلغاء</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>

                  <?php if (!$o_rows): ?>
                    <tr><td class="px-4 py-6 text-center text-slate-500" colspan="6">لا توجد نتائج</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="px-5 py-3 border-t bg-slate-50">
              <?= render_pagination($o_page, $o_perPage, $o_total) ?>
            </div>
          </div>

        <?php else: /* families */ ?>
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="cardx p-5">
              <div class="flex items-center justify-between">
                <div class="font-black"><?= $f_edit_row ? 'تعديل أسرة' : 'إضافة أسرة' ?></div>
                <a class="btnx" target="_blank" href="<?= e(print_url('families', ['page'=>1,'archived'=>'0'])) ?>">طباعة الأسر</a>
              </div>

              <form method="post" class="space-y-3 mt-3">
                <?= csrf_field() ?>
                <input type="hidden" name="entity" value="families">

                <?php if ($f_edit_row): ?>
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?= (int)$f_edit_row['id'] ?>">
                <?php else: ?>
                  <input type="hidden" name="action" value="create">
                <?php endif; ?>

                <div>
                  <label class="text-sm font-bold">الاسم الرباعي</label>
                  <input class="inpx mt-1" name="full_name" required value="<?= e($f_edit_row['full_name'] ?? '') ?>">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="text-sm font-bold">رقم الهوية (غير مكرر)</label>
                    <input class="inpx mt-1" name="national_id" required value="<?= e($f_edit_row['national_id'] ?? '') ?>">
                  </div>
                  <div>
                    <label class="text-sm font-bold">رقم الهاتف</label>
                    <input class="inpx mt-1" name="phone" required value="<?= e($f_edit_row['phone'] ?? '') ?>">
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="text-sm font-bold">الحالة الاجتماعية</label>
                    <?php $cur = $f_edit_row['social_status'] ?? social_status_options()[0]; ?>
                    <select class="inpx mt-1" name="social_status" required>
                      <?php foreach (social_status_options() as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $cur === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="text-sm font-bold">وصف الحالة</label>
                    <input class="inpx mt-1" name="status_description" value="<?= e((string)($f_edit_row['status_description'] ?? '')) ?>" placeholder="اختياري">
                  </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                  <button class="btnxP" type="submit"><?= $f_edit_row ? 'حفظ' : 'إضافة' ?></button>
                  <?php if ($f_edit_row): ?>
                    <a class="btnx" href="<?= e(admin_url('families', ['edit' => null])) ?>">إلغاء</a>
                  <?php endif; ?>
                </div>
              </form>
            </div>

            <div class="cardx p-5">
              <div class="font-black mb-3">بحث</div>

              <form method="get" class="space-y-3">
                <input type="hidden" name="section" value="families">

                <div>
                  <label class="text-sm font-bold">بحث عام</label>
                  <input class="inpx mt-1" name="q" value="<?= e($f_q) ?>" placeholder="اسم/هوية/هاتف/وصف">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="text-sm font-bold">الحالة الاجتم  عية</label>
                    <select class="inpx mt-1" name="social_status">
                      <option value="">الكل</option>
                      <?php foreach (social_status_options() as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $f_social === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div>
                    <label class="text-sm font-bold">الأرشفة</label>
                    <select class="inpx mt-1" name="archived">
                      <option value="" <?= $f_archived===-1?'selected':'' ?>>الكل</option>
                      <option value="0" <?= $f_archived===0?'selected':'' ?>>نشط</option>
                      <option value="1" <?= $f_archived===1?'selected':'' ?>>مؤرشف</option>
                    </select>
                  </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <label class="text-sm font-bold">عرض الوصف</label>
                    <select class="inpx mt-1" name="show_desc">
                      <option value="1" <?= $f_showDesc?'selected':'' ?>>نعم</option>
                      <option value="0" <?= !$f_showDesc?'selected':'' ?>>لا</option>
                    </select>
                  </div>

                  <div>
                    <label class="text-sm font-bold">عدد/صفحة</label>
                    <select class="inpx mt-1" name="per_page">
                      <?php foreach ([10,15,20,30,50] as $n): ?>
                        <option value="<?= $n ?>" <?= $f_perPage===$n?'selected':'' ?>><?= $n ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                  <button class="btnx" type="submit">بحث</button>
                  <a class="btnx" href="<?= e(admin_url('families', ['q'=>null,'social_status'=>null,'archived'=>null,'show_desc'=>null,'page'=>null,'per_page'=>null,'edit'=>null])) ?>">مسح</a>
                </div>
              </form>
            </div>
          </div>

          <div class="cardx overflow-hidden">
            <div class="px-5 py-4 border-b bg-slate-50 flex items-center justify-between">
              <div class="font-black">قائمة الأسر</div>
              <div class="text-xs text-slate-500">العدد: <?= (int)$f_total ?></div>
            </div>

            <div class="overflow-x-auto max-h-[520px]">
              <table class="min-w-full text-sm tablex">
                <thead>
                  <tr class="text-slate-600">
                    <th class="px-4 py-3 text-right font-bold">#</th>
                    <th class="px-4 py-3 text-right font-bold">الاسم</th>
                    <th class="px-4 py-3 text-right font-bold">الهوية</th>
                    <th class="px-4 py-3 text-right font-bold">الهاتف</th>
                    <th class="px-4 py-3 text-right font-bold">الحالة</th>
                    <?php if ($f_showDesc): ?>
                      <th class="px-4 py-3 text-right font-bold">الوصف</th>
                    <?php endif; ?>
                    <th class="px-4 py-3 text-right font-bold">الأرشفة</th>
                    <th class="px-4 py-3 text-right font-bold">إجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($f_rows as $r): ?>
                    <tr>
                      <td class="px-4 py-3"><?= (int)$r['id'] ?></td>
                      <td class="px-4 py-3 font-bold"><?= e($r['full_name']) ?></td>
                      <td class="px-4 py-3"><?= e($r['national_id']) ?></td>
                      <td class="px-4 py-3"><?= e($r['phone']) ?></td>
                      <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full border px-2 py-1 text-[11px] font-bold bg-slate-900/5 border-slate-900/10 text-slate-700">
                          <?= e($r['social_status']) ?>
                        </span>
                      </td>
                      <?php if ($f_showDesc): ?>
                        <td class="px-4 py-3 text-slate-700"><?= e((string)($r['status_description'] ?? '')) ?></td>
                      <?php endif; ?>
                      <td class="px-4 py-3">
                        <?php if ((int)$r['is_archived']): ?>
                          <?= pill('مؤرشف', 'warn') ?>
                        <?php else: ?>
                          <?= pill('نشط', 'ok') ?>
                        <?php endif; ?>
                      </td>
                      <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-2">
                          <a class="btnx" href="<?= e(admin_url('families', ['edit'=>$r['id']])) ?>">تعديل</a>

                          <?php if (!(int)$r['is_archived']): ?>
                            <form method="post">
                              <?= csrf_field() ?>
                              <input type="hidden" name="entity" value="families">
                              <input type="hidden" name="action" value="archive">
                              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                              <button class="btnx" type="submit">أرشفة</button>
                            </form>
                          <?php else: ?>
                            <form method="post">
                              <?= csrf_field() ?>
                              <input type="hidden" name="entity" value="families">
                              <input type="hidden" name="action" value="unarchive">
                              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                              <button class="btnx" type="submit">إلغاء</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>

                  <?php if (!$f_rows): ?>
                    <tr><td class="px-4 py-6 text-center text-slate-500" colspan="<?= $f_showDesc ? 8 : 7 ?>">لا توجد نتائج</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="px-5 py-3 border-t bg-slate-50">
              <?= render_pagination($f_page, $f_perPage, $f_total) ?>
            </div>
          </div>
        <?php endif; ?>
      </main>

      <!-- SIDEBAR RIGHT -->
      <aside class="col-span-12 lg:col-span-3 order-1 lg:order-2">
        <div class="sticky top-24 side rounded-[26px] p-4 text-white">
          <div class="flex items-center justify-between">
            <div>
              <div class="font-black"><?= e($ORG_NAME) ?></div>
              <div class="text-xs text-white/70 mt-1">قائمة العمليات</div>
            </div>
            <div class="h-10 w-10 rounded-2xl bg-white/10 grid place-items-center font-black">⋮</div>
          </div>

          <div class="mt-4 space-y-1">
            <div class="text-[11px] text-white/50 px-2 pt-2">الرئيسية</div>
            <a href="<?= e(admin_url('dashboard', ['edit'=>null,'page'=>null])) ?>"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= $section==='dashboard'?'active':'' ?>">
              <span>لوحة التحكم</span>
              <?= pill('Home', 'muted') ?>
            </a>

            <div class="text-[11px] text-white/50 px-2 pt-2">المستفيدون</div>
            <a href="<?= e(admin_url('families', ['edit'=>null,'page'=>null])) ?>"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= $section==='families'?'active':'' ?>">
              <span>الأسر الفقيرة</span>
              <?= pill((string)((int)$kFamilies['active_count']), 'info') ?>
            </a>
            <a href="<?= e(admin_url('orphans', ['edit'=>null,'page'=>null])) ?>"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= $section==='orphans'?'active':'' ?>">
              <span>الأيتام</span>
              <?= pill((string)((int)$kOrphans['active_count']), 'info') ?>
            </a>

            <div class="text-[11px] text-white/50 px-2 pt-2">التوزيع والطباعة</div>
            <a href="<?= e(admin_url('distribution')) ?>"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= $section==='distribution'?'active':'' ?>">
              <span>كشف توزيع (30/صفحة)</span>
              <?= pill('Print', 'muted') ?>
            </a>

            <div class="text-[11px] text-white/50 px-2 pt-2">التقارير</div>
            <a href="<?= e(admin_url('reports')) ?>"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between <?= $section==='reports'?'active':'' ?>">
              <span>تقارير (قريبًا)</span>
              <?= pill('Soon', 'muted') ?>
            </a>

            <div class="text-[11px] text-white/50 px-2 pt-2">أدوات</div>
            <a target="_blank" href="check.php"
               class="navitem rounded-2xl px-3 py-2 text-sm flex items-center justify-between">
              <span>فحص   لاتصال</span>
              <?= pill('Tool', 'muted') ?>
            </a>
          </div>

          <div class="mt-4 rounded-2xl bg-white/5 p-3 text-xs text-white/70">
            ملاحظة: تسجيل الدخول/الصلاحيات سنضيفها لاحقًا كما طلبت.
          </div>
        </div>
      </aside>
    </div>
  </div>
</body>
</html>