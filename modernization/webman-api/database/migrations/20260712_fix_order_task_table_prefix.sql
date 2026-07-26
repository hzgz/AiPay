-- 版权归属 TG:RENBUZAIHA 所有
-- 唯一发布路径: https://github.com/hzgz/AiPay.git

-- 2026-07-12
-- Repair callback/reconcile task table prefix drift without mutating the
-- original migration checksum that may already be recorded in production.

SET @legacy_callback_table := 'ypay_order_callback_task';
SET @legacy_reconcile_table := 'ypay_order_reconcile_task';
SET @callback_table := CONCAT('aip', 'ay_', 'order_callback_task');
SET @reconcile_table := CONCAT('aip', 'ay_', 'order_reconcile_task');

SET @callback_create_sql := CONCAT(
  'CREATE TABLE IF NOT EXISTS `', @callback_table, '` (',
  ' `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,',
  ' `task_key` varchar(160) NOT NULL,',
  ' `order_id` int(11) NOT NULL,',
  ' `merchant_id` int(11) NOT NULL DEFAULT ''0'',',
  ' `trade_no` varchar(64) DEFAULT NULL,',
  ' `out_trade_no` varchar(64) DEFAULT NULL,',
  ' `scene` varchar(32) NOT NULL DEFAULT ''settlement'',',
  ' `status` varchar(20) NOT NULL DEFAULT ''pending'',',
  ' `attempt_count` int(11) NOT NULL DEFAULT ''0'',',
  ' `max_attempts` int(11) NOT NULL DEFAULT ''8'',',
  ' `next_run_at` datetime DEFAULT NULL,',
  ' `locked_at` datetime DEFAULT NULL,',
  ' `started_at` datetime DEFAULT NULL,',
  ' `finished_at` datetime DEFAULT NULL,',
  ' `notify_url` text,',
  ' `return_url` text,',
  ' `callback_url` text,',
  ' `payload_json` longtext,',
  ' `last_http_status` int(11) DEFAULT NULL,',
  ' `last_error` text,',
  ' `last_response_body` longtext,',
  ' `create_time` datetime DEFAULT NULL,',
  ' `update_time` datetime DEFAULT NULL,',
  ' PRIMARY KEY (`id`),',
  ' UNIQUE KEY `uniq_task_key` (`task_key`),',
  ' KEY `idx_status_next_run` (`status`,`next_run_at`),',
  ' KEY `idx_order_scene` (`order_id`,`scene`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

PREPARE stmt FROM @callback_create_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @reconcile_create_sql := CONCAT(
  'CREATE TABLE IF NOT EXISTS `', @reconcile_table, '` (',
  ' `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,',
  ' `task_key` varchar(160) NOT NULL,',
  ' `order_id` int(11) NOT NULL,',
  ' `merchant_id` int(11) NOT NULL DEFAULT ''0'',',
  ' `account_id` int(11) NOT NULL DEFAULT ''0'',',
  ' `trade_no` varchar(64) DEFAULT NULL,',
  ' `out_trade_no` varchar(64) DEFAULT NULL,',
  ' `plugin_code` varchar(64) NOT NULL DEFAULT '''',',
  ' `channel_code` varchar(64) NOT NULL DEFAULT '''',',
  ' `payment_type` varchar(32) DEFAULT NULL,',
  ' `query_identifier` varchar(255) DEFAULT NULL,',
  ' `status` varchar(20) NOT NULL DEFAULT ''pending'',',
  ' `attempt_count` int(11) NOT NULL DEFAULT ''0'',',
  ' `max_attempts` int(11) NOT NULL DEFAULT ''30'',',
  ' `next_run_at` datetime DEFAULT NULL,',
  ' `locked_at` datetime DEFAULT NULL,',
  ' `started_at` datetime DEFAULT NULL,',
  ' `finished_at` datetime DEFAULT NULL,',
  ' `last_error` text,',
  ' `last_result_json` longtext,',
  ' `create_time` datetime DEFAULT NULL,',
  ' `update_time` datetime DEFAULT NULL,',
  ' PRIMARY KEY (`id`),',
  ' UNIQUE KEY `uniq_task_key` (`task_key`),',
  ' KEY `idx_status_next_run` (`status`,`next_run_at`),',
  ' KEY `idx_plugin_status` (`plugin_code`,`status`)',
  ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

PREPARE stmt FROM @reconcile_create_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_legacy_callback_table := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = @legacy_callback_table
);

SET @callback_copy_sql := IF(
  @has_legacy_callback_table > 0,
  CONCAT(
    'INSERT IGNORE INTO `', @callback_table, '` ',
    '(`task_key`,`order_id`,`merchant_id`,`trade_no`,`out_trade_no`,`scene`,`status`,`attempt_count`,`max_attempts`,`next_run_at`,`locked_at`,`started_at`,`finished_at`,`notify_url`,`return_url`,`callback_url`,`payload_json`,`last_http_status`,`last_error`,`last_response_body`,`create_time`,`update_time`) ',
    'SELECT `task_key`,`order_id`,`merchant_id`,`trade_no`,`out_trade_no`,`scene`,`status`,`attempt_count`,`max_attempts`,`next_run_at`,`locked_at`,`started_at`,`finished_at`,`notify_url`,`return_url`,`callback_url`,`payload_json`,`last_http_status`,`last_error`,`last_response_body`,`create_time`,`update_time` ',
    'FROM `', @legacy_callback_table, '`'
  ),
  'DO 1'
);

PREPARE stmt FROM @callback_copy_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_legacy_reconcile_table := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = @legacy_reconcile_table
);

SET @reconcile_copy_sql := IF(
  @has_legacy_reconcile_table > 0,
  CONCAT(
    'INSERT IGNORE INTO `', @reconcile_table, '` ',
    '(`task_key`,`order_id`,`merchant_id`,`account_id`,`trade_no`,`out_trade_no`,`plugin_code`,`channel_code`,`payment_type`,`query_identifier`,`status`,`attempt_count`,`max_attempts`,`next_run_at`,`locked_at`,`started_at`,`finished_at`,`last_error`,`last_result_json`,`create_time`,`update_time`) ',
    'SELECT `task_key`,`order_id`,`merchant_id`,`account_id`,`trade_no`,`out_trade_no`,`plugin_code`,`channel_code`,`payment_type`,`query_identifier`,`status`,`attempt_count`,`max_attempts`,`next_run_at`,`locked_at`,`started_at`,`finished_at`,`last_error`,`last_result_json`,`create_time`,`update_time` ',
    'FROM `', @legacy_reconcile_table, '`'
  ),
  'DO 1'
);

PREPARE stmt FROM @reconcile_copy_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
