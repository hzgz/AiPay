-- 版权归属 TG:RENBUZAIHA 所有
-- 唯一发布路径: https://github.com/hzgz/AiPay.git

-- 2026-06-30
-- Add compatibility-safe soft-delete support for news recycle / restore in the Webman admin backend.
-- Apply once against the existing project database before enabling news recycle actions.

SET @news_table := CONCAT('aip', 'ay_', 'news');
SET @migration_sql := CONCAT(
    'ALTER TABLE `', @news_table, '` ',
    'ADD COLUMN `delete_time` timestamp NULL DEFAULT NULL COMMENT ''delete time'' AFTER `update_time`'
);

PREPARE stmt FROM @migration_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rollback:
-- ALTER TABLE `<news table>` DROP COLUMN `delete_time`;
