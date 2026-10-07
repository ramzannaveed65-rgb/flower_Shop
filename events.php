<?php
// =====================================================
// events.php - Event decoration packages (wedding, birthday, engagement ...)
// The customer sends a booking request; the shop calls back with the price.
// =====================================================

require __DIR__ . '/site/_init.php';

$types = $pdo->query("SELECT * FROM event_types WHERE is_active = 1 ORDER BY id")->fetchAll();
$type  = (int) ($_GET['type'] ?? 0);

$sql    = "SELECT p.*, t.name AS event_type FROM event_packages p
           LEFT JOIN event_types t ON t.id = p.event_type_id
           WHERE p.is_active = 1";
$params = [];
if ($type > 0) {
    $sql .= " AND p.event_type_id = ?";
    $params[] = $type;
}
$sql .= " ORDER BY p.starting_price ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$packages = $stmt->fetchAll();

$page_title       = 'Event decoration';
$page_description = "Fresh flower decoration for weddings, birthdays and engagements by $shop_name. See packages and send a booking request.";
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="page-head">
    <h1>Event decoration</h1>
    <p>Choose a package and send us the date and place. We call you to talk through the details and agree the final price. You pay nothing on the website.</p>
  </div>

  <?php if ($types): ?>
    <nav class="chips my-3" aria-label="Event types">
      <a class="chip <?= $type === 0 ? 'active' : '' ?>" href="<?= url('events.php') ?>">All</a>
      <?php foreach ($types as $t): ?>
        <a class="chip <?= $type === (int) $t['id'] ? 'active' : '' ?>" href="<?= url('events.php?type=' . (int) $t['id']) ?>"><?= e($t['name']) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if ($packages): ?>
    <div class="row g-4">
      <?php foreach ($packages as $p): $img = photo($p['image']); ?>
        <div class="col-md-6 col-lg-4">
          <article class="package">
            <div class="package-photo">
              <?php if ($img): ?><img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy"><?php else: ?><span class="no-photo"><i class="bi bi-stars"></i></span><?php endif; ?>
            </div>
            <div class="package-body">
              <?php if ($p['event_type']): ?><p class="text-muted small mb-1"><?= e($p['event_type']) ?></p><?php endif; ?>
              <h3 class="fs-5"><?= e($p['name']) ?></h3>
              <p class="mb-2"><strong style="color:var(--leaf)">From <?= rs($p['starting_price']) ?></strong></p>
              <?php if (trim((string) $p['description']) !== ''): ?><p class="text-muted"><?= e($p['description']) ?></p><?php endif; ?>
              <a class="btn btn-rose" href="<?= url('book-event.php?package=' . (int) $p['id']) ?>">Request this package</a>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty"><i class="bi bi-stars"></i>No packages are listed here yet. Tell us what you have in mind.</div>
  <?php endif; ?>

  <?php if ($types): ?>
    <div class="panel mt-5" style="background:var(--mist);border-color:transparent">
      <h2>Want something different?</h2>
      <p class="mb-3">Describe your event and we will plan the decoration around it.</p>
      <a class="btn btn-leaf" href="<?= url('book-event.php') ?>">Send a custom request</a>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
