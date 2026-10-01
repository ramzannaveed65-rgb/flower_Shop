<?php
// =====================================================
// admin/categories.php - Add, rename, show/hide categories
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cid    = (int) ($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');

    try {
        if ($action === 'add' || $action === 'rename') {
            if (mb_strlen($name) < 2) {
                throw new RuntimeException('Enter a category name.');
            }
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id <> ?");
            $stmt->execute([$name, $cid]);
            if ($stmt->fetch()) {
                throw new RuntimeException('A category with this name already exists.');
            }
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$name]);
                flash("Category \"$name\" added.");
            } else {
                $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?")->execute([$name, $cid]);
                flash('Category renamed.');
            }
        } elseif ($action === 'toggle') {
            $pdo->prepare("UPDATE categories SET is_active = 1 - is_active WHERE id = ?")->execute([$cid]);
            flash('Category updated.');
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('categories.php');
}

$categories = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM flowers f WHERE f.category_id = c.id) AS flower_count
     FROM categories c ORDER BY c.name"
)->fetchAll();

$page_title = 'Categories';
$active     = 'categories';
require __DIR__ . '/_header.php';
?>

<h3 class="fw-bold mb-3">Categories</h3>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Name</th><th>Flowers</th><th>In app</th></tr></thead>
          <tbody>
          <?php foreach ($categories as $c): ?>
            <tr class="<?= $c['is_active'] ? '' : 'table-secondary' ?>">
              <td>
                <form method="post" class="d-flex gap-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <input name="name" class="form-control form-control-sm" value="<?= e($c['name']) ?>" style="max-width:260px">
                  <button name="action" value="rename" class="btn btn-sm btn-outline-secondary">Rename</button>
                </form>
              </td>
              <td><a href="flowers.php?cat=<?= (int) $c['id'] ?>"><?= (int) $c['flower_count'] ?> flowers</a></td>
              <td>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button name="action" value="toggle" class="btn btn-sm <?= $c['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                    <?= $c['is_active'] ? '<i class="bi bi-eye"></i> Shown' : '<i class="bi bi-eye-slash"></i> Hidden' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card card-body">
      <h6 class="fw-bold">Add category</h6>
      <form method="post">
        <?= csrf_field() ?>
        <input name="name" class="form-control mb-2" placeholder="e.g. Birthday" required>
        <button name="action" value="add" class="btn btn-pink w-100"><i class="bi bi-plus-lg"></i> Add</button>
      </form>
      <p class="small text-muted mt-3 mb-0">Hidden categories disappear from the filter buttons in the app.
        Their flowers stay visible under "All".</p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
