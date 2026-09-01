<?php
/**
 * First-run bootstrap for Buildon Accounts — PHP version.
 *
 * For hosts without Node.js. Does exactly what sql/db.js does:
 *   1. writes a .env file (production database settings) if one does not exist;
 *   2. creates the database when the account is allowed to (shared hosts
 *      usually pre-create it in cPanel — that is fine, the step is skipped);
 *   3. imports sql/buildon_qatar.sql, but only when the database is still
 *      empty, so an existing installation is never overwritten.
 *
 * Run it either way:
 *
 *   CLI      php sql/db.php
 *   Browser  https://your-site/sql/db.php?token=<setup token>
 *
 * A browser run is refused unless sql/.setup-token exists and its contents match
 * the supplied token. Generate one before uploading:
 *
 *   php sql/db.php --make-token        (prints the token and writes the file)
 *
 * Without that gate anyone who reached this URL before you did could point the
 * application at a database of their own, so the token is mandatory over HTTP.
 * The token file is deleted once setup succeeds.
 *
 * Credentials come from, in order of precedence:
 *   1. the browser form / environment variables,
 *   2. sql/db.config.json (gitignored — copy db.config.example.json),
 *   3. the non-secret defaults below.
 *
 * SAFETY: once the import succeeds this writes sql/.installed and every later
 * web request is refused, so the installer cannot be replayed by a visitor.
 * DELETE THIS FILE (and db.config.json) once the site is live.
 */

declare(strict_types=1);

@set_time_limit(0);
@ini_set('memory_limit', '512M');

const ROOT_DIR = __DIR__ . '/..';
const ENV_PATH = ROOT_DIR . '/.env';
const SQL_PATH = __DIR__ . '/buildon_qatar.sql';
const CONFIG_PATH = __DIR__ . '/db.config.json';
const LOCK_PATH = __DIR__ . '/.installed';
const TOKEN_PATH = __DIR__ . '/.setup-token';

$IS_CLI = (PHP_SAPI === 'cli');

// Non-secret defaults; host/port/name match the production server.
$DEFAULTS = [
    'PROD_DB_TYPE' => 'mysql',
    'PROD_DB_HOST' => 'localhost',
    'PROD_DB_PORT' => '3306',
    'PROD_DB_NAME' => 'buildon_qatar',
    'PROD_DB_USER' => 'buildon_qatar',
    'PROD_DB_PASS' => '',
];

$steps = [];
$errors = [];

function step(string $message): void
{
    global $steps;
    $steps[] = $message;
}

function bail(string $message): void
{
    global $errors;
    $errors[] = $message;
    render();
    exit(1);
}

/**
 * Credentials: form/environment first, then the local config file, then defaults.
 */
function load_config(): array
{
    global $DEFAULTS;

    $fromFile = [];
    if (is_readable(CONFIG_PATH)) {
        $decoded = json_decode((string) file_get_contents(CONFIG_PATH), true);
        if (is_array($decoded)) {
            $fromFile = $decoded;
        }
    }

    $config = [];
    foreach ($DEFAULTS as $key => $default) {
        $posted = $_POST[$key] ?? null;
        $env = getenv($key);
        if (is_string($posted) && $posted !== '') {
            $config[$key] = trim($posted);
        } elseif ($env !== false && $env !== '') {
            $config[$key] = $env;
        } elseif (isset($fromFile[$key]) && $fromFile[$key] !== '') {
            $config[$key] = (string) $fromFile[$key];
        } else {
            $config[$key] = $default;
        }
    }
    return $config;
}

