<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';

ensure_tables();

$ok  = flash_get('ok') ?? '';
$err = flash_get('err') ?? '';

/* ── POST actions ─────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_verify();
  $action = trim($_POST['action'] ?? '');
  $id     = (int)($_POST['id'] ?? 0);

  try {
    if (in_array($action, ['archive', 'unarchive'], true)) {
      if ($id <= 0) throw new RuntimeException('ID غير صحيح');
      $v = $action === 'archive' ? 1 : 0;
      db()->prepare("UPDATE orphan_sponsorships SET is_archived=:a WHERE id=:id")->execute([':a'=>$v,':id'=>$id]);
      flash_set('ok', $action === 'archive' ? 'تمت الأرشفة' : 'تم إلغاء الأرشفة');
      redirect(page_url('orphan-sponsorships.php'));
    }

    if (in_array($action, ['create', 'update'], true)) {
      $full_name          = trim($_POST['full_name'] ?? '');
      $national_id        = trim($_POST['national_id'] ?? '');
      $phone              = trim($_POST['phone'] ?? '');
      $social_status      = trim($_POST['social_status'] ?? '');
      $status_description = trim($_POST['status_description'] ?? '');
      $sponsor_name       = trim($_POST['sponsor_name'] ?? '');
      $sponsorship_amount = trim($_POST['sponsorship_amount'] ?? '');

      if ($full_name === '') throw new RuntimeException('الرجاء تعبئة: الاسم');
      if (!is_valid_social_status($social_status)) throw new RuntimeException('الحالة الاجتماعية غير صحيحة');

      $amount = ($sponsorship_amount !== '' && is_numeric($sponsorship_amount)) ? (float)$sponsorship_amount : null;

      if ($action === 'create') {
        db()->prepare("INSERT INTO orphan_sponsorships (full_name, national_id, phone, social_status, status_description, sponsor_name, sponsorship_amount) VALUES (:n,:nid,:p,:s,:d,:sn,:sa)")
          ->execute([
            ':n'  => $full_name,
            ':nid'=> ($national_id===''?null:$national_id),
            ':p'  => ($phone===''?null:$phone),
            ':s'  => $social_status,
            ':d'  => ($status_description===''?null:$status_description),
            ':sn' => ($sponsor_name===''?null:$sponsor_name),
            ':sa' => $amount,
          ]);
        flash_set('ok', 'تمت إضافة سجل كفالة اليتيم');
        redirect(page_url('orphan-sponsorships.php'));
      } else {
        if ($id <= 0) throw new RuntimeException('ID غير صحيح');
        db()->prepare("UPDATE orphan_sponsorships SET full_name=:n, national_id=:nid, phone=:p, social_status=:s, status_description=:d, sponsor_name=:sn, sponsorship_amount=:sa WHERE id=:id")
          ->execute([
            ':id' => $id,
            ':n'  => $full_name,
            ':nid'=> ($national_id===''?null:$national_id),
            ':p'  => ($phone===''?null:$phone),
            ':s'  => $social_status,
            ':d'  => ($status_description===''?null:$status_description),
            ':sn' => ($sponsor_name===''?null:$sponsor_name),
            ':sa' => $amount,
          ]);
        flash_set('ok', 'تم تعديل سجل الكفالة');
        redirect(page_url('orphan-sponsorships.php', ['edit'=>$id]));
      }
    }

    if ($action === 'add_receipt') {
      $ben_id = (int)($_POST['beneficiary_id'] ?? 0);
      $period = trim($_POST['period_month'] ?? '');
      $items  = trim($_POST['items'] ?? '');
      $notes  = trim($_POST['notes'] ?? '');
      if ($ben_id <= 0 || $period === '' || $items === '') {
        throw new RuntimeException('الرجاء تعبئة: الفترة / الكفالات/المواد المستلمة');
      }
      if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
        throw new RuntimeException('صيغة الفترة غير صحيحة (YYYY-MM)');
      }
      db()->prepare("INSERT INTO monthly_receipts (beneficiary_type, beneficiary_id, period_month, items, notes) VALUES ('sponsorship',:bid,:pm,:it,:no)")
        ->execute([':bid'=>$ben_id,':pm'=>$period,':it'=>$items,':no'=>($notes===''?null:$notes)]);
      flash_set('ok', 'تم تسجيل الكفالة الشهرية');
      redirect(page_url('orphan-sponsorships.php', ['receipts_for'=>$ben_id]));
    }

    if ($action === 'delete_receipt') {
      $rid    = (int)($_POST['receipt_id'] ?? 0);
      $ben_id = (int)($_POST['beneficiary_id'] ?? 0);
      if ($rid <= 0) throw new RuntimeException('ID غير صحيح');
      db()->prepare("DELETE FROM monthly_receipts WHERE id=:id AND beneficiary_type='sponsorship'")->execute([':id'=>$rid]);
      flash_set('ok', 'تم حذف سجل الكفالة');
      redirect(page_url('orphan-sponsorships.php', ['receipts_for'=>$ben_id]));
    }

    throw new RuntimeException('عملية غير مدعومة');

  } catch (Throwable $e) {
    $msg = $e->getMessage();
    if (str_contains($msg, 'Duplicate') || str_contains($msg, '23000')) {
      $msg = 'رقم الهوية موجود مسبقًا.';
    }
    flash_set('err', $msg);
    redirect(page_url('orphan-sponsorships.php', ['edit' => ($id > 0 ? $id : null)]));
  }
}

/* ── Load data ───────────────────────────────────────────────── */
csrf_init();

