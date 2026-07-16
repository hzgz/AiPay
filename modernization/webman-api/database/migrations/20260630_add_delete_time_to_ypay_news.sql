-- 2026-06-30
-- Add soft-delete support for news recycle / restore in the Webman admin backend.
-- Apply once against the existing project database before enabling news recycle actions.

ALTER TABLE `ypay_news`
    ADD COLUMN `delete_time` timestamp NULL DEFAULT NULL COMMENT 'delete time' AFTER `update_time`;

-- Rollback:
-- ALTER TABLE `ypay_news` DROP COLUMN `delete_time`;