function connect(array $config, bool $withDatabase): PDO
{
    $dsn = 'mysql:host=' . $config['PROD_DB_HOST'] . ';port=' . $config['PROD_DB_PORT'] . ';charset=utf8mb4';
    if ($withDatabase) {
        $dsn = 'mysql:host=' . $config['PROD_DB_HOST'] . ';port=' . $config['PROD_DB_PORT']
            . ';dbname=' . $config['PROD_DB_NAME'] . ';charset=utf8mb4';
    }

    return new PDO($dsn, $config['PROD_DB_USER'], $config['PROD_DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/** Step 1 — write .env, never clobbering an existing one. */
function write_env(array $config): void
{
    if (file_exists(ENV_PATH)) {
        step('.env already exists — left untouched');
        return;
    }

    $examplePath = ROOT_DIR . '/.env.example';
    if (is_readable($examplePath)) {
        // Start from the template so non-database settings are present too.
        $contents = (string) file_get_contents($examplePath);
        foreach ($config as $key => $value) {
            $line = $key . '=' . $value;
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
            $contents = preg_match($pattern, $contents)
                ? preg_replace($pattern, $line, $contents, 1)
                : rtrim($contents) . "\n" . $line . "\n";
        }
        $contents = preg_replace('/^APP_ENV=.*$/m', 'APP_ENV=production', $contents, 1);
        // A fresh production install must not expose the no-password quick login.
        if (preg_match('/^ENABLE_QUICK_LOGIN=.*$/m', $contents)) {
            $contents = preg_replace('/^ENABLE_QUICK_LOGIN=.*$/m', 'ENABLE_QUICK_LOGIN=false', $contents, 1);
        } else {
            $contents = rtrim($contents) . "\nENABLE_QUICK_LOGIN=false\n";
        }
    } else {
        $contents = "# Generated by sql/db.php on first run.\n"
            . "APP_ENV=production\n"
            . "ENABLE_QUICK_LOGIN=false\n\n"
            . "# Production database\n"
            . 'PROD_DB_TYPE=' . $config['PROD_DB_TYPE'] . "\n"
            . 'PROD_DB_HOST=' . $config['PROD_DB_HOST'] . "\n"
            . 'PROD_DB_PORT=' . $config['PROD_DB_PORT'] . "\n"
            . 'PROD_DB_NAME=' . $config['PROD_DB_NAME'] . "\n"
            . 'PROD_DB_USER=' . $config['PROD_DB_USER'] . "\n"
            . 'PROD_DB_PASS=' . $config['PROD_DB_PASS'] . "\n";
    }

    if (@file_put_contents(ENV_PATH, $contents) === false) {
        bail('Could not write ' . ENV_PATH . ' — check directory permissions.');
    }
    step('.env created (APP_ENV=production, database ' . $config['PROD_DB_NAME'] . ')');
}

/** Step 2 — create the database when permitted; shared hosts pre-create it. */
function ensure_database(array $config): void
{
    try {
        $pdo = connect($config, false);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $config['PROD_DB_NAME'])
            . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        step('database `' . $config['PROD_DB_NAME'] . '` is present');
    } catch (PDOException $e) {
        // Restricted shared-hosting users cannot CREATE DATABASE. Not fatal as
        // long as the database already exists — the next step will tell us.
        step('skipped database creation (' . $e->getMessage() . ')');
    }
}

/**
 * Split a dump into statements. Tracks quoting, escapes and comments so that
 * semicolons inside strings do not split a statement.
 */
function split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $len = strlen($sql);
    $inSingle = $inDouble = $inBacktick = $inLineComment = $inBlockComment = false;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
                $buffer .= $ch;
            }
            continue;
        }
        if ($inBlockComment) {
            $buffer .= $ch;
            if ($ch === '*' && $next === '/') {
                $buffer .= $next;
                $i++;
                $inBlockComment = false;
            }
            continue;
        }
        if (!$inSingle && !$inDouble && !$inBacktick) {
            // "-- " and "#" line comments, and /* */ blocks (kept: /*!... */ is executable)
            if (($ch === '-' && $next === '-') || $ch === '#') {
                $inLineComment = true;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $buffer .= $ch;
                continue;
            }
            if ($ch === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }
        }

        if ($ch === '\\' && ($inSingle || $inDouble)) {
            // Escaped character inside a string: copy both bytes verbatim.
            $buffer .= $ch;
            if ($next !== '') {
                $buffer .= $next;
                $i++;
            }
            continue;
        }
        if ($ch === "'" && !$inDouble && !$inBacktick) {
            $inSingle = !$inSingle;
        } elseif ($ch === '"' && !$inSingle && !$inBacktick) {
            $inDouble = !$inDouble;
        } elseif ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
        }

        $buffer .= $ch;
    }

    $trimmed = trim($buffer);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }
    return $statements;
}

