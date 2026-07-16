<?php

declare(strict_types=1);

namespace app\process;

use app\service\order\OrderReconcileTaskService;
use Workerman\Timer;
use Workerman\Worker;

final class OrderReconcileWorker
{
    public function onWorkerStart(Worker $worker): void
    {
        $worker->name = 'order-reconcile-worker';

        Timer::add(2.0, function (): void {
            $service = new OrderReconcileTaskService();
            $service->seedMissingTasks(20);

            for ($index = 0; $index < 2; $index++) {
                $result = $service->processNextTask();
                if (empty($result['processed'])) {
                    break;
                }
            }
        });
    }
}
