<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

use Webman\Http\Request;
use Webman\Http\Response;

class AdminRouteAuthorization
{
    public function authorize(
        Request $request,
        string $routeName,
        string $authMark,
        bool $allowWhenRouteUnmapped = true
    ): ?Response {
        return $this->authorizeAny($request, $routeName, [$authMark], $allowWhenRouteUnmapped);
    }

    /**
     * @param array<int, string> $authMarks
     */
    public function authorizeAny(
        Request $request,
        string $routeName,
        array $authMarks,
        bool $allowWhenRouteUnmapped = true
    ): ?Response {
        $admin = (array)($request->admin ?? []);
        $adminId = (int)($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return ApiResponse::error('unauthorized', 401, null, 401);
        }

        $normalizedAuthMarks = array_values(array_unique(array_filter(array_map(
            static fn(string $mark): string => trim($mark),
            $authMarks
        ))));
        if ($normalizedAuthMarks === []) {
            return null;
        }

        $authBuilder = new AdminRouteAuthMapBuilder();
        if ($allowWhenRouteUnmapped && !$authBuilder->routeHasAnyAuthMarks($routeName)) {
            return null;
        }

        foreach ($normalizedAuthMarks as $authMark) {
            if ($authBuilder->hasAuthMark($adminId, $routeName, $authMark)) {
                return null;
            }
        }

        return ApiResponse::error('forbidden', 403, [
            'route_name' => $routeName,
            'required_auth_marks' => $normalizedAuthMarks,
        ], 403);
    }
}
