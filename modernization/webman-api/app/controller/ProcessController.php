<?php

namespace app\controller;

use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\SystemProcessInspector;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

class ProcessController
{
    public function index(Request $request): Response
    {
        $authorizationError = $this->authorize($request, ['index', 'pauseMonitor', 'resumeMonitor', 'cleanupSupervisors']);
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            return ApiResponse::success($this->inspector()->snapshot());
        } catch (Throwable $exception) {
            return ApiResponse::error('进程快照获取失败', 500, [
                'exception' => $exception->getMessage(),
            ], 500);
        }
    }

    public function pauseMonitor(Request $request): Response
    {
        $authorizationError = $this->authorize($request, ['pauseMonitor', 'index']);
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            return ApiResponse::success($this->inspector()->pauseMonitor(), '进程监控已暂停');
        } catch (Throwable $exception) {
            return ApiResponse::error('暂停进程监控失败', 500, [
                'exception' => $exception->getMessage(),
            ], 500);
        }
    }

    public function resumeMonitor(Request $request): Response
    {
        $authorizationError = $this->authorize($request, ['resumeMonitor', 'index']);
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            return ApiResponse::success($this->inspector()->resumeMonitor(), '进程监控已恢复');
        } catch (Throwable $exception) {
            return ApiResponse::error('恢复进程监控失败', 500, [
                'exception' => $exception->getMessage(),
            ], 500);
        }
    }

    public function cleanupDuplicateSupervisors(Request $request): Response
    {
        $authorizationError = $this->authorize($request, ['cleanupSupervisors', 'index']);
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            return ApiResponse::success(
                $this->inspector()->cleanupDuplicateSupervisors(),
                '重复守护进程已清理'
            );
        } catch (Throwable $exception) {
            return ApiResponse::error('清理重复守护进程失败', 500, [
                'exception' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * @param array<int, string> $authMarks
     */
    private function authorize(Request $request, array $authMarks): ?Response
    {
        return (new AdminRouteAuthorization())->authorizeAny($request, 'SystemProcesses', $authMarks);
    }

    private function inspector(): SystemProcessInspector
    {
        return new SystemProcessInspector();
    }
}
