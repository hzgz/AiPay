SET @has_old_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'ypay_order'
    AND index_name = 'idx_out_trade_no'
);

SET @has_new_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'ypay_order'
    AND index_name = 'uq_ypay_order_out_trade_no'
);

SET @migration_sql := IF(
  @has_new_idx > 0,
  'DO 1',
  IF(
    @has_old_idx > 0,
    'ALTER TABLE `ypay_order` DROP INDEX `idx_out_trade_no`, ADD UNIQUE KEY `uq_ypay_order_out_trade_no` (`out_trade_no`)',
    'ALTER TABLE `ypay_order` ADD UNIQUE KEY `uq_ypay_order_out_trade_no` (`out_trade_no`)'
  )
);

PREPARE stmt FROM @migration_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
