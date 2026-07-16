<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$options = parseOptions(array_slice($argv, 1));

$passes = [];
$warnings = [];
$failures = [];

checkRequiredFiles($projectRoot, $passes, $failures);
checkRequiredDirectories($projectRoot, $passes, $failures, $warnings);
$env = checkEnvironment($projectRoot, $passes, $warnings, $failures);
checkPaymentPlugins($projectRoot, $passes, $failures);
checkDatabase($env, $passes, $warnings, $failures);
checkHttpTargets($env, $options, $passes, $warnings, $failures);

foreach ($passes as $message) {
    fwrite(STDOUT, "[PASS] {$message}" . PHP_EOL);
}

foreach ($warnings as $message) {
    fwrite(STDOUT, "[WARN] {$message}" . PHP_EOL);
}

foreach ($failures as $message) {
    fwrite(STDOUT, "[FAIL] {$message}" . PHP_EOL);
}

fwrite(
    STDOUT,
    sprintf(
        'Summary: %d passed, %d warned, %d failed%s',
        count($passes),
        count($warnings),
        count($failures),
        PHP_EOL
    )
);

exit($failures === [] ? 0 : 1);

function parseOptions(array $arguments): array
{
    $options = [
        'backend-url' => null,
        'console-url' => null,
        'merchant-url' => null,
        'public-url' => null,
        'admin-user' => null,
        'admin-password' => null,
        'timeout' => 8,
        'skip-http' => false,
        'skip-admin-api' => false,
        'help' => false,
    ];

    foreach ($arguments as $argument) {
        if ($argument === '--skip-http') {
            $options['skip-http'] = true;
            continue;
        }

        if ($argument === '--skip-admin-api') {
            $options['skip-admin-api'] = true;
            continue;
        }

        if ($argument === '--help') {
            $options['help'] = true;
            continue;
        }

        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            failWithUsage("Unknown argument: {$argument}");
        }

        [$name, $value] = explode('=', substr($argument, 2), 2);
        if (!array_key_exists($name, $options)) {
            failWithUsage("Unknown option: --{$name}");
        }

        if ($name === 'timeout') {
            if (!is_numeric($value) || (int)$value < 1) {
                failWithUsage('--timeout must be a positive integer');
            }

            $options[$name] = (int)$value;
            continue;
        }

        $options[$name] = trim($value);
    }

    if ($options['help']) {
        $usage = <<<TEXT
Usage:
  php deploy/shared/verify-deployment.php [--backend-url=http://127.0.0.1:8787] [--console-url=https://portal.example.com] [--merchant-url=https://portal.example.com] [--public-url=https://portal.example.com] [--admin-user=adminroot] [--admin-password=secret] [--timeout=8] [--skip-http] [--skip-admin-api]

Options:
  --backend-url   Base backend URL. /api/health will be appended automatically.
  --console-url   Frontend shell URL used by the admin login route.
  --merchant-url  Frontend shell URL used by the merchant login route.
  --public-url    Frontend shell URL used by the public homepage.
  --admin-user    Optional admin username used to validate admin APIs after login.
  --admin-password Optional admin password used to validate admin APIs after login.
  --timeout       HTTP timeout in seconds. Default: 8.
  --skip-http     Skip HTTP checks and only verify files, env, plugins, and database.
  --skip-admin-api Skip authenticated admin API checks even when credentials are provided.
  --help          Show this help message.
TEXT;

        fwrite(STDOUT, $usage . PHP_EOL);
        exit(0);
    }

    return $options;
}

function failWithUsage(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    fwrite(STDERR, 'Run with --help to see supported options.' . PHP_EOL);
    exit(1);
}

function checkRequiredFiles(string $projectRoot, array &$passes, array &$failures): void
{
    $files = [
        '.env',
        '.env.example',
        'composer.json',
        'start.php',
        'deploy/shared/verify-deployment.php',
    ];

    foreach ($files as $relativePath) {
        $fullPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($fullPath)) {
            $failures[] = "Required file is missing: {$relativePath}";
            continue;
        }

        $passes[] = "Required file exists: {$relativePath}";
    }
}

