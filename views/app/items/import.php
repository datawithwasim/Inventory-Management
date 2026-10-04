<div class="row g-3"><div class="col-lg-7"><div class="card"><div class="card-body">
  <h2 class="h6">Upload a CSV file</h2>
  <form method="post" action="<?= url('items/import') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
    <div class="mb-3"><input type="file" name="file" accept=".csv,text/csv" class="form-control" required></div>
    <button class="btn btn-primary">Import</button> <a class="btn btn-link" href="<?= url('items') ?>">Cancel</a>
  </form>
  <p class="text-muted small mt-3 mb-0">All rows are checked first. If anything is wrong nothing is imported and you will see what to fix.</p>
</div></div></div>
<div class="col-lg-5"><div class="card"><div class="card-body">
  <h2 class="h6">File format</h2>
  <p class="small">One row per variant. Leave <code>item_name</code> blank on following rows to add more variants to the same <?= e(term('item', true)) ?>.</p>
  <p class="small mb-2"><code><?= e(implode(', ', $columns)) ?></code></p>
  <ul class="small text-muted ps-3">
    <li><?= e(term('racks')) ?> are not part of this file: add stock and choose <?= e(term('racks', true)) ?> under Stock → Adjustments.</li>
    <li><code>unit</code> and <code>tax</code> must already exist (Masters). Categories and brands are created automatically.</li>
    <li><code>item_type</code>: fabric, linen, wallpaper, carpet, accessory or other. Blank means other.</li>
    <li><code>hsn_code, design_no, composition, width, gsm, pattern, finish</code> describe the item; <code>colour</code> and <code>size</code> belong to each variant row.</li>
    <li><code>track_batch</code>: yes / no (one roll = one <?= e(term('batch', true)) ?>). If left blank, fabric, wallpaper and carpet are tracked by roll.</li>
    <li>Older files without the new columns still import.</li>
    <li>Blank <code>sku</code> is generated for you.</li>
    <li>From Excel: File → Save As → CSV.</li>
  </ul>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url('items/import/template') ?>"><i class="bi bi-download"></i> Download template</a>
</div></div></div></div>
