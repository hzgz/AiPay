-- 2026-07-01
-- Add soft-delete support for plugin-download recycle / restore in the Webman admin backend.
-- Apply once against the existing project database before enabling plugin-download recycle actions.

ALTER TABLE `ypay_plug`
    ADD COLUMN `delete_time` timestamp NULL DEFAULT NULL COMMENT 'delete time' AFTER `update_time`;

-- Rollback:
-- ALTER TABLE `ypay_plug` DROP COLUMN `delete_time`;
