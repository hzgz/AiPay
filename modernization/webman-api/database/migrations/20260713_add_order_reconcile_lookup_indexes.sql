SET @reconcile_table := CONCAT('aip', 'ay_', 'order_reconcile_task');
SET @reconcile_sql := CONCAT(
  'ALTER TABLE `', @reconcile_table, '` ',
  'ADD KEY `idx_order_id` (`order_id`)'
);

PREPARE stmt FROM @reconcile_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @order_table := CONCAT('aip', 'ay_', 'order');
SET @order_sql := CONCAT(
  'ALTER TABLE `', @order_table, '` ',
  'ADD KEY `idx_alipay_order_no` (`alipay_order_no`(191))'
);

PREPARE stmt FROM @order_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
