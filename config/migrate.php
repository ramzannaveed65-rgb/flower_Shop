<?php
// =====================================================
// config/migrate.php - Keeps the database up to date by itself
//
// When a new version of the project needs a new column, it is
// added here. The first page that opens after an update adds the
// missing columns, so you never have to run SQL by hand
// (not on your PC, not on the hosting).
// =====================================================

const SCHEMA_VERSION = 1;

function column_exists(PDO $pdo, string $table, string $column): bool
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {      // only used for testing
        foreach ($pdo->query("PRAGMA table_info($table)") as $col) {
            if ($col['name'] === $column) return true;
        }
        return false;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?"
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

function run_migrations(PDO $pdo, array $settings): void
{
    $have = (int) ($settings['schema_version'] ?? 0);
    if ($have >= SCHEMA_VERSION) {
        return;
    }

    // ----- Version 1: things the website needs -----
    if ($have < 1) {
        add_column($pdo, 'flowers', 'old_price', 'DECIMAL(10,2) NULL');           // price before a sale (shown crossed out)
        add_column($pdo, 'flowers', 'is_featured', 'TINYINT(1) NOT NULL DEFAULT 0'); // show in "Top selling" on the home page
        add_column($pdo, 'categories', 'image', 'VARCHAR(255) NULL');              // photo for the category tile
    }

    save_setting($pdo, 'schema_version', (string) SCHEMA_VERSION);
}
