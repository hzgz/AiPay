<?php

namespace app\controller;

use DomainException;
use app\service\payment\PaymentPluginManager;
use app\support\AdminRouteAuthorization;
use app\support\ApiResponse;
use app\support\RequestPayload;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

class PaymentPluginController
{
    public function index(): Response
    {
        try {
            return ApiResponse::success([
                'items' => $this->manager()->all(),
                'registry_residue' => $this->manager()->registryResidues(),
                'registry_residue_ledger' => $this->manager()->registryResidueLedger(),
            ]);
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function createScaffold(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'scaffold');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            return ApiResponse::success(
                $this->manager()->createScaffold(
                    RequestPayload::all($request),
                    $this->operatorFromRequest($request)
                ),
                '支付插件脚手架已创建'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function show(Request $request): Response
    {
        try {
            return ApiResponse::success($this->manager()->detail($this->codeFromRequest($request)));
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function history(Request $request): Response
    {
        try {
            return ApiResponse::success($this->manager()->history($this->codeFromRequest($request)));
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function bundle(Request $request): Response
    {
        try {
            return ApiResponse::success($this->manager()->bundle($this->codeFromRequest($request)));
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function recoveryVault(): Response
    {
        try {
            return ApiResponse::success($this->manager()->recoveryVault());
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function snapshots(Request $request): Response
    {
        try {
            return ApiResponse::success($this->manager()->snapshots($this->codeFromRequest($request)));
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function createSnapshot(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'createSnapshot');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $payload = RequestPayload::all($request);
            $label = isset($payload['label']) ? (string)$payload['label'] : null;

            return ApiResponse::success(
                $this->manager()->createSnapshot(
                    $this->codeFromRequest($request),
                    $label,
                    $this->operatorFromRequest($request)
                ),
                '恢复快照已创建'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function restoreSnapshot(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'restoreSnapshot');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);
        $code = $this->codeFromRequest($request);
        $snapshotId = trim((string)($payload['snapshot_id'] ?? ''));
        $confirmCode = trim((string)($payload['confirm_code'] ?? ''));
        $confirmPhrase = trim((string)($payload['confirm_phrase'] ?? ''));
        $expectedPhrase = $this->restoreConfirmationPhrase($code);

        if ($snapshotId === '') {
            return ApiResponse::error('snapshot_id is required', 422, null, 422);
        }

        if ($confirmCode !== $code) {
            return ApiResponse::error('confirm_code must match plugin code', 422, null, 422);
        }

        if ($confirmPhrase !== $expectedPhrase) {
            return ApiResponse::error(
                'confirm_phrase must equal "' . $expectedPhrase . '"',
                422,
                null,
                422
            );
        }

        try {
            return ApiResponse::success(
                $this->manager()->restoreSnapshot($code, $snapshotId, $this->operatorFromRequest($request)),
                '恢复快照已还原'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function deleteSnapshot(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'deleteSnapshot');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);
        $code = $this->codeFromRequest($request);
        $snapshotId = trim((string)($payload['snapshot_id'] ?? ''));
        $confirmCode = trim((string)($payload['confirm_code'] ?? ''));
        $confirmPhrase = trim((string)($payload['confirm_phrase'] ?? ''));
        $expectedPhrase = $this->deleteSnapshotConfirmationPhrase($snapshotId);

        if ($snapshotId === '') {
            return ApiResponse::error('snapshot_id is required', 422, null, 422);
        }

        if ($confirmCode !== $code) {
            return ApiResponse::error('confirm_code must match plugin code', 422, null, 422);
        }

        if ($confirmPhrase !== $expectedPhrase) {
            return ApiResponse::error(
                'confirm_phrase must equal "' . $expectedPhrase . '"',
                422,
                null,
                422
            );
        }

        try {
            return ApiResponse::success(
                $this->manager()->deleteSnapshot($code, $snapshotId, $this->operatorFromRequest($request)),
                '恢复快照已删除'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function cleanupRegistryResidue(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'cleanupRegistryResidue');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);
        $code = $this->codeFromRequest($request);
        $confirmCode = trim((string)($payload['confirm_code'] ?? ''));
        $confirmPhrase = trim((string)($payload['confirm_phrase'] ?? ''));

        if ($confirmCode !== $code) {
            return ApiResponse::error('confirm_code must match plugin code', 422, null, 422);
        }

        try {
            $residue = $this->manager()->registryResidues();
            $items = is_array($residue['items'] ?? null) ? $residue['items'] : [];
            $match = null;
            foreach ($items as $item) {
                if ((string)($item['plugin_code'] ?? '') === $code) {
                    $match = is_array($item) ? $item : null;
                    break;
                }
            }

            if ($match === null) {
                return ApiResponse::error('payment plugin registry residue was not found', 404, null, 404);
            }

            $guard = is_array($match['snapshot_guard'] ?? null) ? $match['snapshot_guard'] : [];
            $expectedPhrase = !(bool)($guard['has_snapshot'] ?? false)
                ? $this->cleanupRegistryResidueWithoutSnapshotConfirmationPhrase($code)
                : $this->cleanupRegistryResidueConfirmationPhrase($code);

            if ($confirmPhrase !== $expectedPhrase) {
                return ApiResponse::error(
                    'confirm_phrase must equal "' . $expectedPhrase . '"',
                    422,
                    null,
                    422
                );
            }

            return ApiResponse::success(
                $this->manager()->cleanupRegistryResidue($code, $this->operatorFromRequest($request)),
                '插件注册残留已清理'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function install(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'install');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            return ApiResponse::success(
                $this->manager()->install($this->codeFromRequest($request), $this->operatorFromRequest($request)),
                '插件已安装'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function repair(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'repair');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            return ApiResponse::success(
                $this->manager()->repair($this->codeFromRequest($request), $this->operatorFromRequest($request)),
                '插件已修复'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function upgrade(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'upgrade');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            return ApiResponse::success(
                $this->manager()->upgrade($this->codeFromRequest($request), $this->operatorFromRequest($request)),
                '插件已升级'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function enable(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'enable');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            return ApiResponse::success(
                $this->manager()->enable($this->codeFromRequest($request), $this->operatorFromRequest($request)),
                '插件已启用'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function saveConfig(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'saveConfig');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);
        $config = $payload['config'] ?? [];

        if (!is_array($config)) {
            return ApiResponse::error('config payload must be an object', 422, null, 422);
        }

        try {
            return ApiResponse::success(
                $this->manager()->saveConfig($this->codeFromRequest($request), $config, $this->operatorFromRequest($request)),
                '插件配置已保存'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function disable(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'disable');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            return ApiResponse::success(
                $this->manager()->disable($this->codeFromRequest($request), $this->operatorFromRequest($request)),
                '插件已停用'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function uninstallPlan(Request $request): Response
    {
        try {
            $payload = RequestPayload::all($request);
            $purge = $this->toBool($payload['purge'] ?? false);

            return ApiResponse::success(
                $this->manager()->uninstallPlan($this->codeFromRequest($request), $purge),
                '卸载计划已生成'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function uninstall(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'uninstall');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $payload = RequestPayload::all($request);
            $purge = $this->toBool($payload['purge'] ?? false);

            return ApiResponse::success(
                $this->manager()->uninstall($this->codeFromRequest($request), $purge, $this->operatorFromRequest($request)),
                $purge ? '插件已标记为卸载，并生成彻底清理计划' : '插件已标记为卸载'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function cleanupSafe(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'cleanupSafe');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        $payload = RequestPayload::all($request);
        $code = $this->codeFromRequest($request);
        $confirmCode = trim((string)($payload['confirm_code'] ?? ''));

        if ($confirmCode !== $code) {
            return ApiResponse::error('confirm_code must match plugin code', 422, null, 422);
        }

        try {
            return ApiResponse::success(
                $this->manager()->cleanupSafe($code, $this->operatorFromRequest($request)),
                '插件安全清理已完成'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    public function cleanupPurge(Request $request): Response
    {
        $authorizationError = $this->authorizeWrite($request, 'cleanupPurge');
        if ($authorizationError instanceof Response) {
            return $authorizationError;
        }

        try {
            $payload = RequestPayload::all($request);
            $code = $this->codeFromRequest($request);
            $confirmCode = trim((string)($payload['confirm_code'] ?? ''));

            if ($confirmCode !== $code) {
                return ApiResponse::error('confirm_code must match plugin code', 422, null, 422);
            }

            $plan = $this->manager()->uninstallPlan($code, true);
            if ((bool)($plan['requires_confirmation'] ?? true)) {
                $confirmPhrase = trim((string)($payload['confirm_phrase'] ?? ''));
                $snapshotGuard = is_array($plan['snapshot_guard'] ?? null) ? $plan['snapshot_guard'] : [];
                $expectedPhrase = !(bool)($snapshotGuard['has_snapshot'] ?? false)
                    ? $this->purgeWithoutSnapshotConfirmationPhrase($code)
                    : $this->purgeConfirmationPhrase($code);

                if ($confirmPhrase !== $expectedPhrase) {
                    return ApiResponse::error(
                        'confirm_phrase must equal "' . $expectedPhrase . '"',
                        422,
                        null,
                        422
                    );
                }
            }

            return ApiResponse::success(
                $this->manager()->cleanupPurge($code, $this->operatorFromRequest($request)),
                '插件彻底清理已完成'
            );
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }
    }

    private function manager(): PaymentPluginManager
    {
        return new PaymentPluginManager();
    }

    private function authorizeWrite(Request $request, string $authMark): ?Response
    {
        return (new AdminRouteAuthorization())->authorize($request, 'PaymentPlugins', $authMark);
    }

    private function codeFromRequest(Request $request): string
    {
        $code = $request->route ? $request->route->param('code', '') : '';
        return (string)$code;
    }

    private function operatorFromRequest(Request $request): array
    {
        return (array)($request->admin ?? []);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $value = strtolower(trim((string)$value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function purgeConfirmationPhrase(string $code): string
    {
        return '确认彻底清理 ' . trim($code);
    }

    private function purgeWithoutSnapshotConfirmationPhrase(string $code): string
    {
        return '无快照彻底清理 ' . trim($code);
    }

    private function cleanupRegistryResidueConfirmationPhrase(string $code): string
    {
        return '确认清理残留 ' . trim($code);
    }

    private function cleanupRegistryResidueWithoutSnapshotConfirmationPhrase(string $code): string
    {
        return '无快照清理残留 ' . trim($code);
    }

    private function restoreConfirmationPhrase(string $code): string
    {
        return '确认恢复 ' . trim($code);
    }

    private function deleteSnapshotConfirmationPhrase(string $snapshotId): string
    {
        return '确认删除快照 ' . trim($snapshotId);
    }

    private function handleException(Throwable $exception): Response
    {
        if ($exception instanceof InvalidArgumentException) {
            return ApiResponse::error($exception->getMessage(), 404, null, 404);
        }

        if ($exception instanceof DomainException) {
            return ApiResponse::error($exception->getMessage(), 422, null, 422);
        }

        if ($exception instanceof RuntimeException) {
            return ApiResponse::error($exception->getMessage(), 409, null, 409);
        }

        return ApiResponse::error('支付插件操作失败', 500, [
            'exception' => $exception->getMessage(),
        ], 500);
    }
}