function checkRequiredDirectories(string $projectRoot, array &$passes, array &$failures, array &$warnings): void
{
    $directories = [
        'runtime',
        'runtime/cache',
        'runtime/logs',
        'runtime/payment-plugins',
        'upload-assets',
        'upload-assets/images',
        'upload-assets/news',
        'upload-assets/payment-accounts',
        'upload-assets/plugins',
        'upload-assets/qrcode',
        'plugins/payments',
    ];

    foreach ($directories as $relativePath) {
        $fullPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_dir($fullPath)) {
            $failures[] = "Required directory is missing: {$relativePath}";
            continue;
        }

        $passes[] = "Required directory exists: {$relativePath}";

        if (!is_writable($fullPath) && !str_starts_with($relativePath, 'plugins/')) {
            $warnings[] = "Directory is not writable by the current user: {$relativePath}";
        }
    }
}

function checkEnvironment(string $projectRoot, array &$passes, array &$warnings, array &$failures): array
{
    $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
    $examplePath = $projectRoot . DIRECTORY_SEPARATOR . '.env.example';
    $env = loadEnvFile($envPath);

    if ($env === []) {
        $failures[] = '.env could not be parsed or is empty';
        return [];
    }

    $passes[] = '.env file parsed successfully';

    if (is_file($examplePath) && md5_file($envPath) === md5_file($examplePath)) {
        $failures[] = '.env still matches .env.example exactly; production values have not been customized';
    }

    $requiredKeys = [
        'APP_ENV',
        'APP_DEBUG',
        'APP_HOST',
        'APP_PORT',
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'AIPAY_ADMIN_FRONTEND_URL',
        'AIPAY_MERCHANT_FRONTEND_URL',
        'AIPAY_PUBLIC_FRONTEND_URL',
    ];

    foreach ($requiredKeys as $key) {
        $value = trim((string)($env[$key] ?? ''));
        if ($value === '') {
            $failures[] = "Required env key is missing or empty: {$key}";
            continue;
        }

        $passes[] = "Env key configured: {$key}";
    }

    if (($env['APP_ENV'] ?? '') !== 'production') {
        $warnings[] = 'APP_ENV is not set to production';
    } else {
        $passes[] = 'APP_ENV is production';
    }

    if (!isFalseLike($env['APP_DEBUG'] ?? '')) {
        $warnings[] = 'APP_DEBUG is not disabled';
    } else {
        $passes[] = 'APP_DEBUG is disabled';
    }

    if (!ctype_digit((string)($env['APP_PORT'] ?? ''))) {
        $failures[] = 'APP_PORT must be a numeric port value';
    } else {
        $passes[] = 'APP_PORT is numeric';
    }

    if (!ctype_digit((string)($env['DB_PORT'] ?? ''))) {
        $failures[] = 'DB_PORT must be a numeric port value';
    } else {
        $passes[] = 'DB_PORT is numeric';
    }

    foreach (['DB_PASSWORD' => 'change_me'] as $key => $placeholder) {
        if (strcasecmp(trim((string)($env[$key] ?? '')), $placeholder) === 0) {
            $failures[] = "{$key} is still using the example placeholder value";
        }
    }

    foreach (['AIPAY_ADMIN_FRONTEND_URL', 'AIPAY_MERCHANT_FRONTEND_URL', 'AIPAY_PUBLIC_FRONTEND_URL'] as $key) {
        $value = trim((string)($env[$key] ?? ''));
        if ($value === '') {
            continue;
        }

        if (stripos($value, 'example.com') !== false) {
            $failures[] = "{$key} still points to an example.com placeholder";
            continue;
        }

        if (filter_var(stripFragment($value), FILTER_VALIDATE_URL) === false) {
            $failures[] = "{$key} is not a valid URL";
            continue;
        }

        $passes[] = "{$key} is a valid URL";
    }

    $frontendUrls = [
        trim((string)($env['AIPAY_ADMIN_FRONTEND_URL'] ?? '')),
        trim((string)($env['AIPAY_MERCHANT_FRONTEND_URL'] ?? '')),
        trim((string)($env['AIPAY_PUBLIC_FRONTEND_URL'] ?? '')),
    ];
    $frontendOrigins = array_values(array_unique(array_filter(array_map(
        static function (string $url): string {
            $normalized = normalizeUrl($url);
            $scheme = (string)(parse_url($normalized, PHP_URL_SCHEME) ?? '');
            $host = (string)(parse_url($normalized, PHP_URL_HOST) ?? '');
            $port = parse_url($normalized, PHP_URL_PORT);
            if ($scheme === '' || $host === '') {
                return '';
            }

            return $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
        },
        $frontendUrls
    ))));

    if (count($frontendOrigins) === 1 && $frontendOrigins !== []) {
        $passes[] = 'Frontend shell URLs are unified on one public origin';
    } elseif (count($frontendOrigins) > 1) {
        $warnings[] = 'Frontend shell URLs are split across multiple origins; the packaged console build is recommended to run on a single public domain';
    }

    return $env;
}

