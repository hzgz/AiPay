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

$listenHost = Environment::string('APP_HOST', '0.0.0.0');
$listenPort = max(1, Environment::int('APP_PORT', 8787));
$appEnv = strtolower(Environment::string('APP_ENV', 'production'));
$isProduction = $appEnv === 'production';
$detectedCpuCount = max(1, (int)cpu_count());
$defaultWorkerCount = $isProduction ? max(2, min(8, $detectedCpuCount)) : min(2, $detectedCpuCount);
$defaultOrderCallbackWorkerCount = $isProduction ? max(1, min(4, (int)ceil($detectedCpuCount / 2))) : 1;
$defaultOrderReconcileWorkerCount = 1;
$workerCount = max(1, Environment::int('APP_WORKER_COUNT', $defaultWorkerCount));
$orderReconcileWorkerCount = max(1, Environment::int('ORDER_RECONCILE_WORKER_COUNT', $defaultOrderReconcileWorkerCount));
$orderCallbackWorkerCount = max(1, Environment::int('ORDER_CALLBACK_WORKER_COUNT', $defaultOrderCallbackWorkerCount));
$orderReconcileWorkerEnabled = Environment::bool('ENABLE_ORDER_RECONCILE_WORKER', true);
$orderCallbackWorkerEnabled = Environment::bool('ENABLE_ORDER_CALLBACK_WORKER', true);

$config = [
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
];

if ($orderReconcileWorkerEnabled) {
    $config['order_reconcile'] = [
        'handler' => OrderReconcileWorker::class,
        'count' => $orderReconcileWorkerCount,
        'reloadable' => true,
        'name' => 'Order Reconcile Worker',
    ];
}

if ($orderCallbackWorkerEnabled) {
    $config['order_callback'] = [
        'handler' => OrderCallbackWorker::class,
        'count' => $orderCallbackWorkerCount,
        'reloadable' => true,
        'name' => 'Order Callback Worker',
    ];
}

return $config;
