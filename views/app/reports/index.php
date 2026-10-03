<p class="text-muted">Pick a report, set the period, then view it on screen or download it as Excel / CSV, or print it as a PDF.</p>
<?php foreach ($groups as $g => $list): ?>
  <h2 class="h6 text-uppercase text-muted mt-4 mb-2"><?= e($names[$g] ?? $g) ?></h2>
  <div class="row g-3">
    <?php foreach ($list as $slug => $d): ?>
      <div class="col-md-6 col-xl-4">
        <a class="card h-100 text-decoration-none" href="<?= url('reports/' . $slug) ?>">
          <div class="card-body"><div class="fw-semibold text-body"><?= e($d['title']) ?></div><div class="small text-muted"><?= e($d['desc']) ?></div></div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