function checkPaymentPlugins(string $projectRoot, array &$passes, array &$failures): void
{
    $pluginsRoot = $projectRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'payments';
    $pluginDirectories = array_values(array_filter(
        glob($pluginsRoot . DIRECTORY_SEPARATOR . '*') ?: [],
        static function (string $path): bool {
            if (!is_dir($path)) {
                return false;
            }

            $name = basename($path);
            return $name !== '' && $name[0] !== '_' && $name[0] !== '.';
        }
    ));

    if ($pluginDirectories === []) {
        $failures[] = 'No payment plugin directories were found under plugins/payments';
        return;
    }

    $passes[] = sprintf('%d payment plugin directories detected', count($pluginDirectories));

    $missingFiles = [];
    foreach ($pluginDirectories as $pluginDirectory) {
        $pluginName = basename($pluginDirectory);
        if (!is_file($pluginDirectory . DIRECTORY_SEPARATOR . 'plugin.json')) {
            $missingFiles[] = "{$pluginName}/plugin.json";
        }
        if (!is_file($pluginDirectory . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Plugin.php')) {
            $missingFiles[] = "{$pluginName}/src/Plugin.php";
        }
    }

    if ($missingFiles !== []) {
        $failures[] = 'Payment plugin metadata is incomplete: ' . implode(', ', $missingFiles);
        return;
    }

    $passes[] = 'All payment plugin directories include plugin.json and src/Plugin.php';
}

function checkDatabase(array $env, array &$passes, array &$warnings, array &$failures): void
{
    $requiredKeys = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
    foreach ($requiredKeys as $key) {
        if (trim((string)($env[$key] ?? '')) === '') {
            $warnings[] = 'Database checks were skipped because env configuration is incomplete';
            return;
        }
    }

    try {
        $pdo = createPdo($env);
    } catch (Throwable $throwable) {
        $failures[] = 'Database connection failed: ' . $throwable->getMessage();
        return;
    }

    $pdo->query('SELECT 1');
    $passes[] = 'Database connection is healthy';

    $requiredTables = [
        'admin_admin',
        'admin_permission',
        'admin_config',
        'admin_channel',
        'ypay_user',
        'ypay_payment',
        'ypay_account',
        'ypay_order',
        'ypay_plug',
    ];

    $missingTables = [];
    foreach ($requiredTables as $table) {
        if (!tableExists($pdo, $table)) {
            $missingTables[] = $table;
        }
    }

    if ($missingTables !== []) {
        $failures[] = 'Required database tables are missing: ' . implode(', ', $missingTables);
    } else {
        $passes[] = 'Core database tables are present';
    }

    if (tableExists($pdo, 'ypay_payment')) {
        $paymentMethodCount = scalarCount($pdo, 'SELECT COUNT(*) FROM ypay_payment');
        if ($paymentMethodCount <= 0) {
            $failures[] = 'ypay_payment is empty; clean installs would break payment plugin and payment method pages.';
        } else {
            $passes[] = sprintf('Core payment methods are present in ypay_payment (%d rows)', $paymentMethodCount);
        }
    }

    $requiredColumns = [
        'admin_admin.delete_time',
        'admin_channel.delete_time',
        'ypay_navs.delete_time',
        'ypay_news.delete_time',
        'ypay_vip.delete_time',
        'ypay_plug.delete_time',
    ];

    $missingColumns = [];
    foreach ($requiredColumns as $definition) {
        [$table, $column] = explode('.', $definition, 2);
        if (!columnExists($pdo, $table, $column)) {
            $missingColumns[] = $definition;
        }
    }

    if ($missingColumns !== []) {
        $failures[] = 'Known modernization migration columns are missing: ' . implode(', ', $missingColumns);
    } else {
        $passes[] = 'Known modernization migration columns are present';
    }

    if ($missingTables === []) {
        $adminPermissionCount = scalarCount($pdo, 'SELECT COUNT(*) FROM admin_permission');
        if ($adminPermissionCount <= 0) {
            $failures[] = 'Admin permission seed is empty; admin write actions will be blocked on a clean install.';
        } else {
            $passes[] = sprintf('Admin permission seed is present (%d rows)', $adminPermissionCount);
        }

        $adminRoleCount = scalarCount($pdo, 'SELECT COUNT(*) FROM admin_role');
        if ($adminRoleCount <= 0) {
            $failures[] = 'Admin role seed is empty; clean install is missing the default super-admin role.';
        } else {
            $passes[] = sprintf('Admin role seed is present (%d rows)', $adminRoleCount);
        }

        $rootAdminCount = scalarCount($pdo, 'SELECT COUNT(*) FROM admin_admin WHERE id = 1');
        if ($rootAdminCount <= 0) {
            $warnings[] = 'Root admin account (id=1) is not present yet; bind the default admin after completing installation.';
        } else {
            $passes[] = 'Root admin account exists';

            $rootBindingCount = scalarCount($pdo, 'SELECT COUNT(*) FROM admin_admin_role WHERE admin_id = 1 AND role_id = 1');
            if ($rootBindingCount <= 0) {
                $warnings[] = 'Root admin is not bound to role #1 yet; installation can still work, but role-based admin management is not fully initialized.';
            } else {
                $passes[] = 'Root admin is bound to the default super-admin role';
            }
        }
    }
}

function checkHttpTargets(array $env, array $options, array &$passes, array &$warnings, array &$failures): void
{
    if ($options['skip-http']) {
        $passes[] = 'HTTP checks skipped by request';
        return;
    }

    $targets = [];

    $backendBaseUrl = trim((string)($options['backend-url'] ?? ''));
    if ($backendBaseUrl === '') {
        $backendBaseUrl = deriveBackendBaseUrl($env);
    }
    if ($backendBaseUrl !== '') {
        $targets[] = [
            'label' => 'backend health',
            'url' => ensureBackendHealthUrl($backendBaseUrl),
            'type' => 'health',
        ];
    } else {
        $warnings[] = 'Backend URL could not be derived automatically; pass --backend-url to enable health checks';
    }

    $frontendMappings = [
        'console-url' => ['label' => 'admin console', 'env' => 'AIPAY_ADMIN_FRONTEND_URL'],
        'merchant-url' => ['label' => 'merchant console', 'env' => 'AIPAY_MERCHANT_FRONTEND_URL'],
        'public-url' => ['label' => 'public site', 'env' => 'AIPAY_PUBLIC_FRONTEND_URL'],
    ];

    foreach ($frontendMappings as $optionKey => $mapping) {
        $url = trim((string)($options[$optionKey] ?? ''));
        if ($url === '') {
            $url = trim((string)($env[$mapping['env']] ?? ''));
        }

        if ($url === '' || stripos($url, 'example.com') !== false) {
            $warnings[] = "Skipped {$mapping['label']} HTTP check because the URL is not configured for deployment";
            continue;
        }

        $targets[] = [
            'label' => $mapping['label'],
            'url' => normalizeUrl($url),
            'type' => 'frontend',
        ];
    }

    foreach ($targets as $target) {
        $result = httpGet($target['url'], $options['timeout']);
        if (!$result['ok']) {
            $failures[] = sprintf('%s HTTP check failed: %s', ucfirst($target['label']), $result['error']);
            continue;
        }

        if ($target['type'] === 'health') {
            $json = json_decode($result['body'], true);
            if (!is_array($json) || (int)($json['code'] ?? 0) !== 200) {
                $failures[] = 'Backend health endpoint did not return the expected JSON success payload';
                continue;
            }

            $passes[] = sprintf('Backend health endpoint responded with HTTP %d', $result['status']);
            continue;
        }

        if ($result['status'] < 200 || $result['status'] >= 400) {
            $failures[] = sprintf('%s responded with unexpected HTTP status %d', ucfirst($target['label']), $result['status']);
            continue;
        }

        if (trim($result['body']) === '') {
            $failures[] = sprintf('%s responded with an empty body', ucfirst($target['label']));
            continue;
        }

        $passes[] = sprintf('%s responded with HTTP %d', ucfirst($target['label']), $result['status']);
    }

    checkAdminApis($backendBaseUrl, $options, $passes, $warnings, $failures);
}

function scalarCount(PDO $pdo, string $sql): int
{
    $statement = $pdo->query($sql);
    return (int)$statement->fetchColumn();
}

function loadEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $values = [];
    foreach ((array)file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $rawValue] = explode('=', $line, 2);
        $name = trim($name);
        if ($name === '') {
            continue;
        }

        $value = trim($rawValue);
        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $values[$name] = $value;
    }

    return $values;
}

