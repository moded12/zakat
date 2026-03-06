<?php
/**
 * Shared page top: HTML head + CSS + top header bar
 * Expected variables (set before including):
 *   $pageTitle       string  – Page title shown in browser tab and heading
 *   $activePrintEntity string|null – 'families'|'orphans'|null  (null = no print button)
 *   $activePrintLabel  string|null – Arabic label for print button
 *   $ok  string – success flash message (may be empty)
 *   $err string – error flash message   (may be empty)
 */
$_orgName = defined('APP_NAME') ? APP_NAME : 'لجنة زكاة';
$_ORG_DISPLAY = '666666 زكاة مخيم حطين (نسخة جديدة)';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($_orgName) ?> - <?= e($pageTitle ?? 'لوحة الأدمن') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root{--bg1:#0b1220;--bg2:#0f172a;--glow:0 0 0 1px rgba(255,255,255,.06),0 30px 80px rgba(2,6,23,.35)}
    body{background:radial-gradient(1200px 600px at 80% -10%,rgba(59,130,246,.20),transparent 60%),radial-gradient(800px 400px at 10% 0%,rgba(168,85,247,.18),transparent 55%),radial-gradient(900px 500px at 60% 90%,rgba(16,185,129,.12),transparent 60%),linear-gradient(180deg,#f8fafc 0%,#f3f4f6 100%)}
    .glass{background:rgba(255,255,255,.70);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.60)}
    .cardx{border-radius:22px;box-shadow:0 20px 60px rgba(2,6,23,.10);border:1px solid rgba(148,163,184,.25);background:rgba(255,255,255,.88)}
    .side{background:radial-gradient(500px 300px at 30% 10%,rgba(59,130,246,.25),transparent 65%),radial-gradient(500px 300px at 80% 30%,rgba(168,85,247,.20),transparent 70%),linear-gradient(180deg,var(--bg1),var(--bg2));box-shadow:var(--glow);border:1px solid rgba(255,255,255,.10)}
    .navitem{border:1px solid transparent}
    .navitem:hover{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.10);transform:translateY(-1px);transition:.15s ease}
    .navitem.active{background:linear-gradient(90deg,rgba(59,130,246,.20),rgba(168,85,247,.16));border-color:rgba(59,130,246,.35)}
    .inpx{border-radius:18px;border:1px solid rgba(148,163,184,.35);background:rgba(255,255,255,.80);padding:10px 12px;outline:none;width:100%}
    .inpx:focus{border-color:rgba(59,130,246,.6);box-shadow:0 0 0 4px rgba(59,130,246,.18)}
    .btnx{border-radius:18px;padding:10px 14px;font-weight:800;font-size:13px;border:1px solid rgba(148,163,184,.35);background:rgba(255,255,255,.85)}
    .btnx:hover{background:rgba(255,255,255,1)}
    .btnxP{border-radius:18px;padding:10px 14px;font-weight:900;font-size:13px;color:#fff;border:1px solid rgba(59,130,246,.40);background:linear-gradient(90deg,#2563eb,#7c3aed);box-shadow:0 14px 30px rgba(37,99,235,.18)}
    .btnxP:hover{filter:brightness(1.05)}
    .btnxSm{border-radius:14px;padding:6px 10px;font-weight:700;font-size:12px;border:1px solid rgba(148,163,184,.35);background:rgba(255,255,255,.85)}
    .btnxSm:hover{background:rgba(255,255,255,1)}
    .btnxPSm{border-radius:14px;padding:6px 10px;font-weight:700;font-size:12px;color:#fff;border:1px solid rgba(59,130,246,.40);background:linear-gradient(90deg,#2563eb,#7c3aed)}
    .tablex th{background:linear-gradient(180deg,rgba(241,245,249,.95),rgba(255,255,255,.95));position:sticky;top:0;z-index:10}
    .tablex tr:nth-child(even) td{background:rgba(248,250,252,.75)}
    .tablex tr:hover td{background:rgba(219,234,254,.45)}
  </style>
</head>
<body class="text-slate-900 min-h-screen">
  <header class="sticky top-0 z-40">
    <div class="mx-auto max-w-7xl px-4 py-3">
      <div class="glass rounded-[22px] px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-2xl bg-slate-950 text-white grid place-items-center font-black">Z</div>
          <div class="leading-tight">
            <div class="font-black"><?= e($_ORG_DISPLAY) ?></div>
            <div class="text-xs text-slate-500">لوحة الإدارة</div>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <a href="./" class="btnx">صفحة العرض</a>
          <?php if (!empty($activePrintEntity) && !empty($activePrintLabel)): ?>
            <a target="_blank" href="<?= e(print_url($activePrintEntity, ['page'=>1,'archived'=>'0'])) ?>" class="btnxP">
              طباعة كشف <?= e($activePrintLabel) ?> (30/صفحة)
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <div class="mx-auto max-w-7xl px-4 py-6">
    <?php if (!empty($ok)): ?>
      <div class="mb-4 rounded-[20px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900"><?= e($ok) ?></div>
    <?php endif; ?>
    <?php if (!empty($err)): ?>
      <div class="mb-4 rounded-[20px] border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900"><?= e($err) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-12 gap-4">
      <main class="col-span-12 lg:col-span-9 order-2 lg:order-1 space-y-4">
        <div class="cardx p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="text-xs text-slate-500">لوحة الأدمن / <?= e($pageTitle ?? '') ?></div>
              <h1 class="text-xl font-black mt-1"><?= e($pageTitle ?? '') ?></h1>
            </div>
            <div class="flex flex-wrap gap-2">
              <a href="reports.php" class="btnx">تقارير</a>
              <a href="admin.php" class="btnx">لوحة التحكم</a>
            </div>
          </div>
        </div>
