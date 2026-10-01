<?php
// =====================================================
// admin/flowers.php - Flower list: show/hide, delete, quick stock view
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

// ----- Actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fid    = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $pdo->prepare("UPDATE flowers SET is_active = 1 - is_active WHERE id = ?")->execute([$fid]);
        flash('Flower updated.');
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE flower_id = ?");
        $stmt->execute([$fid]);
        if ((int) $stmt->fetchColumn() > 0) {
            // Keep it for old orders: just hide it
            $pdo->prepare("UPDATE flowers SET is_active = 0 WHERE id = ?")->execute([$fid]);
            flash('This flower is in old orders, so it was hidden instead of deleted.', 'warning');
        } else {
            $stmt = $pdo->prepare("SELECT image FROM flowers WHERE id = ?");
            $stmt->execute([$fid]);
            $image = $stmt->fetchColumn();
            $pdo->prepare("DELETE FROM flowers WHERE id = ?")->execute([$fid]);
            delete_image($image ?: null);
            flash('Flower deleted.');
        }
    }
    redirect('flowers.php' . (!empty($_POST['back']) ? '?' . $_POST['back'] : ''));
}

// ----- List -----
$q   = trim($_GET['q'] ?? '');
$cat = (int) ($_GET['cat'] ?? 0);

$sql    = "SELECT f.*, c.name AS category_name FROM flowers f LEFT JOIN categories c ON c.id = f.category_id WHERE 1 = 1";
$params = [];
if ($q !== '') {
    $sql .= " AND f.name LIKE ?";
    $params[] = "%$q%";
}
if ($cat > 0) {
    $sql .= " AND f.category_id = ?";
    $params[] = $cat;
}
$sql .= " ORDER BY f.is_active DESC, f.name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$flowers = $stmt->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$back       = http_build_query(array_filter(['q' => $q, 'cat' => $cat ?: null]));

$page_title = 'Flowers';
$active     = 'flowers';
require __DIR__ . '/_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="fw-bold mb-0">Flowers</h3>
  <a href="flower_edit.php" class="btn btn-pink"><i class="bi bi-plus-lg"></i> Add flower</a>
</div>

<form class="card card-body mb-3" method="get">
  <div class="row g-2">
    <div class="col-md-5"><input name="q" class="form-control" placeholder="Search by name" value="<?= e($q) ?>"></div>
    <div class="col-md-4">
      <select name="cat" class="form-select">
        <option value="0">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-outline-secondary">Filter</button>
      <a href="flowers.php" class="btn btn-link">Clear</a>
    </div>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead><tr><th>Photo</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Same day</th><th>In app</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php if (!$flowers): ?>
        <tr><td colspan="8" class="text-center text-muted py-5">No flowers found</td></tr>
      <?php endif; ?>
      <?php foreach ($flowers as $f): ?>
        <tr class="<?= $f['is_active'] ? '' : 'table-secondary text-muted' ?>">
          <td>
            <?php if (is_file(__DIR__ . '/../' . $f['image'])): ?>
              <img src="../<?= e($f['image']) ?>" class="thumb" alt="">
            <?php else: ?>
              <div class="thumb-ph" title="No photo yet"><i class="bi bi-image"></i></div>
            <?php endif; ?>
          </td>
          <td class="fw-semibold"><?= e($f['name']) ?></td>
          <td><?= e($f['category_name'] ?? '—') ?></td>
          <td><?= rs($f['price']) ?></td>
          <td>
            <?php if ((int) $f['stock'] === 0): ?><span class="badge text-bg-danger">Out of stock</span>
            <?php elseif ((int) $f['stock'] <= 3): ?><span class="badge text-bg-warning"><?= (int) $f['stock'] ?></span>
            <?php else: ?><?= (int) $f['stock'] ?><?php endif; ?>
          </td>
          <td><?= $f['same_day_available'] ? '<i class="bi bi-check-lg text-success"></i>' : '—' ?></td>
          <td>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
              <input type="hidden" name="back" value="<?= e($back) ?>">
              <button name="action" value="toggle" class="btn btn-sm <?= $f['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>"
                      title="<?= $f['is_active'] ? 'Visible in app, click to hide' : 'Hidden, click to show' ?>">
                <?= $f['is_active'] ? '<i class="bi bi-eye"></i> Shown' : '<i class="bi bi-eye-slash"></i> Hidden' ?>
              </button>
            </form>
          </td>
          <td class="text-end text-nowrap">
            <a href="flower_edit.php?id=<?= (int) $f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete <?= e(addslashes($f['name'])) ?>?')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
              <input type="hidden" name="back" value="<?= e($back) ?>">
              <button name="action" value="delete" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