$s_edit_id = clamp_int($_GET['edit'] ?? 0, 0, 999999999, 0);
$s_edit_row = null;
if ($s_edit_id > 0) {
  $stmt = db()->prepare("SELECT * FROM orphan_sponsorships WHERE id=:id LIMIT 1");
  $stmt->execute([':id' => $s_edit_id]);
  $s_edit_row = $stmt->fetch() ?: null;
}

$s_q          = trim($_GET['q'] ?? '');
$s_social     = trim($_GET['social_status'] ?? '');
$s_archivedRaw = $_GET['archived'] ?? '';
$s_archived   = ($s_archivedRaw === '0') ? 0 : (($s_archivedRaw === '1') ? 1 : -1);
$s_page       = clamp_int($_GET['page'] ?? 1, 1, 999999, 1);
$s_perPage    = clamp_int($_GET['per_page'] ?? 15, 5, 50, 15);

$params = [];
$where  = "1=1";
if ($s_archived === 0 || $s_archived === 1) { $where .= " AND is_archived=:archived"; $params[':archived'] = $s_archived; }
if ($s_social !== '' && is_valid_social_status($s_social)) { $where .= " AND social_status=:social"; $params[':social'] = $s_social; }
if ($s_q !== '') { $where .= " AND (full_name LIKE :q OR national_id LIKE :q OR phone LIKE :q OR sponsor_name LIKE :q)"; $params[':q'] = '%'.$s_q.'%'; }

$stmt = db()->prepare("SELECT COUNT(*) c FROM orphan_sponsorships WHERE $where");
$stmt->execute($params);
$s_total = (int)$stmt->fetch()['c'];

$offset = ($s_page - 1) * $s_perPage;
$stmt = db()->prepare("SELECT id,full_name,national_id,phone,social_status,status_description,sponsor_name,sponsorship_amount,is_archived,created_at FROM orphan_sponsorships WHERE $where ORDER BY id DESC LIMIT $s_perPage OFFSET $offset");
$stmt->execute($params);
$s_rows = $stmt->fetchAll();

