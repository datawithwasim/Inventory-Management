<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <title><?= e($title ?? 'Print') ?></title>
  <style>
    body { background: #fff; }
    .sheet { max-width: 820px; margin: 0 auto; padding: 24px; }
    .sheet.receipt { max-width: 340px; padding: 8px; font-size: 12px; }
    @media print { .no-print { display: none !important; } .sheet { padding: 0; max-width: none; } .sheet.receipt { max-width: 80mm; } }
  </style>
</head>
<body>
  <div class="sheet <?= !empty($receipt) ? 'receipt' : '' ?>"><?= $content ?></div>
  <?php require __DIR__ . '/foot.php'; ?>
</body>
</html>
