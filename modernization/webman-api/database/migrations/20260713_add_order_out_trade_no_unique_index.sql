SET @order_table := CONCAT('aip', 'ay_', 'order');
SET @old_index := 'idx_out_trade_no';
SET @new_index := CONCAT('uq_', 'aip', 'ay_order_out_trade_no');

SET @has_old_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = @order_table
    AND index_name = @old_index
);

SET @has_new_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = @order_table
    AND index_name = @new_index
);

SET @migration_sql := IF(
  @has_new_idx > 0,
  'DO 1',
  IF(
    @has_old_idx > 0,
    CONCAT(
      'ALTER TABLE `', @order_table, '` ',
      'DROP INDEX `', @old_index, '`, ',
      'ADD UNIQUE KEY `', @new_index, '` (`out_trade_no`)'
    ),
    CONCAT(
      'ALTER TABLE `', @order_table, '` ',
      'ADD UNIQUE KEY `', @new_index, '` (`out_trade_no`)'
    )
  )
);

PREPARE stmt FROM @migration_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