/** Step 3 — import the dump, but only into an empty database. */
function import_dump(array $config): bool
{
    if (!is_readable(SQL_PATH)) {
        bail('Missing sql/buildon_qatar.sql — cannot import.');
    }

    try {
        $pdo = connect($config, true);
    } catch (PDOException $e) {
        bail('Could not connect to `' . $config['PROD_DB_NAME'] . '`: ' . $e->getMessage()
            . ' — check the database exists and the user has access to it.');
    }

    $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')
        ->fetchColumn();
    if ($tables > 0) {
        step('database already has ' . $tables . ' tables — import skipped');
        return false;
    }

    $sql = (string) file_get_contents(SQL_PATH);
    $statements = split_sql($sql);

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $executed = 0;
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            bail('Import failed on statement ' . ($executed + 1) . ': ' . $e->getMessage()
                . ' — SQL began: ' . substr($statement, 0, 120));
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')
        ->fetchColumn();
    step('imported ' . $executed . ' statements — ' . $tables . ' tables created');
    return true;
}

function render(): void
{
    global $IS_CLI, $steps, $errors;

    if ($IS_CLI) {
        echo "\nBuildon Accounts — first-run setup\n\n";
        foreach ($steps as $s) {
            echo '  . ' . $s . "\n";
        }
        foreach ($errors as $e) {
            echo "\n  x " . $e . "\n";
        }
        if (!$errors) {
            echo "\nSetup complete. Delete sql/db.php and sql/db.config.json now.\n\n";
        }
        return;
    }

    $ok = !$errors;
    echo '<!doctype html><meta charset="utf-8"><title>Buildon Accounts setup</title>';
    echo '<style>body{font:14px/1.55 system-ui,sans-serif;max-width:640px;margin:48px auto;padding:0 16px;color:#333}'
        . 'h1{font-size:20px}li{margin:.25rem 0}.ok{color:#166534}.bad{color:#b91c1c}'
        . 'code{background:#f3f4f6;padding:1px 4px;border-radius:3px}</style>';
    echo '<h1>Buildon Accounts — first-run setup</h1><ul>';
    foreach ($steps as $s) {
        echo '<li class="ok">' . htmlspecialchars($s) . '</li>';
    }
    foreach ($errors as $e) {
        echo '<li class="bad"><strong>' . htmlspecialchars($e) . '</strong></li>';
    }
    echo '</ul>';
    if ($ok) {
        echo '<p><strong>Setup complete.</strong> Delete <code>sql/db.php</code> and '
            . '<code>sql/db.config.json</code>, then open <code>index.php</code>.</p>';
    }
}

function render_form(array $config, ?string $notice = null): void
{
    echo '<!doctype html><meta charset="utf-8"><title>Buildon Accounts setup</title>';
    echo '<style>body{font:14px/1.55 system-ui,sans-serif;max-width:560px;margin:48px auto;padding:0 16px;color:#333}'
        . 'h1{font-size:20px}label{display:block;margin:12px 0 4px;font-weight:600}'
        . 'input{width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font:inherit}'
        . 'button{margin-top:18px;padding:10px 18px;border:0;border-radius:6px;background:#ea580c;color:#fff;font:inherit;cursor:pointer}'
        . '.note{background:#fff7ed;border:1px solid #fed7aa;padding:10px 12px;border-radius:6px}</style>';
    echo '<h1>Buildon Accounts — first-run setup</h1>';
    if ($notice) {
        echo '<p class="note">' . htmlspecialchars($notice) . '</p>';
    }
    echo '<p>Enter the database this site should use. The values are written to '
        . '<code>.env</code>; the dump is imported only if the database is empty.</p>';
    echo '<form method="post">';
    $token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
    if ($token !== '') {
        echo '<input type="hidden" name="token" value="' . htmlspecialchars($token) . '">';
    }
    foreach ([
        'PROD_DB_HOST' => 'Database host',
        'PROD_DB_PORT' => 'Port',
        'PROD_DB_NAME' => 'Database name',
        'PROD_DB_USER' => 'Database user',
        'PROD_DB_PASS' => 'Database password',
    ] as $key => $label) {
        $type = $key === 'PROD_DB_PASS' ? 'password' : 'text';
        echo '<label for="' . $key . '">' . $label . '</label>';
        echo '<input id="' . $key . '" type="' . $type . '" name="' . $key . '" value="'
            . htmlspecialchars($key === 'PROD_DB_PASS' ? '' : $config[$key]) . '">';
    }
    echo '<button type="submit" name="install" value="1">Install</button></form>';
}