function isFalseLike(string $value): bool
{
    return in_array(strtolower(trim($value)), ['0', 'false', 'off', 'no'], true);
}

function createPdo(array $env): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $env['DB_HOST'],
        $env['DB_PORT'],
        $env['DB_DATABASE'],
        $env['DB_CHARSET'] ?? 'utf8'
    );

    return new PDO($dsn, (string)$env['DB_USERNAME'], (string)$env['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function tableExists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $statement->execute([$table]);

    return (int)$statement->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $statement->execute([$table, $column]);

    return (int)$statement->fetchColumn() > 0;
}

function deriveBackendBaseUrl(array $env): string
{
    $host = trim((string)($env['APP_HOST'] ?? ''));
    $port = trim((string)($env['APP_PORT'] ?? ''));

    if ($port === '' || !ctype_digit($port)) {
        return '';
    }

    if ($host === '' || $host === '0.0.0.0') {
        $host = '127.0.0.1';
    }

    return sprintf('http://%s:%s', $host, $port);
}

function ensureBackendHealthUrl(string $url): string
{
    $url = normalizeUrl($url);
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    if ($path === '/api/health') {
        return $url;
    }

    return rtrim($url, '/') . '/api/health';
}

function normalizeUrl(string $url): string
{
    $url = stripFragment(trim($url));
    if ($url === '') {
        return $url;
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }

    return $url;
}

function stripFragment(string $url): string
{
    $fragmentPosition = strpos($url, '#');
    if ($fragmentPosition === false) {
        return $url;
    }

    return substr($url, 0, $fragmentPosition);
}

function httpGet(string $url, int $timeout): array
{
    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPGET => true,
            CURLOPT_USERAGENT => 'aipay-release-verifier/1.0',
        ]);

        $body = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($body === false) {
            return [
                'ok' => false,
                'status' => $status,
                'body' => '',
                'error' => $error !== '' ? $error : 'Unknown cURL failure',
            ];
        }

        return [
            'ok' => $status >= 200 && $status < 400,
            'status' => $status,
            'body' => (string)$body,
            'error' => $status >= 200 && $status < 400 ? '' : "HTTP {$status}",
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'follow_location' => 1,
            'ignore_errors' => true,
            'header' => "User-Agent: aipay-release-verifier/1.0\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $matches)) {
            $status = (int)$matches[1];
            break;
        }
    }

    if ($body === false) {
        $lastError = error_get_last();
        return [
            'ok' => false,
            'status' => $status,
            'body' => '',
            'error' => $lastError['message'] ?? 'Unknown HTTP failure',
        ];
    }

    return [
        'ok' => $status >= 200 && $status < 400,
        'status' => $status,
        'body' => (string)$body,
        'error' => $status >= 200 && $status < 400 ? '' : "HTTP {$status}",
    ];
}

