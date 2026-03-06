<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';

csrf_init();

$ok = flash_get('ok') ?? '';
$err = flash_get('err') ?? '';

function norm_header(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
  return $s;
}

function map_headers(array $headerRow): array {
  $map = [];

  foreach ($headerRow as $i => $h) {
    $h = norm_header((string)$h);

    // تجاهل عمود الترقيم
    if ($h === '' || $h === '#' || $h === 'م' || $h === 'رقم' || $h === 'تسلسل') {
      continue;
    }

    if ($h === 'الاسم') $map[$i] = 'full_name';
    elseif ($h === 'رقم هوية' || $h === 'رقم الهوية' || $h === 'رقم هويه') $map[$i] = 'national_id';
    elseif ($h === 'الهاتف' || $h === 'رقم الهاتف') $map[$i] = 'phone';
    elseif ($h === 'التوقيع') $map[$i] = 'signature';
    elseif ($h === 'ملاحظات' || $h === 'ملاحظة') $map[$i] = 'notes';
  }

  if (!in_array('full_name', $map, true)) {
    throw new RuntimeException('لم يتم العثور على عمود "الاسم" في ملف CSV.');
  }

  return $map;
}

function find_header_row(string $path): array {
  $fh = fopen($path, 'r');
  if (!$fh) throw new RuntimeException('تعذر قراءة الملف');

  $lineNo = 0;
  while (($row = fgetcsv($fh)) !== false) {
    $lineNo++;
    $cells = array_map(fn($c) => norm_header((string)$c), $row);

    $hasName = in_array('الاسم', $cells, true);
    $hasId = in_array('رقم هوية', $cells, true) || in_array('رقم الهوية', $cells, true) || in_array('رقم هويه', $cells, true);
    $hasPhone = in_array('الهاتف', $cells, true) || in_array('رقم الهاتف', $cells, true);

    if ($hasName && ($hasId || $hasPhone)) {
      fclose($fh);
      return [$row, $lineNo];
    }
  }

  fclose($fh);
  throw new RuntimeException('لم يتم العثور على صف عناوين الأعمدة (Header). تأكد أن الملف يحتوي أعمدة: الاسم، رقم هوية، الهاتف.');
}

function csv_data_iter(string $path, int $headerLineNo): Generator {
  $fh = fopen($path, 'r');
  if (!$fh) throw new RuntimeException('تعذر قراءة الملف');

  $lineNo = 0;
  while (($row = fgetcsv($fh)) !== false) {
    $lineNo++;
    if ($lineNo <= $headerLineNo) continue;

    if (count($row) === 1 && trim((string)$row[0]) === '') continue;
    yield $row;
  }
  fclose($fh);
}

function read_preview(string $path, int $headerLineNo, int $rows = 8): array {
  $out = [];
  $i = 0;
  foreach (csv_data_iter($path, $headerLineNo) as $r) {
    $out[] = $r;
    $i++;
    if ($i >= $rows) break;
  }
  return $out;
}

$preview = [];
$headerLineNo = null;
$mapping = null;

