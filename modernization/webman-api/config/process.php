<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use app\process\Http;
use app\support\Environment;
use support\Log;
use support\Request;
use app\process\OrderCallbackWorker;
use app\process\OrderReconcileWorker;

global $argv;

$listenHost = Environment::string('APP_HOST', '0.0.0.0');
$listenPort = max(1, Environment::int('APP_PORT', 8787));
$appEnv = strtolower(Environment::string('APP_ENV', 'production'));
$isProduction = $appEnv === 'production';
$detectedCpuCount = max(1, (int)cpu_count());
$defaultWorkerCount = min(2, $detectedCpuCount);
$workerCount = max(1, Environment::int('APP_WORKER_COUNT', $defaultWorkerCount));
$orderReconcileWorkerCount = max(1, Environment::int('ORDER_RECONCILE_WORKER_COUNT', 1));
$orderCallbackWorkerCount = max(1, Environment::int('ORDER_CALLBACK_WORKER_COUNT', 1));
$fileMonitorEnabled = Environment::bool(
    'APP_FILE_MONITOR',
    !$isProduction && !in_array('-d', $argv, true) && DIRECTORY_SEPARATOR === '/'
);
$memoryMonitorEnabled = Environment::bool(
    'APP_MEMORY_MONITOR',
    !$isProduction && DIRECTORY_SEPARATOR === '/'
);

return [
    'webman' => [
        'handler' => Http::class,
        'listen' => sprintf('http://%s:%d', $listenHost, $listenPort),
        'count' => $workerCount,
        'user' => '',
        'group' => '',
        'reusePort' => false,
        'eventLoop' => '',
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path()
        ]
    ],
    'order_reconcile' => [
        'handler' => OrderReconcileWorker::class,
        'count' => $orderReconcileWorkerCount,
        'reloadable' => true,
        'name' => 'Order Reconcile Worker',
    ],
    'order_callback' => [
        'handler' => OrderCallbackWorker::class,
        'count' => $orderCallbackWorkerCount,
        'reloadable' => true,
        'name' => 'Order Callback Worker',
    ],
    // File update detection and automatic reload
    'monitor' => [
        'handler' => app\process\Monitor::class,
        'reloadable' => false,
        'constructor' => [
            // Monitor these directories
            'monitorDir' => array_merge([
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/.env',
            ], glob(base_path() . '/plugin/*/app'), glob(base_path() . '/plugin/*/config'), glob(base_path() . '/plugin/*/api')),
            // Files with these suffixes will be monitored
            'monitorExtensions' => [
                'php', 'html', 'htm', 'env'
            ],
            'options' => [
                'enable_file_monitor' => $fileMonitorEnabled,
                'enable_memory_monitor' => $memoryMonitorEnabled,
            ]
        ]
    ]
];
