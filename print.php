<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';

$id = clamp_int($_GET['id'] ?? 0, 1, 999999999, 0);
if ($id <= 0) { http_response_code(400); exit('Missing id'); }

$showDesc = ($_GET['show_desc'] ?? '1') === '1';

$stmt = db()->prepare("SELECT * FROM families WHERE id=:id LIMIT 1");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); exit('Not found'); }

function val($x): string {
  $x = (string)($x ?? '');
  return $x === '' ? '—' : $x;
}
?>
<!doctype html>
<html lang="ar">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>طباعة - <?= e(APP_NAME) ?></title>
  <style>
    body{font-family:Tahoma,Arial,sans-serif;direction:rtl;text-align:right;margin:0;background:#fff;color:#000}
    .page{max-width:800px;margin:18px auto;padding:18px}
    .header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #000;padding-bottom:10px}
    .title{font-size:18px;font-weight:700}
    .meta{font-size:12px}
    table{width:100%;border-collapse:collapse;margin-top:14px}
    td,th{border:1px solid #000;padding:10px;vertical-align:top}
    th{background:#f3f3f3}
    .box{border:1px solid #000;padding:10px;margin-top:14px}
    .sign-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}
    .sign-line{height:38px;border-bottom:1px solid #000}
    .hint{font-size:12px;margin-top:10px}
    .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
    a.btn, button.btn{border:1px solid #111;background:#fff;padding:8px 12px;text-decoration:none;color:#000;cursor:pointer}
    @media print{
      .actions{display:none !important}
      .page{margin:0;max-width:none;padding:0}
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="header">
      <div>
        <div class="title"><?= e(APP_NAME) ?></div>
        <div class="meta">نموذج استلام / بيانات أسرة فقيرة</div>
      </div>
      <div class="meta">
        رقم السجل: <?= (int)$row['id'] ?><br>
        تاريخ الطباعة: <?= date('Y-m-d H:i') ?>
      </div>
    </div>

    <div class="actions">
      <a class="btn" href="admin.php?edit=<?= (int)$row['id'] ?>">رجوع للتعديل</a>
      <a class="btn" href="print.php?id=<?= (int)$row['id'] ?>&show_desc=<?= $showDesc ? '0' : '1' ?>">
        <?= $showDesc ? 'إخفاء الوصف' : 'إظهار الوصف' ?>
      </a>
      <button class="btn" onclick="window.print()">طباعة</button>
    </div>

    <table>
      <tr>
        <th style="width:30%">الاسم الرباعي</th>
        <td><?= e(val($row['full_name'])) ?></td>
      </tr>
      <tr>
        <th>رقم الهوية</th>
        <td><?= e(val($row['national_id'])) ?></td>
      </tr>
      <tr>
        <th>رقم الهاتف</th>
        <td><?= e(val($row['phone'])) ?></td>
      </tr>
      <tr>
        <th>الحالة الاجتماعية</th>
        <td><?= e(val($row['social_status'])) ?></td>
      </tr>

      <?php if ($showDesc): ?>
      <tr>
        <th>وصف الحالة</th>
        <td style="white-space:pre-wrap"><?= e(val($row['status_description'] ?? '')) ?></td>
      </tr>
      <?php endif; ?>

      <tr>
        <th>الحالة</th>
        <td><?= (int)$row['is_archived'] ? 'مؤرشف' : 'نشط' ?></td>
      </tr>
      <tr>
        <th>تاريخ الإدخال</th>
        <td><?= e(val($row['created_at'])) ?></td>
      </tr>
    </table>

    <div class="box">
      <div style="font-weight:700">إقرار استلام</div>
      <div class="hint">أقرّ أنا الموقّع أدناه باستلام المساعدة/المخصصات حسب نظام اللجنة.</div>

      <div class="sign-grid">
        <div>
          <div>اسم المستلم:</div>
          <div class="sign-line"></div>
        </div>
        <div>
          <div>رقم الهوية:</div>
          <div class="sign-line"></div>
        </div>
        <div>
          <div>التاريخ:</div>
          <div class="sign-line"></div>
        </div>
        <div>
          <div>التوقيع:</div>
          <div class="sign-line"></div>
        </div>
      </div>
    </div>

    <div class="hint">
      ملاحظة: يمكن اختيار إظهار/إخفاء الوصف من زر "إظهار/إخفاء الوصف" قبل الطباعة.
    </div>
  </div>
</body>
</html>