function checkAdminApis(string $backendBaseUrl, array $options, array &$passes, array &$warnings, array &$failures): void
{
    if ($options['skip-admin-api']) {
        $passes[] = 'Authenticated admin API checks skipped by request';
        return;
    }

    $adminUser = trim((string)($options['admin-user'] ?? ''));
    $adminPassword = trim((string)($options['admin-password'] ?? ''));
    if ($adminUser === '' || $adminPassword === '') {
        $warnings[] = 'Authenticated admin API checks were skipped because admin credentials were not provided';
        return;
    }

    if ($backendBaseUrl === '') {
        $warnings[] = 'Authenticated admin API checks were skipped because backend URL is unavailable';
        return;
    }

    $loginUrl = rtrim(normalizeUrl($backendBaseUrl), '/') . '/api/admin/login';
    $loginResult = httpJsonRequest(
        'POST',
        $loginUrl,
        ['username' => $adminUser, 'password' => $adminPassword],
        [],
        (int)$options['timeout']
    );

    if (!$loginResult['ok']) {
        $failures[] = 'Admin login check failed: ' . $loginResult['error'];
        return;
    }

    $loginPayload = json_decode($loginResult['body'], true);
    if (!is_array($loginPayload) || (int)($loginPayload['code'] ?? 0) !== 200) {
        $failures[] = 'Admin login check returned an unexpected payload';
        return;
    }

    $token = trim((string)($loginPayload['data']['token'] ?? ''));
    if ($token === '') {
        $failures[] = 'Admin login check did not return a usable token';
        return;
    }

    $passes[] = 'Admin login API responded successfully';

    $headers = ['Authorization: Bearer ' . $token];
    $checks = [
        [
            'label' => 'admin payment plugin list',
            'url' => rtrim(normalizeUrl($backendBaseUrl), '/') . '/api/admin/payment-plugins',
            'assert' => static function (array $payload): ?string {
                if ((int)($payload['code'] ?? 0) !== 200) {
                    return 'unexpected response code';
                }

                $items = $payload['data']['items'] ?? $payload['data']['records'] ?? null;
                if (!is_array($items) || $items === []) {
                    return 'plugin list is empty';
                }

                return null;
            },
        ],
        [
            'label' => 'admin payment methods',
            'url' => rtrim(normalizeUrl($backendBaseUrl), '/') . '/api/admin/payments',
            'assert' => static function (array $payload): ?string {
                if ((int)($payload['code'] ?? 0) !== 200) {
                    return 'unexpected response code';
                }

                $items = $payload['data']['items'] ?? $payload['data']['records'] ?? null;
                if (!is_array($items) || $items === []) {
                    return 'payment method list is empty';
                }

                return null;
            },
        ],
        [
            'label' => 'admin process overview',
            'url' => rtrim(normalizeUrl($backendBaseUrl), '/') . '/api/admin/processes',
            'assert' => static function (array $payload): ?string {
                if ((int)($payload['code'] ?? 0) !== 200) {
                    return 'unexpected response code';
                }

                $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
                $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
                $coreTotal = (int)($summary['core_total'] ?? 0);
                if ($coreTotal <= 0) {
                    return 'core process definitions were not detected';
                }

                $serverListen = trim((string)($data['environment']['server_listen'] ?? ''));
                $coreRunningTotal = (int)($summary['core_running_total'] ?? 0);
                if ($serverListen !== '' && $coreRunningTotal <= 0) {
                    return 'backend is reachable but process inspector reports no running core workers';
                }

                return null;
            },
        ],
    ];

    foreach ($checks as $check) {
        $result = httpJsonRequest('GET', (string)$check['url'], null, $headers, (int)$options['timeout']);
        if (!$result['ok']) {
            $failures[] = ucfirst((string)$check['label']) . ' check failed: ' . $result['error'];
            continue;
        }

        $payload = json_decode($result['body'], true);
        if (!is_array($payload)) {
            $failures[] = ucfirst((string)$check['label']) . ' check did not return valid JSON';
            continue;
        }

        $assertionError = $check['assert']($payload);
        if (is_string($assertionError) && $assertionError !== '') {
            $failures[] = ucfirst((string)$check['label']) . ' check failed: ' . $assertionError;
            continue;
        }

        $passes[] = ucfirst((string)$check['label']) . ' check passed';
    }
}

