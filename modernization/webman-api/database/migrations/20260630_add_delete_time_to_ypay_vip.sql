-- 2026-06-30
-- Add soft-delete support for VIP recycle / restore in the Webman admin backend.
-- Apply once against the existing project database before enabling VIP recycle actions.

ALTER TABLE `ypay_vip`
    ADD COLUMN `delete_time` timestamp NULL DEFAULT NULL COMMENT 'delete time' AFTER `create_time`;

-- Rollback:
-- ALTER TABLE `ypay_vip` DROP COLUMN `delete_time`;
