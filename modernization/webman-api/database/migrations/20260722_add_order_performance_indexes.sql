SET @order_table := CONCAT('aip', 'ay_', 'order');
SET @account_table := CONCAT('aip', 'ay_', 'account');
SET @pool_item_table := CONCAT('aip', 'ay_', 'poll_pool_item');

SET @idx_status_create_time := 'idx_status_create_time';
SET @has_idx_status_create_time := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = @order_table
    AND index_name = @idx_status_create_time
);

SET @sql_status_create_time := IF(
  @has_idx_status_create_time > 0,
  'DO 1',
  CONCAT(
    'ALTER TABLE `', @order_table, '` ',
    'ADD KEY `', @idx_status_create_time, '` (`status`,`create_time`)'
  )
);

PREPARE stmt FROM @sql_status_create_time;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_user_status_create_time := 'idx_user_status_create_time';
SET @has_idx_user_status_create_time := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = @order_table
    AND index_name = @idx_user_status_create_time
);

SET @sql_user_status_create_time := IF(
  @has_idx_user_status_create_time > 0,
  'DO 1',
  CONCAT(
    'ALTER TABLE `', @order_table, '` ',
    'ADD KEY `', @idx_user_status_create_time, '` (`user_id`,`status`,`create_time`)'
  )
);

PREPARE stmt FROM @sql_user_status_create_time;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_status_out_time_account := 'idx_status_out_time_account';
SET @has_idx_status_out_time_account := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = @order_table
    AND index_name = @idx_status_out_time_account
);

SET @sql_status_out_time_account := IF(
  @has_idx_status_out_time_account > 0,
  'DO 1',
  CONCAT(
    'ALTER TABLE `', @order_table, '` ',
    'ADD KEY `', @idx_status_out_time_account, '` (`status`,`out_time`,`account_id`)'
  )
);

PREPARE stmt FROM @sql_status_out_time_account;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_code := 'idx_code';
SET @has_idx_code := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = @account_table
    AND index_name = @idx_code
);

SET @sql_code := IF(
  @has_idx_code > 0,
  'DO 1',
  CONCAT(
    'ALTER TABLE `', @account_table, '` ',
    'ADD KEY `', @idx_code, '` (`code`)'
  )
);

PREPARE stmt FROM @sql_code;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_user_type_status_is_status_code := 'idx_user_type_status_is_status_code';
SET @has_idx_user_type_status_is_status_code := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = @account_table
    AND index_name = @idx_user_type_status_is_status_code
);

SET @sql_user_type_status_is_status_code := IF(
  @has_idx_user_type_status_is_status_code > 0,
  'DO 1',
  CONCAT(
    'ALTER TABLE `', @account_table, '` ',
    'ADD KEY `', @idx_user_type_status_is_status_code, '` (`user_id`,`type`,`status`,`is_status`,`code`)'
  )
);

PREPARE stmt FROM @sql_user_type_status_is_status_code;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_pool_sort_account := 'idx_pool_sort_account';
SET @has_idx_pool_sort_account := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = @pool_item_table
    AND index_name = @idx_pool_sort_account
);

SET @sql_pool_sort_account := IF(
  @has_idx_pool_sort_account > 0,
  'DO 1',
  CONCAT(
    'ALTER TABLE `', @pool_item_table, '` ',
    'ADD KEY `', @idx_pool_sort_account, '` (`pool_id`,`sort`,`account_id`)'
  )
);

PREPARE stmt FROM @sql_pool_sort_account;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