/* Monthly receipts panel */
$receipts_for = clamp_int($_GET['receipts_for'] ?? 0, 0, 999999999, 0);
$receipts_row = null;
$receipts     = [];
if ($receipts_for > 0) {
  $stmt = db()->prepare("SELECT * FROM orphan_sponsorships WHERE id=:id LIMIT 1");
  $stmt->execute([':id' => $receipts_for]);
  $receipts_row = $stmt->fetch() ?: null;
  if ($receipts_row) {
    $stmt = db()->prepare("SELECT * FROM monthly_receipts WHERE beneficiary_type='sponsorship' AND beneficiary_id=:id ORDER BY period_month DESC");
    $stmt->execute([':id' => $receipts_for]);
    $receipts = $stmt->fetchAll();
  }
}

$pageTitle  = 'كفالة الأيتام';
$activePage = 'sponsorships';
$activePrintEntity = null;
$activePrintLabel  = null;

include __DIR__ . '/partials/page-top.php';
?>

<?php if ($receipts_for > 0 && $receipts_row): ?>
<div class="cardx p-5 border-2 border-sky-200">
  <div class="flex items-center justify-between mb-4">
    <div>
      <div class="font-black text-sky-700">سجل الكفالات المستلمة</div>
      <div class="text-sm text-slate-600 mt-1">اليتيم: <strong><?= e($receipts_row['full_name']) ?></strong></div>
      <?php if (!empty($receipts_row['sponsor_name'])): ?>
        <div class="text-sm text-slate-500 mt-1">الكافل: <?= e($receipts_row['sponsor_name']) ?></div>
      <?php endif; ?>
    </div>
    <a href="<?= e(page_url('orphan-sponsorships.php', ['edit'=>$receipts_for])) ?>" class="btnx">← رجوع</a>
  </div>

  <form method="post" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5 p-4 rounded-[18px] bg-slate-50 border border-slate-200">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_receipt">
    <input type="hidden" name="beneficiary_id" value="<?= (int)$receipts_for ?>">
    <div>
      <label class="text-xs font-bold text-slate-600">الفترة (YYYY-MM)</label>
      <input class="inpx mt-1" name="period_month" placeholder="<?= date('Y-m') ?>" value="<?= e(date('Y-m')) ?>" required>
    </div>
    <div class="sm:col-span-2">
      <label class="text-xs font-bold text-slate-600">الكفالات/المواد المستلمة</label>
      <input class="inpx mt-1" name="items" placeholder="مثال: كفالة شهرية – مستلزمات مدرسية" required>
    </div>
    <div>
      <label class="text-xs font-bold text-slate-600">ملاحظات (اختياري)</label>
      <input class="inpx mt-1" name="notes" placeholder="ملاحظات">
    </div>
    <div class="sm:col-span-2 lg:col-span-4">
      <button class="btnxP" type="submit">تسجيل كفالة</button>
    </div>
  </form>

  <?php if ($receipts): ?>
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm tablex">
      <thead>
        <tr class="text-slate-600">
          <th class="px-4 py-3 text-right font-bold">الفترة</th>
          <th class="px-4 py-3 text-right font-bold">الكفالات المستلمة</th>
          <th class="px-4 py-3 text-right font-bold">ملاحظات</th>
          <th class="px-4 py-3 text-right font-bold">التاريخ</th>
          <th class="px-4 py-3 text-right font-bold">حذف</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($receipts as $r): ?>
        <tr>
          <td class="px-4 py-3 font-bold"><?= e($r['period_month']) ?></td>
          <td class="px-4 py-3"><?= e($r['items']) ?></td>
          <td class="px-4 py-3 text-slate-500"><?= e((string)($r['notes'] ?? '')) ?></td>
          <td class="px-4 py-3 text-slate-400 text-xs"><?= e(substr($r['created_at'], 0, 10)) ?></td>
          <td class="px-4 py-3">
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_receipt">
              <input type="hidden" name="receipt_id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="beneficiary_id" value="<?= (int)$receipts_for ?>">
              <button class="btnxSm text-rose-600" type="submit">حذف</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="text-center text-slate-500 py-6">لا توجد سجلات كفالة بعد</div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
  <div class="cardx p-5">
    <div class="font-black mb-1"><?= $s_edit_row ? 'تعديل سجل الكفالة' : 'إضافة يتيم مكفول' ?></div>
    <div class="text-xs text-slate-500 mb-3">كفالة الأيتام – بيانات اليتيم والكفيل</div>

    <form method="post" class="space-y-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $s_edit_row ? 'update' : 'create' ?>">
      <?php if ($s_edit_row): ?>
        <input type="hidden" name="id" value="<?= (int)$s_edit_row['id'] ?>">
      <?php endif; ?>

      <div>
        <label class="text-sm font-bold">اسم اليتيم (رباعي)</label>
        <input class="inpx mt-1" name="full_name" required value="<?= e($s_edit_row['full_name'] ?? '') ?>">
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="text-sm font-bold">رقم الهوية (اختياري)</label>
          <input class="inpx mt-1" name="national_id" value="<?= e($s_edit_row['national_id'] ?? '') ?>">
        </div>
        <div>
          <label class="text-sm font-bold">رقم الهاتف (اختياري)</label>
          <input class="inpx mt-1" name="phone" value="<?= e($s_edit_row['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="text-sm font-bold">الحالة الاجتماعية</label>
          <?php $curS = $s_edit_row['social_status'] ?? social_status_options()[0]; ?>
          <select class="inpx mt-1" name="social_status" required>
            <?php foreach (social_status_options() as $opt): ?>
              <option value="<?= e($opt) ?>" <?= $curS === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-sm font-bold">وصف الحالة (اختياري)</label>
          <input class="inpx mt-1" name="status_description" value="<?= e((string)($s_edit_row['status_description'] ?? '')) ?>">
        </div>
      </div>

      <div class="rounded-[16px] border border-sky-200 bg-sky-50 p-3 space-y-3">
        <div class="text-xs font-bold text-sky-700 mb-1">بيانات الكفالة</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="text-sm font-bold">اسم الكافل (اختياري)</label>
            <input class="inpx mt-1" name="sponsor_name" value="<?= e((string)($s_edit_row['sponsor_name'] ?? '')) ?>" placeholder="اسم المتبرع / الكافل">
          </div>
          <div>
            <label class="text-sm font-bold">مبلغ الكفالة الشهرية (اختياري)</label>
            <input class="inpx mt-1" name="sponsorship_amount" type="number" step="0.01" min="0"
                   value="<?= e((string)($s_edit_row['sponsorship_amount'] ?? '')) ?>" placeholder="0.00">
          </div>
        </div>
      </div>

      <div class="flex flex-wrap gap-2 pt-2">
        <button class="btnxP" type="submit"><?= $s_edit_row ? 'حفظ التعديل' : 'إضافة' ?></button>
        <?php if ($s_edit_row): ?>
          <a class="btnx" href="orphan-sponsorships.php">إلغاء</a>
          <a class="btnxP" href="<?= e(page_url('orphan-sponsorships.php', ['receipts_for'=>$s_edit_row['id']])) ?>"
             style="background:linear-gradient(90deg,#0891b2,#0e7490)">الكفالات المستلمة</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="cardx p-5">
    <div class="font-black mb-3">بحث</div>
    <form method="get" class="space-y-3">
      <div>
        <label class="text-sm font-bold">بحث عام</label>
        <input class="inpx mt-1" name="q" value="<?= e($s_q) ?>" placeholder="اسم/هوية/هاتف/كافل">
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="text-sm font-bold">الحالة الاجتماعية</label>
          <select class="inpx mt-1" name="social_status">
            <option value="">الكل</option>
            <?php foreach (social_status_options() as $opt): ?>
              <option value="<?= e($opt) ?>" <?= $s_social === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-sm font-bold">الأرشفة</label>
          <select class="inpx mt-1" name="archived">
            <option value="" <?= $s_archived===-1?'selected':'' ?>>الكل</option>
            <option value="0" <?= $s_archived===0?'selected':'' ?>>نشط</option>
            <option value="1" <?= $s_archived===1?'selected':'' ?>>مؤرشف</option>
          </select>
        </div>
      </div>
      <div>
        <label class="text-sm font-bold">عدد/صفحة</label>
        <select class="inpx mt-1" name="per_page">
          <?php foreach ([10,15,20,30,50] as $n): ?>
            <option value="<?= $n ?>" <?= $s_perPage===$n?'selected':'' ?>><?= $n ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex flex-wrap gap-2 pt-2">
        <button class="btnx" type="submit">بحث</button>
        <a class="btnx" href="orphan-sponsorships.php">مسح</a>
      </div>
    </form>
  </div>
