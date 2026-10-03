<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <script>document.documentElement.setAttribute('data-bs-theme','light');document.documentElement.setAttribute('data-theme','light');</script>
  <title><?= e($title ?? 'Print') ?></title>
  <style>
    body { background: #fff !important; color: #111 !important; }
    .sheet { max-width: 820px; margin: 0 auto; padding: 24px; }
    .sheet.receipt { max-width: 340px; padding: 8px; font-size: 12px; }
    @media screen { .sheet { overflow-x: auto; } }
    @media print { .no-print { display: none !important; } .sheet { padding: 0; max-width: none; } .sheet.receipt { max-width: 80mm; } }
  </style>
</head>
<body>
  <div class="sheet <?= !empty($receipt) ? 'receipt' : '' ?>"><?= $content ?></div>
  <?php require __DIR__ . '/foot.php'; ?>
</body>
</html>
