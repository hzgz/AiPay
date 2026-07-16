<?php

declare(strict_types=1);

namespace app\process;

use app\service\order\OrderCallbackTaskService;
use Workerman\Timer;
use Workerman\Worker;

final class OrderCallbackWorker
{
    public function onWorkerStart(Worker $worker): void
    {
        $worker->name = 'order-callback-worker';

        Timer::add(1.0, function (): void {
            $service = new OrderCallbackTaskService();

            for ($index = 0; $index < 3; $index++) {
                $result = $service->processNextTask();
                if (empty($result['processed'])) {
                    break;
                }
            }
        });
    }
}
