<?php
// =====================================================
// admin/events.php - Event decoration packages + event types
// =====================================================

require __DIR__ . '/_init.php';
$admin = require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    try {
        // ----- Packages -----
        if ($action === 'toggle_package') {
            $pdo->prepare("UPDATE event_packages SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
            flash('Package updated.');
        } elseif ($action === 'delete_package') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_bookings WHERE package_id = ?");
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $pdo->prepare("UPDATE event_packages SET is_active = 0 WHERE id = ?")->execute([$id]);
                flash('This package has bookings, so it was hidden instead of deleted.', 'warning');
            } else {
                $stmt = $pdo->prepare("SELECT image FROM event_packages WHERE id = ?");
                $stmt->execute([$id]);
                $image = $stmt->fetchColumn();
                $pdo->prepare("DELETE FROM event_packages WHERE id = ?")->execute([$id]);
                delete_image($image ?: null);
                flash('Package deleted.');
            }
        }

        // ----- Event types -----
        elseif ($action === 'add_type' || $action === 'rename_type') {
            $name = trim($_POST['name'] ?? '');
            if (mb_strlen($name) < 2) throw new RuntimeException('Enter an event type name.');
            $stmt = $pdo->prepare("SELECT id FROM event_types WHERE name = ? AND id <> ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetch()) throw new RuntimeException('This event type already exists.');
            if ($action === 'add_type') {
                $pdo->prepare("INSERT INTO event_types (name) VALUES (?)")->execute([$name]);
                flash("Event type \"$name\" added.");
            } else {
                $pdo->prepare("UPDATE event_types SET name = ? WHERE id = ?")->execute([$name, $id]);
                flash('Event type renamed.');
            }
        } elseif ($action === 'toggle_type') {
            $pdo->prepare("UPDATE event_types SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
            flash('Event type updated.');
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('events.php');
}

$packages = $pdo->query(
    "SELECT p.*, t.name AS event_type,
            (SELECT COUNT(*) FROM event_bookings b WHERE b.package_id = p.id) AS booking_count
     FROM event_packages p LEFT JOIN event_types t ON t.id = p.event_type_id
     ORDER BY p.is_active DESC, t.id, p.starting_price"
)->fetchAll();

$types = $pdo->query(
    "SELECT t.*, (SELECT COUNT(*) FROM event_packages p WHERE p.event_type_id = t.id) AS package_count
     FROM event_types t ORDER BY t.id"
)->fetchAll();

$page_title = 'Event packages';
$active     = 'events';
require __DIR__ . '/_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="fw-bold mb-0">Event decoration packages</h3>
  <a href="event_edit.php" class="btn btn-pink"><i class="bi bi-plus-lg"></i> Add package</a>
</div>

<div class="row g-3">
  <div class="col-xl-9">
    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Photo</th><th>Package</th><th>Event</th><th>From</th><th>Bookings</th><th>On website</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php if (!$packages): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">No packages yet. Click "Add package".</td></tr>
          <?php endif; ?>
          <?php foreach ($packages as $p): ?>
            <tr class="<?= $p['is_active'] ? '' : 'table-secondary text-muted' ?>">
              <td>
                <?php if ($p['image'] && is_file(__DIR__ . '/../' . $p['image'])): ?>
                  <img src="../<?= e($p['image']) ?>" class="thumb" alt="">
                <?php else: ?>
                  <div class="thumb-ph" title="No photo yet"><i class="bi bi-image"></i></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold"><?= e($p['name']) ?></div>
                <small class="text-muted"><?= e(mb_strimwidth((string) $p['description'], 0, 70, '…')) ?></small>
              </td>
              <td><?= e($p['event_type'] ?? '—') ?></td>
              <td class="text-nowrap"><?= rs($p['starting_price']) ?></td>
              <td><a href="bookings.php?package=<?= (int) $p['id'] ?>"><?= (int) $p['booking_count'] ?></a></td>
              <td>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button name="action" value="toggle_package" class="btn btn-sm <?= $p['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>">
                    <?= $p['is_active'] ? '<i class="bi bi-eye"></i> Shown' : '<i class="bi bi-eye-slash"></i> Hidden' ?>
                  </button>
                </form>
              </td>
              <td class="text-end text-nowrap">
                <a href="event_edit.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this package?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button name="action" value="delete_package" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-xl-3">
    <div class="card card-body">
      <h6 class="fw-bold">Event types</h6>
      <p class="small text-muted">These are the filter buttons on the Event decoration page.</p>
      <?php foreach ($types as $t): ?>
        <div class="d-flex gap-1 mb-2 align-items-center">
          <form method="post" class="d-flex gap-1 flex-grow-1">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <input name="name" class="form-control form-control-sm" value="<?= e($t['name']) ?>">
            <button name="action" value="rename_type" class="btn btn-sm btn-outline-secondary" title="Rename"><i class="bi bi-check-lg"></i></button>
          </form>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button name="action" value="toggle_type" class="btn btn-sm <?= $t['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>"
                    title="<?= $t['is_active'] ? 'Shown on the website, click to hide' : 'Hidden, click to show' ?>">
              <i class="bi <?= $t['is_active'] ? 'bi-eye' : 'bi-eye-slash' ?>"></i>
            </button>
          </form>
        </div>
      <?php endforeach; ?>
      <form method="post" class="d-flex gap-1 mt-2">
        <?= csrf_field() ?>
        <input name="name" class="form-control form-control-sm" placeholder="New type, e.g. Mehndi" required>
        <button name="action" value="add_type" class="btn btn-sm btn-pink">Add</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
