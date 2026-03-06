<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config.php';

/**
 * Print Distribution (30 per page)
 * - Prints ONLY one entity based on URL param:
 *    ?entity=families  (default)
 *    ?entity=orphans
 *
 * If the chosen entity has 0 records, it prints blank rows (still 30)
 * and DOES NOT fallback to families.
 */

$HEADER = 'لجنة زكاة مخيم حطين';

$entity = strtolower(trim($_GET['entity'] ?? 'families'));
if (!in_array($entity, ['families', 'orphans'], true)) {
  $entity = 'families';
}

$page = clamp_int($_GET['page'] ?? 1, 1, 999999, 1);
$perPage = 30;

$q = trim($_GET['q'] ?? '');
$archived = ($_GET['archived'] ?? '0') === '1' ? 1 : 0;

$params = [':archived' => $archived];
$where = "is_archived = :archived";

if ($q !== '') {
  $where .= " AND (full_name LIKE :q OR national_id LIKE :q OR phone LIKE :q)";
  $params[':q'] = '%' . $q . '%';
}

/* families-only optional filter */
$social = trim($_GET['social_status'] ?? '');
if ($entity === 'families' && $social !== '' && is_valid_social_status($social)) {
  $where .= " AND social_status = :social";
  $params[':social'] = $social;
}

/* Count */
$stmt = db()->prepare("SELECT COUNT(*) c FROM {$entity} WHERE $where");
$stmt->execute($params);
$total = (int)$stmt->fetch()['c'];

$offset = ($page - 1) * $perPage;

/* Fetch rows */
$stmt = db()->prepare("
  SELECT full_name, national_id, phone
  FROM {$entity}
  WHERE $where
  ORDER BY full_name ASC
  LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pages = max(1, (int)ceil(max(0, $total) / $perPage));

function compact_name(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
  return $s;
}

$entityLabel = ($entity === 'orphans') ? 'الأيتام' : 'الأسر الفقيرة';
?>
<!doctype html>
<html lang="ar">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>كشف توزيع (<?= e($entityLabel) ?>) - <?= e($HEADER) ?></title>
  <style>
    @page { size: A4 portrait; margin: 7mm; }

    body{font-family:Tahoma,Arial,sans-serif;direction:rtl;text-align:right;margin:0;background:#fff;color:#000}
    .page{max-width:900px;margin:0 auto}

    .header{border:2px solid #000;padding:6px 10px;border-radius:8px;margin-bottom:6px}
    .header .t{font-size:17px;font-weight:900;text-align:center}
    .header .sub{display:flex;justify-content:space-between;gap:10px;margin-top:5px;font-size:11px}

    table{width:100%;border-collapse:collapse;table-layout:auto}
    th,td{
      border:1px solid #000;
      padding:4px 6px;
      vertical-align:middle;
      font-size:12px;
      line-height:1.15;
    }
    th{background:#f2f2f2;font-weight:800}

    /* Fill page for 30 rows */
    tbody tr{height:8.2mm;}

    .col-no{width:44px;text-align:center;white-space:nowrap}
    .col-nid{width:155px;white-space:nowrap}
    .col-phone{width:120px;white-space:nowrap}
    .col-name{
      white-space:nowrap;
      max-width: 300px;
      overflow:hidden;
      text-overflow:ellipsis;
    }
    .col-sign{width:auto}
    td.col-sign{position:relative}
    td.col-sign::after{
      content:"";
      position:absolute;
      left:10px; right:10px;
      bottom:7px;
      border-bottom:1px solid rgba(0,0,0,.35);
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="header">
      <div class="t"><?= e($HEADER) ?></div>
      <div class="sub">
        <div>
          كشف توزيع / توقيع استلام — <?= e($entityLabel) ?> — نوع الزكاة ( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )
        </div>
        <div>
          التاريخ: <?= date('Y-m-d') ?> |
          الصفحة: <?= (int)$page ?> / <?= (int)$pages ?> |
          عدد الأسماء: <?= (int)$perPage ?>
        </div>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th class="col-no">الرقم</th>
          <th class="col-name">الاسم (سطر واحد)</th>
          <th class="col-nid">رقم الهوية</th>
          <th class="col-phone">الهاتف</th>
          <th class="col-sign">التوقيع</th>
        </tr>
      </thead>
      <tbody>
        <?php
          $startNo = ($page - 1) * $perPage + 1;

          foreach ($rows as $i => $r):
            $name = compact_name((string)$r['full_name']);
        ?>
          <tr>
            <td class="col-no"><?= (int)($startNo + $i) ?></td>
            <td class="col-name" title="<?= e($name) ?>"><?= e($name) ?></td>
            <td class="col-nid"><?= e((string)($r['national_id'] ?? '')) ?></td>
            <td class="col-phone"><?= e((string)($r['phone'] ?? '')) ?></td>
            <td class="col-sign"></td>
          </tr>
        <?php endforeach; ?>

        <?php
          // pad to 30 rows always
          $missing = $perPage - count($rows);
          for ($k=0; $k<$missing; $k++):
        ?>
          <tr>
            <td class="col-no"><?= (int)($startNo + count($rows) + $k) ?></td>
            <td class="col-name"></td>
            <td class="col-nid"></td>
            <td class="col-phone"></td>
            <td class="col-sign"></td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
</body>
</html>