if (isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
  try {
    csrf_verify();

    $tmp = $_FILES['csv']['tmp_name'];
    $name = $_FILES['csv']['name'] ?? 'file.csv';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext !== 'csv') throw new RuntimeException('ارفع ملف CSV فقط. من Excel اختر Save As -> CSV');

    [$headerRow, $headerLineNo] = find_header_row($tmp);
    $mapping = map_headers($headerRow);

    $preview = [];
    $preview[] = ['(HEADER)', ...$headerRow];
    foreach (read_preview($tmp, $headerLineNo, 8) as $r) $preview[] = $r;

    if (($_POST['do_import'] ?? '') === '1') {
      $pdo = db();
      $pdo->beginTransaction();

      $inserted = 0;
      $updated = 0;
      $skipped = 0;

      $stmt = $pdo->prepare("
        INSERT INTO orphans (full_name, national_id, phone, signature, notes)
        VALUES (:full_name, :national_id, :phone, :signature, :notes)
        ON DUPLICATE KEY UPDATE
          full_name=VALUES(full_name),
          phone=VALUES(phone),
          signature=VALUES(signature),
          notes=VALUES(notes)
      ");

      foreach (csv_data_iter($tmp, $headerLineNo) as $row) {
        $assoc = [
          'full_name' => '',
          'national_id' => null,
          'phone' => null,
          'signature' => null,
          'notes' => null,
        ];

        foreach ($mapping as $idx => $key) {
          $assoc[$key] = trim((string)($row[$idx] ?? ''));
        }

        if ($assoc['full_name'] === '') { $skipped++; continue; }

        $nid = trim((string)$assoc['national_id']);
        $phone = trim((string)$assoc['phone']);

        $stmt->execute([
          ':full_name' => $assoc['full_name'],
          ':national_id' => ($nid === '' ? null : $nid),
          ':phone' => ($phone === '' ? null : $phone),
          ':signature' => ($assoc['signature'] === '' ? null : $assoc['signature']),
          ':notes' => ($assoc['notes'] === '' ? null : $assoc['notes']),
        ]);

        $rc = (int)$pdo->query("SELECT ROW_COUNT() AS c")->fetch()['c'];
        if ($rc === 1) $inserted++;
        elseif ($rc === 2) $updated++;
        else $updated++;
      }

      $pdo->commit();
      flash_set('ok', "تم الاستيراد. جديد: $inserted - تحديث: $updated - متجاهل: $skipped");
      redirect('import-orphans.php');
    }
  } catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    flash_set('err', $e->getMessage());
    redirect('import-orphans.php');
  }
}
?>
<!doctype html>
<html lang="ar">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?> - استيراد الأيتام (CSV)</title>
  <link rel="stylesheet" href="assets/rtl.css">
</head>
<body>
  <div class="nav">
    <div class="container">
      <a class="brand" href="./"><?= e(APP_NAME) ?></a>
      <a href="admin.php">لوحة الإدارة</a>
      <a href="import-orphans.php">استيراد الأيتام (CSV)</a>
    </div>
  </div>

  <div class="container">
    <?php if ($ok): ?><div class="alert alert-ok"><?= e($ok) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>

    <div class="card">
      <h2 style="margin:0 0 8px 0">استيراد الأيتام من Excel عبر CSV</h2>
      <p class="hint">ارفع CSV (حتى لو يوجد عنوان أعلى الجدول). سيتم اكتشاف الأعمدة: الاسم / رقم هوية / الهاتف / التوقيع.</p>

      <form method="post" enctype="multipart/form-data" class="grid">
        <?= csrf_field() ?>
        <input type="hidden" name="do_import" value="0">

        <div style="grid-column:1/-1">
          <label>رفع ملف CSV</label>
          <input class="input" type="file" name="csv" accept=".csv" required>
        </div>

        <div class="row" style="grid-column:1/-1">
          <button class="btn btn-primary" type="submit">معاينة</button>
        </div>
      </form>
    </div>

    <?php if ($preview): ?>
      <div class="card" style="margin-top:12px">
        <h2 style="margin:0 0 8px 0">معاينة</h2>
        <p class="hint">بعد التأكد من المعاينة، ارفع نفس الملف مرة ثانية واضغط “استيراد نهائي”.</p>

        <table class="table">
          <tbody>
          <?php foreach ($preview as $i => $line): ?>
            <tr>
              <td style="width:70px">#<?= $i+1 ?></td>
              <td><pre style="margin:0;white-space:pre-wrap"><?= e(implode(' | ', array_map('strval', $line))) ?></pre></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <form method="post" enctype="multipart/form-data" style="margin-top:10px">
          <?= csrf_field() ?>
          <input type="hidden" name="do_import" value="1">
          <div>
            <label>ارفع نفس الملف للاستيراد النهائي</label>
            <input class="input" type="file" name="csv" accept=".csv" required>
          </div>
          <div class="row" style="margin-top:10px">
            <button class="btn btn-primary" type="submit">استيراد نهائي</button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>