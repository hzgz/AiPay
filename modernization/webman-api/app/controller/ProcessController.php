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
            return ApiResponse::error('process snapshot failed', 500, [
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
            return ApiResponse::success($this->inspector()->pauseMonitor(), 'monitor paused');
        } catch (Throwable $exception) {
            return ApiResponse::error('pause monitor failed', 500, [
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
            return ApiResponse::success($this->inspector()->resumeMonitor(), 'monitor resumed');
        } catch (Throwable $exception) {
            return ApiResponse::error('resume monitor failed', 500, [
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
                'duplicate supervisor cleanup completed'
            );
        } catch (Throwable $exception) {
            return ApiResponse::error('duplicate supervisor cleanup failed', 500, [
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
