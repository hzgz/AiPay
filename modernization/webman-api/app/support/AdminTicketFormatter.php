<?php
/*
 * 版权归属 TG:RENBUZAIHA 所有
 * 唯一发布路径: https://github.com/hzgz/AiPay.git
 */

namespace app\support;

class AdminTicketFormatter
{
    public static function format(array $ticket): array
    {
        $id = (int)($ticket['id'] ?? 0);
        $type = (int)($ticket['type'] ?? 0);
        $status = (int)($ticket['status'] ?? 0);
        $creatorId = (int)($ticket['creator_id'] ?? 0);
        $assigneeId = (int)($ticket['assignee_id'] ?? 0);
        $title = AdminFixtureTextNormalizer::normalize(trim((string)($ticket['title'] ?? '')));
        $categoryName = AdminFixtureTextNormalizer::normalizeNullable(self::nullableString($ticket['category_name'] ?? null));
        $content = AdminFixtureTextNormalizer::normalizeNullable(self::nullableString($ticket['content'] ?? null));
        $replyContent = AdminFixtureTextNormalizer::normalizeNullable(self::nullableString($ticket['reply_content'] ?? null));
        $creatorUsername = AdminFixtureTextNormalizer::normalize(trim((string)($ticket['creator_username'] ?? '')));
        $creatorName = AdminFixtureTextNormalizer::normalize(trim((string)($ticket['creator_name'] ?? '')));
        $assigneeUsername = AdminFixtureTextNormalizer::normalize(trim((string)($ticket['assignee_username'] ?? '')));
        $assigneeNickname = AdminFixtureTextNormalizer::normalize(trim((string)($ticket['assignee_nickname'] ?? '')));
        $isReplied = $replyContent !== null;
        $isOpen = in_array($status, [0, 1], true);
        $typeName = $categoryName ?? ($type > 0 ? sprintf('分类 #%d', $type) : '未分配分类');
        $replyStateText = $isReplied ? '已回复' : '待回复';
        $statusText = self::statusLabel($status);

        return [
            'id' => $id,
            'ticket_label' => self::ticketLabel($id, $title),
            'type' => $type,
            'type_name_text' => $typeName,
            'type_name' => $typeName,
            'title' => $title,
            'content' => $content,
            'content_preview' => self::preview($content, 120) ?? '暂无工单内容',
            'reply_content' => $replyContent,
            'reply_preview' => self::preview($replyContent, 120) ?? '暂无管理员回复',
            'reply_state_text' => $replyStateText,
            'reply_state_label' => $replyStateText,
            'creator_id' => $creatorId,
            'creator_username' => $creatorUsername,
            'creator_name' => $creatorName === '' ? null : $creatorName,
            'creator_email' => self::nullableString($ticket['creator_email'] ?? null),
            'creator_mobile' => self::nullableString($ticket['creator_mobile'] ?? null),
            'creator_display' => self::merchantDisplay($creatorId, $creatorUsername, $creatorName),
            'assignee_id' => $assigneeId,
            'assignee_username' => $assigneeUsername,
            'assignee_nickname' => $assigneeNickname === '' ? null : $assigneeNickname,
            'assignee_display' => self::assigneeDisplay($assigneeId, $assigneeUsername, $assigneeNickname),
            'create_time' => self::nullableString($ticket['create_time'] ?? null),
            'update_time' => self::nullableString($ticket['update_time'] ?? null),
            'reply_time' => self::nullableString($ticket['reply_time'] ?? null),
            'status' => $status,
            'status_text' => $statusText,
            'status_label' => $statusText,
            'status_type' => self::statusType($status),
            'is_replied' => $isReplied,
            'is_open' => $isOpen,
            'delete_blocked' => false,
            'delete_guard_reason' => '当前允许彻底删除，确认后会永久移除工单记录。',
        ];
    }

    public static function formatCategory(array $category): array
    {
        $status = array_key_exists('status', $category) ? (int)$category['status'] : null;

        return [
            'id' => (int)($category['id'] ?? 0),
            'name' => trim((string)($category['name'] ?? '')),
            'status' => $status,
            'status_label' => $status === null ? '未知' : ($status === 1 ? '启用' : '禁用'),
        ];
    }

    private static function ticketLabel(int $ticketId, string $title): string
    {
        if ($title !== '') {
            return $title;
        }

        return $ticketId > 0 ? '工单 #' . $ticketId : '未命名工单';
    }

    private static function merchantDisplay(int $userId, string $username, string $name): string
    {
        if ($name !== '' && $username !== '') {
            return $name . ' / ' . $username;
        }

        if ($name !== '') {
            return $name;
        }

        if ($username !== '') {
            return $username;
        }

        return $userId > 0 ? '商户 #' . $userId : '未知商户';
    }

    private static function assigneeDisplay(int $adminId, string $username, string $nickname): string
    {
        if ($nickname !== '' && $username !== '') {
            return $nickname . ' / ' . $username;
        }

        if ($nickname !== '') {
            return $nickname;
        }

        if ($username !== '') {
            return $username;
        }

        return $adminId > 0 ? '管理员 #' . $adminId : '未分配';
    }

    private static function statusLabel(int $status): string
    {
        return match ($status) {
            0 => '新建',
            1 => '处理中',
            2 => '已解决',
            3 => '已关闭',
            default => '未知',
        };
    }

    private static function statusType(int $status): string
    {
        return match ($status) {
            0 => 'warning',
            1 => 'primary',
            2 => 'success',
            3 => 'info',
            default => 'danger',
        };
    }

    private static function preview(?string $text, int $length): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($text));
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) <= $length) {
            return $normalized;
        }

        return mb_substr($normalized, 0, max(1, $length - 3)) . '...';
    }

    private static function nullableString(mixed $value): ?string
    {
        $string = trim((string)$value);
        return $string === '' ? null : $string;
    }
}
