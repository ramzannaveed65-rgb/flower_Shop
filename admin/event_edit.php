<?php
// =====================================================
// admin/event_edit.php        - Add an event decoration package
// admin/event_edit.php?id=2   - Edit a package
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

$id  = (int) ($_GET['id'] ?? 0);
$pkg = ['event_type_id' => '', 'name' => '', 'description' => '', 'starting_price' => '', 'image' => '', 'is_active' => 1];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM event_packages WHERE id = ?");
    $stmt->execute([$id]);
    $pkg = $stmt->fetch();
    if (!$pkg) {
        flash('Package not found', 'danger');
        redirect('events.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pkg['event_type_id']  = (int) ($_POST['event_type_id'] ?? 0) ?: null;
    $pkg['name']           = trim($_POST['name'] ?? '');
    $pkg['description']    = trim($_POST['description'] ?? '');
    $pkg['starting_price'] = trim($_POST['starting_price'] ?? '');
    $pkg['is_active']      = isset($_POST['is_active']) ? 1 : 0;

    if (!$pkg['event_type_id'])                                                    $errors[] = 'Choose the event type.';
    if (mb_strlen($pkg['name']) < 3)                                               $errors[] = 'Enter the package name.';
    if (!is_numeric($pkg['starting_price']) || (float) $pkg['starting_price'] <= 0) $errors[] = 'Enter a starting price greater than 0.';

    $has_new_photo = isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE;

    if (!$errors) {
        try {
            $old_image = $pkg['image'];
            if ($has_new_photo) {
                $pkg['image'] = save_image($_FILES['image'], $pkg['name'], 'events');
            }
            $values = [$pkg['event_type_id'], $pkg['name'], $pkg['description'],
                       (float) $pkg['starting_price'], $pkg['image'] ?? '', $pkg['is_active']];

            if ($id === 0) {
                $pdo->prepare(
                    "INSERT INTO event_packages (event_type_id, name, description, starting_price, image, is_active)
                     VALUES (?, ?, ?, ?, ?, ?)"
                )->execute($values);
                flash('Package added. It is now in the app\'s Events tab.');
            } else {
                $values[] = $id;
                $pdo->prepare(
                    "UPDATE event_packages SET event_type_id = ?, name = ?, description = ?, starting_price = ?,
                            image = ?, is_active = ? WHERE id = ?"
                )->execute($values);
                if ($has_new_photo && $old_image !== $pkg['image']) {
                    delete_image($old_image);
                }
                flash('Package saved.');
            }
            redirect('events.php');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$types     = $pdo->query("SELECT id, name FROM event_types ORDER BY id")->fetchAll();
$has_photo = $pkg['image'] && is_file(__DIR__ . '/../' . $pkg['image']);

$page_title = $id ? 'Edit package' : 'Add package';
$active     = 'events';
require __DIR__ . '/_header.php';
?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="events.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <h3 class="fw-bold mb-0"><?= $id ? 'Edit package' : 'Add event package' ?></h3>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="row g-3">
  <?= csrf_field() ?>
  <div class="col-lg-8">
    <div class="card card-body">
      <div class="row">
        <div class="col-md-8 mb-3">
          <label class="form-label">Package name *</label>
          <input name="name" class="form-control" value="<?= e($pkg['name']) ?>" placeholder="e.g. Barat Stage Decoration" required>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Event type *</label>
          <select name="event_type_id" class="form-select" required>
            <option value="">— Choose —</option>
            <?php foreach ($types as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= (int) $pkg['event_type_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mb-3" style="max-width: 260px">
        <label class="form-label">Starting price (Rs) *</label>
        <input name="starting_price" type="number" min="1" step="1" class="form-control"
               value="<?= e($pkg['starting_price'] !== '' ? (float) $pkg['starting_price'] : '') ?>" required>
        <div class="form-text">Shown as "From Rs …". The final price is agreed with the customer.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">What's included</label>
        <textarea name="description" rows="5" class="form-control"
                  placeholder="Stage backdrop with fresh roses, entrance arch, 10 table centrepieces..."><?= e($pkg['description']) ?></textarea>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" id="act" <?= $pkg['is_active'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="act">Show in the app</label>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card card-body">
      <label class="form-label">Photo of a past decoration</label>
      <img id="preview" src="<?= $has_photo ? '../' . e($pkg['image']) : '' ?>"
           class="rounded mb-2 border <?= $has_photo ? '' : 'd-none' ?>" style="width:100%;aspect-ratio:4/3;object-fit:cover" alt="">
      <?php if (!$has_photo): ?>
        <div id="nophoto" class="rounded mb-2 d-flex align-items-center justify-content-center"
             style="width:100%;aspect-ratio:4/3;background:#fce4ec;color:#c2185b;font-size:3rem"><i class="bi bi-balloon-heart"></i></div>
      <?php endif; ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-control"
             onchange="if(this.files[0]){var p=document.getElementById('preview');p.src=window.URL.createObjectURL(this.files[0]);p.classList.remove('d-none');var n=document.getElementById('nophoto');if(n)n.remove();}">
      <div class="form-text">JPG, PNG or WEBP, max 5 MB. A wide (landscape) photo looks best.</div>
    </div>
    <button class="btn btn-pink w-100 mt-3 py-2"><i class="bi bi-check-lg"></i> <?= $id ? 'Save changes' : 'Add package' ?></button>
  </div>
</form>

<?php require __DIR__ . '/_footer.php'; ?>