function httpJsonRequest(string $method, string $url, ?array $payload, array $headers, int $timeout): array
{
    $method = strtoupper(trim($method));
    $normalizedHeaders = array_values(array_filter(array_map('trim', $headers), static fn(string $header): bool => $header !== ''));
    $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload !== null && $body === false) {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'error' => 'JSON payload encoding failed',
        ];
    }

    if ($payload !== null) {
        $normalizedHeaders[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERAGENT => 'aipay-release-verifier/1.0',
            CURLOPT_HTTPHEADER => $normalizedHeaders,
        ]);

        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($responseBody === false) {
            return [
                'ok' => false,
                'status' => $status,
                'body' => '',
                'error' => $error !== '' ? $error : 'Unknown cURL failure',
            ];
        }

        return [
            'ok' => $status >= 200 && $status < 400,
            'status' => $status,
            'body' => (string)$responseBody,
            'error' => $status >= 200 && $status < 400 ? '' : "HTTP {$status}",
        ];
    }

    $headerText = "User-Agent: aipay-release-verifier/1.0\r\n";
    foreach ($normalizedHeaders as $header) {
        $headerText .= $header . "\r\n";
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => $timeout,
            'follow_location' => 1,
            'ignore_errors' => true,
            'header' => $headerText,
            'content' => $payload !== null ? $body : '',
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $header, $matches)) {
            $status = (int)$matches[1];
            break;
        }
    }

    if ($responseBody === false) {
        $lastError = error_get_last();
        return [
            'ok' => false,
            'status' => $status,
            'body' => '',
            'error' => $lastError['message'] ?? 'Unknown HTTP failure',
        ];
    }

    return [
        'ok' => $status >= 200 && $status < 400,
        'status' => $status,
        'body' => (string)$responseBody,
        'error' => $status >= 200 && $status < 400 ? '' : "HTTP {$status}",
    ];
}
