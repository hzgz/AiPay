-- 2026-07-01
-- Add soft-delete support for admin recycle / restore in the Webman admin backend.
-- Apply once against the existing project database before enabling admin recycle actions.

ALTER TABLE `admin_admin`
    ADD COLUMN `delete_time` timestamp NULL DEFAULT NULL COMMENT 'delete time' AFTER `update_time`;

-- Rollback:
-- ALTER TABLE `admin_admin` DROP COLUMN `delete_time`;
