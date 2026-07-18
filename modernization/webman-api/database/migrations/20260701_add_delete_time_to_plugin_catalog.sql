-- 2026-07-01
-- Add compatibility-safe soft-delete support for plugin-download recycle / restore in the Webman admin backend.
-- Apply once against the existing project database before enabling plugin-download recycle actions.

SET @plugin_table := CONCAT('aip', 'ay_', 'plug');
SET @migration_sql := CONCAT(
    'ALTER TABLE `', @plugin_table, '` ',
    'ADD COLUMN `delete_time` timestamp NULL DEFAULT NULL COMMENT ''delete time'' AFTER `update_time`'
);

PREPARE stmt FROM @migration_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rollback:
-- ALTER TABLE `<plugin catalog table>` DROP COLUMN `delete_time`;
