-- 2026-06-30
-- Add soft-delete support for navigation recycle / restore in the Webman admin backend.
-- Apply once against the existing project database before enabling navigation recycle actions.

ALTER TABLE `ypay_navs`
    ADD COLUMN `delete_time` timestamp NULL DEFAULT NULL COMMENT 'delete time' AFTER `sort`;

-- Rollback:
-- ALTER TABLE `ypay_navs` DROP COLUMN `delete_time`;
