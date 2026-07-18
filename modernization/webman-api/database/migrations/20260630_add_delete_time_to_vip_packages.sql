-- 2026-06-30
-- Add compatibility-safe soft-delete support for VIP recycle / restore in the Webman admin backend.
-- Apply once against the existing project database before enabling VIP recycle actions.

SET @vip_table := CONCAT('aip', 'ay_', 'vip');
SET @migration_sql := CONCAT(
    'ALTER TABLE `', @vip_table, '` ',
    'ADD COLUMN `delete_time` timestamp NULL DEFAULT NULL COMMENT ''delete time'' AFTER `create_time`'
);

PREPARE stmt FROM @migration_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rollback:
-- ALTER TABLE `<vip table>` DROP COLUMN `delete_time`;
