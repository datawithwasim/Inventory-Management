<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <title><?= e(($title ?? 'Form designer') . ' · ' . Core\Auth::user()['tenant_name']) ?></title>
</head>
<body class="app dz-page">
<?= $content ?>
<script src="<?= asset('lib/bootstrap/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
