<?php
/**
 * Health check and schema repair.
 *
 *   https://login.buildonqatar.com/fix_health.php
 *
 * Diagnoses white screens (a fatal inside a page include shows nothing when
 * display_errors is off) and applies the schema migrations this codebase needs.
 *
 * ACCESS: signed in as superadmin/admin, or with a token. To use the token,
 * create a file named .health-token next to this script containing a long
 * random string, then open fix_health.php?token=<that string>.
 *
 * Delete this file when the site is healthy.
 */

session_start();

require_once __DIR__ . '/includes/functions.php';
loadEnv(__DIR__ . '/.env');

const HEALTH_TOKEN_PATH = __DIR__ . '/.health-token';

// ------------------------------------------------------------------ access

$role = $_SESSION['role'] ?? '';
$signedIn = !empty($_SESSION['logged_in']) && in_array($role, ['superadmin', 'admin'], true);

$tokenOk = false;
if (!$signedIn && is_readable(HEALTH_TOKEN_PATH)) {
    $expected = trim((string) file_get_contents(HEALTH_TOKEN_PATH));
    $supplied = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
    $tokenOk = $expected !== '' && hash_equals($expected, $supplied);
}

if (!$signedIn && !$tokenOk) {
    usleep(250000);
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Forbidden. Sign in as an administrator, or create .health-token beside this file and pass ?token=\n");
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

// ------------------------------------------------------------------ helpers

$checks = [];

function check(string $label, bool $ok, string $detail = '', string $severity = 'error'): void
{
    global $checks;
    $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail, 'severity' => $severity];
}

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** The schema this codebase expects, with the statement that repairs each gap. */
function required_schema(): array
{
    return [
        [
            'what' => 'invoices.project_id',
            'kind' => 'column',
            'table' => 'invoices',
            'column' => 'project_id',
            'fix' => [
                'ALTER TABLE invoices ADD COLUMN project_id INT(11) DEFAULT NULL AFTER quotation_id',
                'UPDATE invoices i JOIN quotations q ON i.quotation_id = q.id SET i.project_id = q.project_id WHERE q.project_id IS NOT NULL',
                'CREATE INDEX idx_invoices_project_id ON invoices (project_id)',
            ],
            'why' => 'Projects income and the invoice list resolve the project through this column.',
        ],
        [
            'what' => 'invoices.discount',
            'kind' => 'column',
            'table' => 'invoices',
            'column' => 'discount',
            'fix' => ['ALTER TABLE invoices ADD COLUMN discount DECIMAL(10,2) DEFAULT 0 AFTER total_amount'],
            'why' => 'Invoice discounts are stored here and printed on the document.',
        ],
        [
            'what' => 'quotations.discount',
            'kind' => 'column',
            'table' => 'quotations',
            'column' => 'discount',
            'fix' => [
                'ALTER TABLE quotations ADD COLUMN discount DECIMAL(10,2) DEFAULT 0 AFTER total_amount',
                'UPDATE quotations q SET q.discount = GREATEST(0, (SELECT COALESCE(SUM(qi.total),0) FROM quotation_items qi WHERE qi.quotation_id = q.id) - COALESCE(q.total_amount,0)) WHERE EXISTS (SELECT 1 FROM quotation_items qi WHERE qi.quotation_id = q.id)',
            ],
            'why' => 'Quotation discounts; without it the quotation list and document fail.',
        ],
        [
            'what' => 'purchase_returns table',
            'kind' => 'table',
            'table' => 'purchase_returns',
            'fix' => [
                'CREATE TABLE IF NOT EXISTS purchase_returns (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    purchase_id INT NOT NULL,
                    return_date DATE NOT NULL,
                    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                    invoice_number VARCHAR(100),
                    reason TEXT,
                    created_by VARCHAR(100),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_purchase_returns_purchase (purchase_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            ],
            'why' => 'Return to Vendor on the Purchases page reads and writes this table.',
        ],
    ];
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns
                           WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables
                           WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Include a page with errors on and output discarded, so a white screen turns
 * into a readable message. Fatals are caught by the shutdown handler.
 */
function probe_page(PDO $pdo, string $page): array
{
    $file = __DIR__ . '/pages/' . basename($page) . '.php';
    if (!is_file($file)) {
        return ['ok' => false, 'message' => 'No such page file: ' . $file];
    }

    $result = ['ok' => true, 'message' => 'rendered without error'];
    $marker = __DIR__ . '/.health-probe-' . getmypid();
    @file_put_contents($marker, $page);

    // A fatal ends the request, so record it from the shutdown handler.
    register_shutdown_function(static function () use ($marker, $page) {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            echo '<div class="card bad"><strong>Fatal while rendering ' . h($page) . '</strong><pre>'
                . h($err['message'] . "\n" . $err['file'] . ':' . $err['line']) . '</pre>'
                . '<p>That is what produces the blank page.</p></div>';
        }
        @unlink($marker);
    });

    ob_start();
    try {
        include $file;
    } catch (Throwable $e) {
        $result = [
            'ok' => false,
            'message' => get_class($e) . ': ' . $e->getMessage() . "\n"
                . $e->getFile() . ':' . $e->getLine(),
        ];
    }
    ob_end_clean();
    @unlink($marker);

    return $result;
}

// ------------------------------------------------------------------ checks

error_reporting(E_ALL);
ini_set('display_errors', '1');

$envPath = __DIR__ . '/.env';
check('.env present', file_exists($envPath), $envPath);

$appEnv = getenv('APP_ENV') ?: '(unset)';
$dbName = $appEnv === 'production' ? getenv('PROD_DB_NAME') : getenv('DEV_DB_NAME');
$dbUser = $appEnv === 'production' ? getenv('PROD_DB_USER') : getenv('DEV_DB_USER');
$dbHost = $appEnv === 'production' ? getenv('PROD_DB_HOST') : getenv('DEV_DB_HOST');
check('APP_ENV', $appEnv === 'production', 'APP_ENV=' . $appEnv, $appEnv === 'production' ? 'error' : 'warn');
check('PHP version', PHP_VERSION_ID >= 80000, PHP_VERSION);
check('pdo_mysql loaded', extension_loaded('pdo_mysql'), '');

$quickLogin = getenv('ENABLE_QUICK_LOGIN');
check(
    'ENABLE_QUICK_LOGIN disabled',
    !in_array(strtolower((string) $quickLogin), ['1', 'true', 'yes', 'on'], true) && $quickLogin !== false,
    'Value: ' . ($quickLogin === false ? '(unset — defaults to ENABLED)' : $quickLogin)
        . '. While enabled, anyone can POST quick_login=1 and get a superadmin session.'
);

$pdo = null;
try {
    $dsn = 'mysql:host=' . ($dbHost ?: 'localhost') . ';port=' . (getenv($appEnv === 'production' ? 'PROD_DB_PORT' : 'DEV_DB_PORT') ?: '3306')
        . ';dbname=' . $dbName . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $dbUser, getenv($appEnv === 'production' ? 'PROD_DB_PASS' : 'DEV_DB_PASS') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    check('Database connection', true, 'Connected to `' . $dbName . '` as ' . $dbUser);
} catch (PDOException $e) {
    check('Database connection', false, $e->getMessage());
}

$missing = [];
if ($pdo) {
    $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
    check('Tables present', $tables > 0, $tables . ' tables in `' . $dbName . '`');

    foreach (required_schema() as $item) {
        $present = $item['kind'] === 'column'
            ? column_exists($pdo, $item['table'], $item['column'])
            : table_exists($pdo, $item['table']);
        if (!$present) {
            $missing[] = $item;
        }
        check('Schema: ' . $item['what'], $present, $present ? 'present' : 'MISSING — ' . $item['why']);
    }
}

// ------------------------------------------------------------------ actions

$applied = [];
$applyErrors = [];

if ($pdo && isset($_POST['apply_schema'])) {
    foreach ($missing as $item) {
        foreach ($item['fix'] as $sql) {
            try {
                $pdo->exec($sql);
                $applied[] = $item['what'] . ': ' . substr(preg_replace('/\s+/', ' ', $sql), 0, 90) . ' ...';
            } catch (PDOException $e) {
                // A duplicate column/index means someone else already fixed it.
                if (stripos($e->getMessage(), 'Duplicate') !== false || stripos($e->getMessage(), 'already exists') !== false) {
                    $applied[] = $item['what'] . ': already present';
                    continue;
                }
                $applyErrors[] = $item['what'] . ': ' . $e->getMessage();
            }
        }
    }
    // Re-check so the page reflects reality after the repair.
    $missing = [];
    foreach (required_schema() as $item) {
        $present = $item['kind'] === 'column'
            ? column_exists($pdo, $item['table'], $item['column'])
            : table_exists($pdo, $item['table']);
        if (!$present) {
            $missing[] = $item;
        }
        foreach ($checks as &$c) {
            if ($c['label'] === 'Schema: ' . $item['what']) {
                $c['ok'] = $present;
                $c['detail'] = $present ? 'present' : 'STILL MISSING';
            }
        }
        unset($c);
    }
}

$probe = null;
$probePage = preg_replace('/[^a-z0-9_]/i', '', (string) ($_POST['probe_page'] ?? ''));
if ($pdo && $probePage !== '') {
    $probe = probe_page($pdo, $probePage);
}

$failing = array_filter($checks, static fn($c) => !$c['ok'] && $c['severity'] === 'error');

?>
<!doctype html>
<meta charset="utf-8">
<title>Buildon Accounts — health check</title>
<style>
  body { font: 14px/1.6 system-ui, sans-serif; max-width: 860px; margin: 40px auto; padding: 0 16px; color: #333; }
  h1 { font-size: 22px; margin-bottom: 4px; }
  h2 { font-size: 16px; margin-top: 32px; }
  .sub { color: #6b7280; margin-top: 0; }
  table { border-collapse: collapse; width: 100%; margin-top: 12px; }
  td { padding: 8px 10px; border-bottom: 1px solid #eceef1; vertical-align: top; }
  td.state { width: 34px; font-weight: 700; }
  .ok { color: #166534; } .bad { color: #b91c1c; } .warn { color: #b45309; }
  .card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; margin-top: 16px; }
  .card.bad { border-color: #fecaca; background: #fef2f2; }
  .card.good { border-color: #bbf7d0; background: #f0fdf4; }
  pre { white-space: pre-wrap; background: #f8fafc; padding: 10px; border-radius: 6px; font-size: 13px; overflow-x: auto; }
  button { padding: 9px 16px; border: 0; border-radius: 6px; background: #ea580c; color: #fff; font: inherit; cursor: pointer; }
  button.secondary { background: #475569; }
  select { padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; }
  .muted { color: #6b7280; font-size: 13px; }
</style>

<h1>Buildon Accounts — health check</h1>
<p class="sub">Signed in as <?php echo h($signedIn ? ($_SESSION['username'] ?? 'admin') . ' (' . $role . ')' : 'token holder'); ?></p>

<table>
  <?php foreach ($checks as $c): ?>
    <tr>
      <td class="state <?php echo $c['ok'] ? 'ok' : ($c['severity'] === 'warn' ? 'warn' : 'bad'); ?>">
        <?php echo $c['ok'] ? '✓' : ($c['severity'] === 'warn' ? '!' : '✗'); ?>
      </td>
      <td><strong><?php echo h($c['label']); ?></strong><br><span class="muted"><?php echo h($c['detail']); ?></span></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php if ($applied): ?>
  <div class="card good"><strong>Schema repair applied</strong><pre><?php echo h(implode("\n", $applied)); ?></pre></div>
<?php endif; ?>
<?php if ($applyErrors): ?>
  <div class="card bad"><strong>Repair errors</strong><pre><?php echo h(implode("\n", $applyErrors)); ?></pre></div>
<?php endif; ?>

<?php if ($missing): ?>
  <h2>Fix the schema</h2>
  <p>The deployed code expects <?php echo count($missing); ?> object(s) this database does not have.
     Applying them is safe and idempotent — existing data is preserved.</p>
  <form method="post">
    <input type="hidden" name="token" value="<?php echo h($token); ?>">
    <button type="submit" name="apply_schema" value="1">Apply <?php echo count($missing); ?> migration(s)</button>
  </form>
<?php elseif ($pdo): ?>
  <div class="card good">Schema is up to date — every column and table the code expects is present.</div>
<?php endif; ?>

<h2>Find what a blank page is hiding</h2>
<p>Renders a page with errors switched on and the output discarded, so a fatal
   becomes a readable message instead of a white screen.</p>
<form method="post">
  <input type="hidden" name="token" value="<?php echo h($token); ?>">
  <select name="probe_page">
    <?php foreach (['dashboard', 'invoices', 'quotations', 'projects', 'purchases', 'payroll', 'clients', 'vendors'] as $p): ?>
      <option value="<?php echo h($p); ?>" <?php echo $p === $probePage ? 'selected' : ''; ?>><?php echo h($p); ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="secondary">Render it</button>
</form>

<?php if ($probe !== null): ?>
  <div class="card <?php echo $probe['ok'] ? 'good' : 'bad'; ?>">
    <strong><?php echo h($probePage); ?>:</strong>
    <pre><?php echo h($probe['message']); ?></pre>
  </div>
<?php endif; ?>

<h2>Server</h2>
<pre><?php
echo 'PHP        ' . PHP_VERSION . ' (' . PHP_SAPI . ")\n";
echo 'display_errors  ' . (ini_get('display_errors') ?: '0') . "\n";
echo 'error_log       ' . (ini_get('error_log') ?: '(default)') . "\n";
echo 'memory_limit    ' . ini_get('memory_limit') . "\n";
echo 'APP_ENV         ' . h($appEnv) . "\n";
echo 'Database        ' . h($dbName) . ' @ ' . h($dbHost) . "\n";
?></pre>

<?php
$logCandidates = [__DIR__ . '/error_log', __DIR__ . '/php_errorlog', ini_get('error_log')];
foreach ($logCandidates as $log) {
    if ($log && is_readable($log) && is_file($log)) {
        $lines = @file($log);
        if ($lines) {
            echo '<h2>Last lines of ' . h($log) . '</h2><pre>'
                . h(implode('', array_slice($lines, -25))) . '</pre>';
        }
        break;
    }
}
?>

<p class="muted" style="margin-top:32px">
  Delete <code>fix_health.php</code> (and <code>.health-token</code>) once the site is healthy.
</p>
