<?php

// Set Qatar timezone (UTC+3) for the entire application
date_default_timezone_set('Asia/Qatar');

// Load environment variables
require_once __DIR__ . '/functions.php';
loadEnv(__DIR__ . '/../.env');

// Determine environment (development or production)
$env = getenv('APP_ENV') ?: 'development';

// Get database configuration based on environment
if ($env === 'production') {
    $dbType = getenv('PROD_DB_TYPE') ?: 'mysql';
    $dbHost = getenv('PROD_DB_HOST') ?: 'localhost';
    $dbPort = getenv('PROD_DB_PORT') ?: '3306';
    $dbName = getenv('PROD_DB_NAME') ?: '';
    $dbUser = getenv('PROD_DB_USER') ?: '';
    $dbPass = getenv('PROD_DB_PASS') ?: '';
} else {
    $dbType = getenv('DEV_DB_TYPE') ?: 'mysql';
    $dbHost = getenv('DEV_DB_HOST') ?: 'localhost';
    $dbPort = getenv('DEV_DB_PORT') ?: '3306';
    $dbName = getenv('DEV_DB_NAME') ?: 'buildon';
    $dbUser = getenv('DEV_DB_USER') ?: 'root';
    $dbPass = getenv('DEV_DB_PASS') ?: '';
}

try {
    if ($dbType === 'mysql') {
        // MySQL Connection
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
    } else {
        // SQLite Connection (fallback)
        $sqlitePath = getenv('SQLITE_DB_PATH') ?: 'buildon.sqlite';
        $dsn = 'sqlite:' . __DIR__ . '/../' . $sqlitePath;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (PDOException $e) {
    // The SQLite fallback exists for local work. Falling back to an EMPTY
    // SQLite file turns a configuration problem into "no such table: invoices"
    // on every page, which is why it is refused unless the file is a real,
    // populated database and we are not in production.
    $sqlitePath = getenv('SQLITE_DB_PATH') ?: 'buildon.sqlite';
    $sqliteFile = __DIR__ . '/../' . $sqlitePath;
    $envMissing = !file_exists(__DIR__ . '/../.env');

    $mayFallBack = $dbType === 'mysql'
        && $env !== 'production'
        && is_file($sqliteFile)
        && filesize($sqliteFile) > 0;

    if ($mayFallBack) {
        try {
            $candidate = new PDO('sqlite:' . $sqliteFile);
            $candidate->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // A file that opens but holds no tables is worse than no fallback:
            // every query then dies with "no such table". Only accept a real one.
            $tableCount = (int) $candidate->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"
            )->fetchColumn();

            if ($tableCount > 0) {
                $pdo = $candidate;
                error_log('MySQL connection failed, using SQLite fallback (' . $tableCount . ' tables): ' . $e->getMessage());
                return;
            }
            error_log('SQLite fallback refused: ' . $sqliteFile . ' has no tables.');
        } catch (PDOException $e2) {
            error_log('SQLite fallback also failed: ' . $e2->getMessage());
        }
    }

    // Fail loudly and usefully. Details go to the error log; the page shows
    // only what an operator needs, and never a credential.
    error_log('Database connection failed (' . $env . ', host ' . $dbHost . ', db ' . $dbName . '): ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Database not configured</title>';
    echo '<style>body{font:14px/1.6 system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 16px;color:#333}'
        . 'h1{font-size:20px}code{background:#f3f4f6;padding:1px 5px;border-radius:3px}'
        . '.box{border:1px solid #fecaca;background:#fef2f2;border-radius:8px;padding:14px 16px}</style>';
    echo '<h1>The application cannot reach its database</h1><div class="box">';
    if ($envMissing) {
        echo '<p><strong>There is no <code>.env</code> file.</strong> Without it the application falls back to '
            . 'development defaults, which do not exist on this server.</p>'
            . '<p>Create one by running the installer (<code>php sql/db.php</code>, or open '
            . '<code>sql/db.php</code> in a browser), or copy <code>.env.example</code> to <code>.env</code> '
            . 'and fill in the database settings.</p>';
    } else {
        echo '<p><code>.env</code> is present but the connection was refused. Check the '
            . '<code>PROD_DB_*</code> settings and that <code>APP_ENV=production</code>.</p>';
    }
    echo '<p>Run <code>fix_health.php</code> for a full diagnosis. The exact error is in the PHP error log.</p>';
    echo '</div>';
    exit;
}