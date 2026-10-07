<?php
// =====================================================
// shop.php - All flowers, with category filter, search and sorting
//   shop.php?cat=2      one category
//   shop.php?q=rose     search
//   shop.php?sale=1     only items on sale
//   shop.php?sort=low   cheapest first (low | high | new)
// =====================================================

require __DIR__ . '/site/_init.php';

$cat  = (int) ($_GET['cat'] ?? 0);
$q    = trim((string) ($_GET['q'] ?? ''));
$sale = !empty($_GET['sale']);
$sort = in_array($_GET['sort'] ?? '', ['low', 'high'], true) ? $_GET['sort'] : 'new';

$cats    = shop_categories($pdo);
$current = null;
foreach ($cats as $c) {
    if ((int) $c['id'] === $cat) $current = $c;
}
if ($cat > 0 && !$current) {
    $cat = 0;                       // hidden or deleted category: show everything
}

$sql    = "SELECT * FROM flowers WHERE is_active = 1";
$params = [];
if ($cat > 0) {
    $sql .= " AND category_id = ?";
    $params[] = $cat;
}
if ($q !== '') {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($sale) {
    $sql .= " AND old_price IS NOT NULL AND old_price > price";
}
$sql .= " ORDER BY (stock > 0) DESC, " . ['new' => 'id DESC', 'low' => 'price ASC', 'high' => 'price DESC'][$sort];

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$flowers = $stmt->fetchAll();

// Keeps the other filters when one of them changes
$link = function (array $change) use ($cat, $q, $sale, $sort): string {
    $now = array_merge(['cat' => $cat ?: null, 'q' => $q !== '' ? $q : null, 'sale' => $sale ? 1 : null,
                        'sort' => $sort !== 'new' ? $sort : null], $change);
    $qs  = http_build_query(array_filter($now, fn($v) => $v !== null));
    return url('shop.php' . ($qs !== '' ? '?' . $qs : ''));
};

if ($q !== '')        $heading = 'Results for "' . $q . '"';
elseif ($current)     $heading = $current['name'];
elseif ($sale)        $heading = 'On sale';
else                  $heading = 'All flowers and gifts';

$page_title = $heading;
require __DIR__ . '/site/_header.php';
?>

<div class="container">
  <div class="page-head">
    <p class="crumbs"><a href="<?= url() ?>">Home</a> / <a href="<?= url('shop.php') ?>">Shop</a><?= $current ? ' / ' . e($current['name']) : '' ?></p>
    <h1><?= e($heading) ?></h1>
    <p><?= count($flowers) ?> <?= count($flowers) === 1 ? 'item' : 'items' ?><?= $sale && ($current || $q !== '') ? ' on sale' : '' ?></p>
  </div>

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 my-3">
    <nav class="chips" aria-label="Categories">
      <a class="chip <?= $cat === 0 && !$sale ? 'active' : '' ?>" href="<?= $link(['cat' => null, 'sale' => null]) ?>">All</a>
      <?php foreach ($cats as $c): ?>
        <a class="chip <?= $cat === (int) $c['id'] ? 'active' : '' ?>" href="<?= $link(['cat' => (int) $c['id']]) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a class="chip <?= $sale ? 'active' : '' ?>" href="<?= $link(['sale' => $sale ? null : 1]) ?>">On sale</a>
    </nav>
    <form method="get" action="<?= url('shop.php') ?>" class="d-flex align-items-center gap-2">
      <?php if ($cat): ?><input type="hidden" name="cat" value="<?= $cat ?>"><?php endif; ?>
      <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
      <?php if ($sale): ?><input type="hidden" name="sale" value="1"><?php endif; ?>
      <label for="sort" class="text-muted small text-nowrap">Sort by</label>
      <select id="sort" name="sort" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="new"  <?= $sort === 'new' ? 'selected' : '' ?>>Newest</option>
        <option value="low"  <?= $sort === 'low' ? 'selected' : '' ?>>Price: low to high</option>
        <option value="high" <?= $sort === 'high' ? 'selected' : '' ?>>Price: high to low</option>
      </select>
      <noscript><button class="btn btn-sm btn-outline-leaf">Sort</button></noscript>
    </form>
  </div>

  <?php if ($flowers): ?>
    <div class="products mb-4">
      <?php foreach ($flowers as $f) echo product_card($f); ?>
    </div>
  <?php else: ?>
    <div class="empty">
      <i class="bi bi-search"></i>
      <?php if ($q !== ''): ?>
        Nothing matches "<?= e($q) ?>". Try a shorter word, like "rose" or "bouquet".
      <?php else: ?>
        Nothing here yet. New flowers are added often.
      <?php endif; ?>
      <p class="mt-3"><a class="btn btn-rose" href="<?= url('shop.php') ?>">See all flowers</a></p>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/site/_footer.php'; ?>