/** Disarm the web installer. Called on every completed browser run. */
function disarm(string $why): void
{
    @file_put_contents(LOCK_PATH, date('c') . ' ' . $why . "\n");
    @unlink(TOKEN_PATH);
}

/** Refuse the request with a status code and a bare message. */
function deny(int $status, string $message): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message . "\n");
}

/**
 * A browser run must present the setup token. Without this an attacker who
 * reaches the URL before the deployer does could install the application
 * against a database of their own and take over the site.
 */
function require_setup_token(): void
{
    if (!is_readable(TOKEN_PATH)) {
        deny(403, 'Setup is not armed. Run "php sql/db.php --make-token" (or create sql/.setup-token '
            . 'containing a random string) before using the web installer.');
    }

    $expected = trim((string) file_get_contents(TOKEN_PATH));
    $supplied = (string) ($_POST['token'] ?? $_GET['token'] ?? $_SERVER['HTTP_X_SETUP_TOKEN'] ?? '');

    if ($expected === '' || !hash_equals($expected, trim($supplied))) {
        // Slow down blind guessing a little without hanging the worker.
        usleep(250000);
        deny(403, 'Forbidden.');
    }
}

function make_token(): void
{
    $token = bin2hex(random_bytes(24));
    if (@file_put_contents(TOKEN_PATH, $token . "\n") === false) {
        fwrite(STDERR, "Could not write " . TOKEN_PATH . "\n");
        exit(1);
    }
    @chmod(TOKEN_PATH, 0600);
    echo "\nSetup token written to sql/.setup-token\n\n  " . $token . "\n\n"
        . "Open:  https://your-site/sql/db.php?token=" . $token . "\n\n"
        . "It is deleted automatically once setup completes.\n\n";
    exit(0);
}

// ---------------------------------------------------------------- entry point

if ($IS_CLI && in_array('--make-token', $argv ?? [], true)) {
    make_token();
}

if (!$IS_CLI) {
    // 1. Already installed — stay disarmed.
    if (file_exists(LOCK_PATH)) {
        deny(403, 'Setup has already been completed. Delete sql/db.php from the server.');
    }

    // 2. Every browser run must carry the setup token.
    require_setup_token();

    // 3. A configured application is not reinstallable over HTTP: if .env is
    //    already present the site is live, so disarm instead of touching it.
    if (file_exists(ENV_PATH)) {
        disarm('env-present');
        deny(403, 'This site is already configured (.env exists). The installer has been disabled; '
            . 'delete sql/db.php from the server.');
    }
}

$config = load_config();

// In the browser, ask for the credentials unless they are already configured
// (config file or environment) or were just submitted.
if (!$IS_CLI && !isset($_POST['install'])) {
    $configured = $config['PROD_DB_PASS'] !== '' || is_readable(CONFIG_PATH);
    if (!$configured) {
        render_form($config);
        exit;
    }
}

write_env($config);
ensure_database($config);
$imported = import_dump($config);

if (!$IS_CLI) {
    // Self-disarm on any completed browser run, imported or not, so the
    // endpoint is never left live waiting for a second visitor.
    disarm($imported ? 'installed' : 'completed');
    step('wrote sql/.installed and removed the setup token — the web installer is now disabled');
} elseif ($imported) {
    @file_put_contents(LOCK_PATH, date('c') . " installed\n");
    step('wrote sql/.installed — the web installer is now disabled');
}

render();
