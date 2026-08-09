<?php
/**
 * Migration runner — one command brings any database up to date.
 *
 *   php migrate.php               apply all pending migrations (in order)
 *   php migrate.php --status      list applied / pending
 *   php migrate.php --baseline    mark ALL current files as applied without
 *                                 running them (use once on a DB that was
 *                                 migrated by hand before this runner existed)
 *
 * Tracks state in `schema_migrations`. Each migration/NNN_*.sql runs once,
 * inside a best-effort transaction (DDL auto-commits in MySQL, so the real
 * safety is: fix the failing file and re-run — completed files are skipped).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

require_once __DIR__ . '/config/database.php';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        filename   VARCHAR(120) PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$files = glob(__DIR__ . '/migration/[0-9][0-9][0-9]_*.sql');
sort($files, SORT_STRING);

// SAFETY: 000_full_schema.sql is the FRESH-INSTALL base (mysqldump with DROP
// TABLE statements). It must never run via the runner against a database that
// has data — import it manually on an empty DB only, then --baseline.
$files = array_filter($files, fn($f) => basename($f) !== '000_full_schema.sql');

$applied = $pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$mode = $argv[1] ?? 'run';

if ($mode === '--status') {
    foreach ($files as $f) {
        $name = basename($f);
        echo (in_array($name, $applied, true) ? '  [applied] ' : '  [PENDING] ') . $name . "\n";
    }
    $pending = count($files) - count(array_intersect(array_map('basename', $files), $applied));
    echo count($files) . " file(s), $pending pending\n";
    exit(0);
}

if ($mode === '--baseline') {
    $n = 0;
    $ins = $pdo->prepare("INSERT IGNORE INTO schema_migrations (filename) VALUES (?)");
    foreach ($files as $f) { $ins->execute([basename($f)]); $n += $ins->rowCount(); }
    echo "Baseline: marked $n file(s) as applied (not executed).\n";
    exit(0);
}

// run
$ran = 0;
foreach ($files as $f) {
    $name = basename($f);
    if (in_array($name, $applied, true)) continue;

    echo "Applying $name … ";
    $sql = file_get_contents($f);
    // Guard: destructive statements never run automatically.
    if (preg_match('/\bDROP\s+(TABLE|DATABASE)\b/i', $sql)) {
        echo "BLOCKED — contains DROP TABLE/DATABASE; run manually if intentional.\n";
        exit(1);
    }
    // split on ; at end of statement (keeps it simple; our migrations avoid
    // stored procedures / DELIMITER blocks)
    $stmts = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));
    try {
        foreach ($stmts as $stmt) {
            if ($stmt === '' || strpos($stmt, '--') === 0) continue;
            $pdo->exec($stmt);
        }
        $pdo->prepare("INSERT INTO schema_migrations (filename) VALUES (?)")->execute([$name]);
        echo "OK\n";
        $ran++;
    } catch (PDOException $e) {
        echo "FAILED\n  " . $e->getMessage() . "\n  Fix the file and re-run; applied files are skipped.\n";
        exit(1);
    }
}
echo $ran ? "Done — $ran migration(s) applied.\n" : "Up to date — nothing to apply.\n";
