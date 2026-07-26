<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

declare(strict_types=1);

namespace app\controller;

use app\support\SharedRedis;
use support\Db;
use Webman\Http\Response;

use function config;

class HealthController
{
    public function root(): Response
    {
        Db::select('select 1');

        return json([
            'code' => 200,
            'message' => 'success',
            'msg' => 'success',
            'data' => [
                'service' => 'aipay-webman-api',
                'status' => 'ok',
                'mode' => 'backend_only',
                'health_message' => 'AiPay backend service is running normally.',
                'redis' => SharedRedis::ping(),
                'time' => date('Y-m-d H:i:s'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function index(): Response
    {
        Db::select('select 1');

        $sessionType = (string)config('session.type', 'file');
        $sessionRedis = in_array($sessionType, ['redis', 'redis_cluster'], true)
            ? SharedRedis::ping(SharedRedis::sessionConfig())
            : null;

        return json([
            'code' => 200,
            'message' => 'success',
            'msg' => 'success',
            'data' => [
                'service' => 'aipay-webman-api',
                'database' => 'ok',
                'redis' => SharedRedis::ping(),
                'session_driver' => $sessionType,
                'session_redis' => $sessionRedis,
                'time' => date('Y-m-d H:i:s'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
