<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\process;

use app\service\order\OrderReconcileTaskService;
use app\support\Environment;
use Workerman\Timer;
use Workerman\Worker;

final class OrderReconcileWorker
{
    public function onWorkerStart(Worker $worker): void
    {
        $worker->name = 'order-reconcile-worker';
        $service = new OrderReconcileTaskService();
        $interval = max(0.1, Environment::float('ORDER_RECONCILE_POLL_INTERVAL', 0.5));
        $seedBatch = max(1, Environment::int('ORDER_RECONCILE_SEED_BATCH', 50));
        $processBatch = max(1, Environment::int('ORDER_RECONCILE_PROCESS_BATCH', 5));

        Timer::add($interval, function () use ($service, $seedBatch, $processBatch): void {
            $service->seedMissingTasks($seedBatch);

            for ($index = 0; $index < $processBatch; $index++) {
                $result = $service->processNextTask();
                if (empty($result['processed'])) {
                    break;
                }
            }
        });
    }
}
