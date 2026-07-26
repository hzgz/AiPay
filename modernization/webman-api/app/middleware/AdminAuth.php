<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\middleware;

use app\support\ApiResponse;
use app\support\DatabaseColumnInspector;
use support\Db;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class AdminAuth implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $token = $this->token($request);
        if ($token === '') {
            return ApiResponse::error('unauthorized', 401, null, 401);
        }

        $query = Db::table('admin_admin')
            ->select('id', 'username', 'nickname', 'status', 'token')
            ->where('token', $token)
            ->where('status', 1);

        if (DatabaseColumnInspector::hasColumn('admin_admin', 'delete_time')) {
            $query->whereNull('delete_time');
        }

        $admin = $query->first();

        if (!$admin) {
            return ApiResponse::error('unauthorized', 401, null, 401);
        }

        $request->admin = (array) $admin;
        return $handler($request);
    }

    private function token(Request $request): string
    {
        $authorization = (string) $request->header('authorization', '');
        if (stripos($authorization, 'Bearer ') === 0) {
            return trim(substr($authorization, 7));
        }

        return trim((string) $request->header('x-admin-token', ''));
    }
}
