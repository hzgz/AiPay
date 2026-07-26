-- 版权归属 TG:RENBUZAIHA 所有
-- 唯一发布路径: https://github.com/hzgz/AiPay.git

-- 2026-07-01
-- Add soft-delete support for channel-catalog recycle / restore in the Webman admin backend.
-- Apply once against the existing project database before enabling channel-catalog recycle actions.

ALTER TABLE `admin_channel`
    ADD COLUMN `delete_time` timestamp NULL DEFAULT NULL COMMENT 'delete time' AFTER `maxcount`;

-- Rollback:
-- ALTER TABLE `admin_channel` DROP COLUMN `delete_time`;
