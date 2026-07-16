<?php

namespace app\controller;

use app\support\ApiResponse;
use support\Db;
use Webman\Http\Response;

class HealthController
{
    public function root(): Response
    {
        Db::select('select 1');

        return ApiResponse::success([
            'service' => 'aipay-webman-api',
            'status' => 'ok',
            'mode' => 'backend_only',
            'message' => 'Webman backend is running. Use the frontend site on port 8132.',
            'time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function index(): Response
    {
        Db::select('select 1');

        return ApiResponse::success([
            'service' => 'aipay-webman-api',
            'database' => '正常',
            'time' => date('Y-m-d H:i:s'),
        ]);
    }
}
