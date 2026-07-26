<?php

declare(strict_types=1);

namespace app\process;

use app\service\order\OrderCallbackTaskService;
use app\support\Environment;
use Workerman\Timer;
use Workerman\Worker;

final class OrderCallbackWorker
{
    public function onWorkerStart(Worker $worker): void
    {
        $worker->name = 'order-callback-worker';
        $service = new OrderCallbackTaskService();
        $interval = max(0.05, Environment::float('ORDER_CALLBACK_POLL_INTERVAL', 0.2));
        $batchSize = max(1, Environment::int('ORDER_CALLBACK_BATCH_SIZE', 10));

        Timer::add($interval, function () use ($service, $batchSize): void {
            for ($index = 0; $index < $batchSize; $index++) {
                $result = $service->processNextTask();
                if (empty($result['processed'])) {
                    break;
                }
            }
        });
    }
}
