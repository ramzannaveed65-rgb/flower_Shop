<?php
// =====================================================
// admin/flower_edit.php        - Add a new flower
// admin/flower_edit.php?id=3   - Edit a flower
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

$id     = (int) ($_GET['id'] ?? 0);
$flower = [
    'name' => '', 'category_id' => '', 'description' => '', 'price' => '',
    'stock' => '10', 'same_day_available' => 1, 'is_active' => 1, 'image' => '',
    'old_price' => '', 'is_featured' => 0,
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM flowers WHERE id = ?");
    $stmt->execute([$id]);
    $flower = $stmt->fetch();
    if (!$flower) {
        flash('Flower not found', 'danger');
        redirect('flowers.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the form (keep values so the form refills if there is an error)
    $flower['name']               = trim($_POST['name'] ?? '');
    $flower['category_id']        = (int) ($_POST['category_id'] ?? 0) ?: null;
    $flower['description']        = trim($_POST['description'] ?? '');
    $flower['price']              = trim($_POST['price'] ?? '');
    $flower['stock']              = trim($_POST['stock'] ?? '');
    $flower['same_day_available'] = isset($_POST['same_day_available']) ? 1 : 0;
    $flower['is_active']          = isset($_POST['is_active']) ? 1 : 0;
    $flower['old_price']          = trim($_POST['old_price'] ?? '');
    $flower['is_featured']        = isset($_POST['is_featured']) ? 1 : 0;

    if (mb_strlen($flower['name']) < 2)                                     $errors[] = 'Enter the flower name.';
    if (!is_numeric($flower['price']) || (float) $flower['price'] <= 0)     $errors[] = 'Enter a price greater than 0.';
    if (!ctype_digit($flower['stock']))                                     $errors[] = 'Stock must be a whole number (0 or more).';
    if ($flower['old_price'] !== '' && (!is_numeric($flower['old_price']) || (float) $flower['old_price'] <= (float) $flower['price'])) {
        $errors[] = 'The price before sale must be higher than the price. Leave it empty if the flower is not on sale.';
    }

    $has_new_photo = isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;
    if ($id === 0 && !$has_new_photo) {
        $errors[] = 'Choose a photo for the new flower.';
    }

    if (!$errors) {
        try {
            $old_image = $flower['image'];
            if ($has_new_photo) {
                $flower['image'] = save_image($_FILES['image'], $flower['name']);
            }

            $values = [
                $flower['category_id'], $flower['name'], $flower['description'],
                (float) $flower['price'], (int) $flower['stock'],
                $flower['same_day_available'], $flower['is_active'], $flower['image'],
                $flower['old_price'] !== '' ? (float) $flower['old_price'] : null, $flower['is_featured'],
            ];

            if ($id === 0) {
                $pdo->prepare(
                    "INSERT INTO flowers (category_id, name, description, price, stock, same_day_available, is_active, image, old_price, is_featured)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute($values);
                flash('Flower added. It is now on the website.');
            } else {
                $values[] = $id;
                $pdo->prepare(
                    "UPDATE flowers SET category_id = ?, name = ?, description = ?, price = ?, stock = ?,
                            same_day_available = ?, is_active = ?, image = ?, old_price = ?, is_featured = ? WHERE id = ?"
                )->execute($values);
                if ($has_new_photo && $old_image !== $flower['image']) {
                    delete_image($old_image);
                }
                flash('Flower saved.');
            }
            redirect('flowers.php');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$has_photo  = $flower['image'] && is_file(__DIR__ . '/../' . $flower['image']);

$page_title = $id ? 'Edit ' . $flower['name'] : 'Add flower';
$active     = 'flowers';
require __DIR__ . '/_header.php';
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="flowers.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h3 class="fw-bold mb-0"><?= $id ? 'Edit flower' : 'Add flower' ?></h3>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="row g-3">
  <?= csrf_field() ?>
  <div class="col-lg-8">
    <div class="card card-body">
      <div class="mb-3">
        <label class="form-label">Name *</label>
        <input name="name" class="form-control" value="<?= e($flower['name']) ?>" placeholder="e.g. Red Roses (12 stems)" required>
      </div>
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">Price (Rs) *</label>
          <input name="price" type="number" step="1" min="1" class="form-control" value="<?= e($flower['price'] !== '' ? (float) $flower['price'] : '') ?>" required>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Price before sale (Rs)</label>
          <input name="old_price" type="number" step="1" min="1" class="form-control" value="<?= e(($flower['old_price'] ?? '') !== '' && $flower['old_price'] !== null ? (float) $flower['old_price'] : '') ?>" placeholder="Empty = not on sale">
          <div class="form-text">Shown crossed out next to the price.</div>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Stock *</label>
          <input name="stock" type="number" min="0" step="1" class="form-control" value="<?= e($flower['stock']) ?>" required>
        </div>
        <div class="col-md-12 mb-3">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-select">
            <option value="0">— None —</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) $flower['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" rows="4" class="form-control" placeholder="What is included, size, colours..."><?= e($flower['description']) ?></textarea>
      </div>
      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" name="same_day_available" id="sd" <?= $flower['same_day_available'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="sd">Available for same-day delivery</label>
      </div>
      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" name="is_featured" id="feat" <?= !empty($flower['is_featured']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="feat">Show in "Top selling" on the home page</label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" id="act" <?= $flower['is_active'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="act">Show on the website</label>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card card-body">
      <label class="form-label">Photo <?= $id ? '' : '*' ?></label>
      <img id="preview" src="<?= $has_photo ? '../' . e($flower['image']) : '' ?>"
           class="rounded mb-2 border <?= $has_photo ? '' : 'd-none' ?>" style="width:100%;aspect-ratio:1;object-fit:cover" alt="">
      <?php if (!$has_photo): ?>
        <div id="nophoto" class="rounded mb-2 d-flex align-items-center justify-content-center"
             style="width:100%;aspect-ratio:1;background:#fce4ec;color:#c2185b;font-size:3rem"><i class="bi bi-image"></i></div>
      <?php endif; ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control"
             onchange="if(this.files[0]){var p=document.getElementById('preview');p.src=window.URL.createObjectURL(this.files[0]);p.classList.remove('d-none');var n=document.getElementById('nophoto');if(n)n.remove();}">
      <div class="form-text">JPG, PNG or WEBP, max 5 MB. A square photo looks best.</div>
    </div>
    <button class="btn btn-pink w-100 mt-3 py-2"><i class="bi bi-check-lg"></i> <?= $id ? 'Save changes' : 'Add flower' ?></button>
  </div>
</form>

<?php require __DIR__ . '/_footer.php'; ?>
