#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__);

try {
    $options = parseArguments($argv);
    $baseUrl = rtrim($options['base_url'], '/');
    $code = $options['code'];

    assertWebmanAlive($baseUrl);
    $token = adminLogin($baseUrl, $options['username'], $options['password']);

    $detail = requireApiCode(
        apiRequest('GET', $baseUrl . '/api/admin/payment-plugins/' . $code, null, $token),
        200,
        'load plugin detail'
    );
    $state = (array)($detail['state'] ?? []);

    if (!empty($state['enabled'])) {
        requireApiCode(
            apiRequest('POST', $baseUrl . '/api/admin/payment-plugins/' . $code . '/disable', [], $token),
            200,
            'disable plugin'
        );
    }

    $createdSnapshotId = null;
    if (!$options['skip_snapshot'] && !empty($state['installed'])) {
        $snapshot = requireApiCode(
            apiRequest('POST', $baseUrl . '/api/admin/payment-plugins/' . $code . '/snapshot', [
                'label' => $options['snapshot_label'],
            ], $token),
            200,
            'create recovery snapshot'
        );
        $createdSnapshotId = trim((string)(($snapshot['snapshot']['snapshot_id'] ?? '')));
        if ($createdSnapshotId === '') {
            throw new RuntimeException('snapshot creation did not return a snapshot_id');
        }
    }

    if (!empty($state['installed'])) {
        requireApiCode(
            apiRequest('POST', $baseUrl . '/api/admin/payment-plugins/' . $code . '/uninstall', [
                'purge' => true,
            ], $token),
            200,
            'mark plugin uninstalled with purge plan'
        );
    }

    $purgePlan = requireApiCode(
        apiRequest('POST', $baseUrl . '/api/admin/payment-plugins/' . $code . '/uninstall-plan', [
            'purge' => true,
        ], $token),
        200,
        'load purge plan'
    );
    $snapshotGuard = (array)($purgePlan['snapshot_guard'] ?? []);
    $hasSnapshot = !empty($snapshotGuard['has_snapshot']);

    if (!$hasSnapshot && !$options['force_without_snapshot']) {
        throw new RuntimeException(
            'purge plan has no recovery snapshot; rerun with --force-without-snapshot or keep snapshot creation enabled'
        );
    }

    requireApiCode(
        apiRequest('POST', $baseUrl . '/api/admin/payment-plugins/' . $code . '/cleanup-purge', [
            'confirm_code' => $code,
            'confirm_phrase' => purgeConfirmPhrase($purgePlan),
        ], $token),
        200,
        'purge cleanup'
    );

    $deletedSnapshotIds = [];
    if ($options['delete_snapshots']) {
        foreach (listSnapshotIds($baseUrl, $token, $code) as $snapshotId) {
            requireApiCode(
                apiRequest('POST', $baseUrl . '/api/admin/payment-plugins/' . $code . '/delete-snapshot', [
                    'snapshot_id' => $snapshotId,
                    'confirm_code' => $code,
                    'confirm_phrase' => discoverExpectedConfirmPhrase(
                        apiRequest('POST', $baseUrl . '/api/admin/payment-plugins/' . $code . '/delete-snapshot', [
                            'snapshot_id' => $snapshotId,
                            'confirm_code' => $code,
                            'confirm_phrase' => '',
                        ], $token),
                        'delete snapshot confirmation [' . $snapshotId . ']'
                    ),
                ], $token),
                200,
                'delete snapshot [' . $snapshotId . ']'
            );
            $deletedSnapshotIds[] = $snapshotId;
        }
    }

    $pluginDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'payments' . DIRECTORY_SEPARATOR . $code;
    $registryPath = $projectRoot . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'payment_plugins.json';

    printSummary([
        'plugin_code' => $code,
        'base_url' => $baseUrl,
        'created_snapshot_id' => $createdSnapshotId,
        'deleted_snapshot_ids' => $deletedSnapshotIds,
        'plugin_directory_exists' => file_exists($pluginDirectory) ? 'yes' : 'no',
        'registry_entry_exists' => registryEntryExists($registryPath, $code) ? 'yes' : 'no',
        'remaining_snapshot_ids' => listSnapshotIds($baseUrl, $token, $code),
    ]);

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function parseArguments(array $argv): array
{
    $args = $argv;
    array_shift($args);

    if ($args === [] || in_array('--help', $args, true) || in_array('-h', $args, true)) {
        printUsage();
        exit($args === [] ? 1 : 0);
    }

    $options = [
        'code' => null,
        'base_url' => 'http://127.0.0.1:8787',
        'username' => 'admin',
        'password' => '123456',
        'snapshot_label' => 'pre_remove_' . date('Ymd_His'),
        'skip_snapshot' => false,
        'force_without_snapshot' => false,
        'delete_snapshots' => true,
    ];

    for ($index = 0; $index < count($args); $index++) {
        $argument = $args[$index];

        if (!str_starts_with($argument, '--')) {
            if ($options['code'] === null) {
                $options['code'] = trim($argument);
                continue;
            }

            throw new InvalidArgumentException('unexpected positional argument [' . $argument . ']');
        }

        if ($argument === '--skip-snapshot') {
            $options['skip_snapshot'] = true;
            continue;
        }

        if ($argument === '--force-without-snapshot') {
            $options['force_without_snapshot'] = true;
            continue;
        }

        if ($argument === '--keep-snapshots') {
            $options['delete_snapshots'] = false;
            continue;
        }

        [$key, $value] = parseOption($args, $index);

        switch ($key) {
            case 'code':
                $options['code'] = trim($value);
                break;
            case 'base-url':
                $options['base_url'] = trim($value);
                break;
            case 'username':
                $options['username'] = trim($value);
                break;
            case 'password':
                $options['password'] = (string)$value;
                break;
            case 'snapshot-label':
                $options['snapshot_label'] = trim($value);
                break;
            default:
                throw new InvalidArgumentException('unknown option [--' . $key . ']');
        }
    }

    if ($options['code'] === null || $options['code'] === '') {
        throw new InvalidArgumentException('plugin code is required');
    }

    return $options;
}

function parseOption(array $args, int &$index): array
{
    $argument = $args[$index];
    $position = strpos($argument, '=');

    if ($position !== false) {
        return [
            substr($argument, 2, $position - 2),
            substr($argument, $position + 1),
        ];
    }

    $key = substr($argument, 2);
    if (!isset($args[$index + 1])) {
        throw new InvalidArgumentException('missing value for option [--' . $key . ']');
    }

    $index++;

    return [$key, $args[$index]];
}

function printUsage(): void
{
    echo <<<TXT
Usage:
  php tools/remove_payment_plugin.php demo_gateway
  php tools/remove_payment_plugin.php --code=demo_gateway --keep-snapshots

Options:
  --code                    Plugin code. Can also be passed as the first positional argument.
  --base-url                Webman API base URL. Default: http://127.0.0.1:8787
  --username                Admin username. Default: admin
  --password                Admin password. Default: 123456
  --snapshot-label          Snapshot label created before uninstall. Default: pre_remove_<timestamp>
  --skip-snapshot           Do not create a fresh recovery snapshot before uninstall.
  --force-without-snapshot  Allow purge cleanup to continue when no snapshot exists.
  --keep-snapshots          Keep recovery snapshots instead of deleting them after purge.
  --help, -h                Show this help message.
TXT;
    echo PHP_EOL;
}

function assertWebmanAlive(string $baseUrl): void
{
    requireApiCode(apiRequest('GET', $baseUrl . '/api/health'), 200, 'webman health check');
}

function adminLogin(string $baseUrl, string $username, string $password): string
{
    $data = requireApiCode(apiRequest('POST', $baseUrl . '/api/admin/login', [
        'username' => $username,
        'password' => $password,
    ]), 200, 'admin login');

    $token = trim((string)($data['token'] ?? ''));
    if ($token === '') {
        throw new RuntimeException('admin login did not return a bearer token');
    }

    return $token;
}

function listSnapshotIds(string $baseUrl, string $token, string $code): array
{
    $data = requireApiCode(
        apiRequest('GET', $baseUrl . '/api/admin/payment-plugins/' . $code . '/snapshots', null, $token),
        200,
        'load snapshots'
    );

    $ids = [];
    foreach ((array)($data['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $snapshotId = trim((string)($item['snapshot_id'] ?? ''));
        if ($snapshotId !== '') {
            $ids[] = $snapshotId;
        }
    }

    return $ids;
}

function purgeConfirmPhrase(array $purgePlan): string
{
    $guard = (array)($purgePlan['snapshot_guard'] ?? []);
    $phrase = trim((string)(
        !empty($guard['has_snapshot'])
            ? ($guard['purge_confirmation_phrase'] ?? '')
            : ($guard['missing_snapshot_confirmation_phrase'] ?? '')
    ));

    if ($phrase === '') {
        throw new RuntimeException('purge plan did not return a confirmation phrase');
    }

    return $phrase;
}

function registryEntryExists(string $registryPath, string $code): bool
{
    if (!is_file($registryPath)) {
        return false;
    }

    $decoded = json_decode((string)file_get_contents($registryPath), true);
    return is_array($decoded) && is_array($decoded[$code] ?? null);
}

function apiRequest(string $method, string $url, ?array $payload = null, ?string $token = null): array
{
    $headers = ['Accept: application/json'];
    if ($token !== null && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $options = ['headers' => $headers];
    if ($payload !== null) {
        $options['headers'][] = 'Content-Type: application/json';
        $options['body'] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $response = httpRequest($method, $url, $options);
    $decoded = json_decode($response['body'], true);
    if (!is_array($decoded)) {
        throw new RuntimeException(sprintf('api request [%s %s] did not return valid json', strtoupper($method), $url));
    }

    return [
        'status_code' => $response['status_code'],
        'json' => $decoded,
    ];
}

function httpRequest(string $method, string $url, array $options = []): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $headers = $options['headers'] ?? [];
    if ($headers !== []) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if (array_key_exists('body', $options)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$options['body']);
    }

    $body = curl_exec($ch);
    if ($body === false) {
        $message = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('http request failed: ' . $message);
    }

    $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [
        'status_code' => $statusCode,
        'body' => (string)$body,
    ];
}

function requireApiCode(array $response, int $expectedCode, string $label, ?int $expectedHttpStatus = null): array
{
    $statusCode = (int)($response['status_code'] ?? 0);
    $json = (array)($response['json'] ?? []);
    $apiCode = (int)($json['code'] ?? 0);

    if (($expectedHttpStatus !== null && $statusCode !== $expectedHttpStatus) || $apiCode !== $expectedCode) {
        throw new RuntimeException(sprintf(
            '%s failed: expected api code %d%s, got http=%d api=%d body=%s',
            $label,
            $expectedCode,
            $expectedHttpStatus === null ? '' : (' and http status ' . $expectedHttpStatus),
            $statusCode,
            $apiCode,
            json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ));
    }

    return (array)($json['data'] ?? []);
}

function discoverExpectedConfirmPhrase(array $response, string $label): string
{
    $statusCode = (int)($response['status_code'] ?? 0);
    $json = (array)($response['json'] ?? []);
    $message = (string)($json['message'] ?? '');

    if ($statusCode !== 422 || (int)($json['code'] ?? 0) !== 422) {
        throw new RuntimeException($label . ' failed: ' . json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    if (!preg_match('/confirm_phrase must equal "(.+)"/u', $message, $matches)) {
        throw new RuntimeException($label . ' did not expose a confirm_phrase hint');
    }

    return trim((string)($matches[1] ?? ''));
}

function printSummary(array $summary): void
{
    echo 'Payment plugin removal completed.' . PHP_EOL;
    foreach ($summary as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        echo '- ' . $key . ': ' . (string)$value . PHP_EOL;
    }
}