</div>

<div class="cardx overflow-hidden">
  <div class="px-5 py-4 border-b bg-slate-50 flex items-center justify-between">
    <div class="font-black">قائمة الأيتام المكفولين</div>
    <div class="text-xs text-slate-500">العدد: <?= (int)$s_total ?></div>
  </div>

  <div class="overflow-x-auto max-h-[520px]">
    <table class="min-w-full text-sm tablex">
      <thead>
        <tr class="text-slate-600">
          <th class="px-4 py-3 text-right font-bold">#</th>
          <th class="px-4 py-3 text-right font-bold">الاسم</th>
          <th class="px-4 py-3 text-right font-bold">الهوية</th>
          <th class="px-4 py-3 text-right font-bold">الهاتف</th>
          <th class="px-4 py-3 text-right font-bold">الحالة الاجتماعية</th>
          <th class="px-4 py-3 text-right font-bold">الكافل</th>
          <th class="px-4 py-3 text-right font-bold">مبلغ الكفالة</th>
          <th class="px-4 py-3 text-right font-bold">الأرشفة</th>
          <th class="px-4 py-3 text-right font-bold">إجراءات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($s_rows as $r): ?>
          <tr>
            <td class="px-4 py-3"><?= (int)$r['id'] ?></td>
            <td class="px-4 py-3 font-bold"><?= e($r['full_name']) ?></td>
            <td class="px-4 py-3"><?= e((string)($r['national_id'] ?? '')) ?></td>
            <td class="px-4 py-3"><?= e((string)($r['phone'] ?? '')) ?></td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-full border px-2 py-1 text-[11px] font-bold bg-slate-900/5 border-slate-900/10 text-slate-700">
                <?= e($r['social_status']) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-slate-600"><?= e((string)($r['sponsor_name'] ?? '')) ?></td>
            <td class="px-4 py-3 text-slate-600">
              <?php if ($r['sponsorship_amount'] !== null && $r['sponsorship_amount'] !== ''): ?>
                <?= number_format((float)$r['sponsorship_amount'], 2) ?>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <?= (int)$r['is_archived'] ? pill('مؤرشف','warn') : pill('نشط','ok') ?>
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <a class="btnxSm" href="<?= e(page_url('orphan-sponsorships.php', ['edit'=>$r['id']])) ?>">تعديل</a>
                <a class="btnxSm" href="<?= e(page_url('orphan-sponsorships.php', ['receipts_for'=>$r['id']])) ?>"
                   style="border-color:rgba(8,145,178,.4);color:#0891b2">الكفالات</a>
                <?php if (!(int)$r['is_archived']): ?>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btnxSm" type="submit">أرشفة</button>
                  </form>
                <?php else: ?>
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="unarchive">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btnxSm" type="submit">إلغاء الأرشفة</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$s_rows): ?>
          <tr><td class="px-4 py-6 text-center text-slate-500" colspan="9">لا توجد نتائج</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="px-5 py-3 border-t bg-slate-50">
    <?= render_pagination($s_page, $s_perPage, $s_total) ?>
  </div>
</div>

      </main>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
