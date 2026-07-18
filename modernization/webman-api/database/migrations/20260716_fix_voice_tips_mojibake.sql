-- 2026-07-16
-- Repair known mojibake voice-tip defaults and existing merchant settings.

SET @merchant_profile_table := CONCAT('aip', 'ay_', 'userbasic');
SET @fixed_voice_tips := '尊敬的用户，您本次交易金额为[money]';
SET @alter_sql := CONCAT(
  'ALTER TABLE `', @merchant_profile_table, '` ',
  'MODIFY COLUMN `voice_tips` varchar(255) DEFAULT ', QUOTE(@fixed_voice_tips)
);

PREPARE stmt FROM @alter_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @update_sql := CONCAT(
  'UPDATE `', @merchant_profile_table, '` ',
  'SET `voice_tips` = ', QUOTE(@fixed_voice_tips), ' ',
  'WHERE HEX(`voice_tips`) IN (',
  '''E7818FE5A983E69A9AE990A8E58BADE695A4E98EB4E587A4E7B49DE6B5A3E78AB3E6B9B0E5A886E280B2E6B0A6E98F84E692BBE599BEE6A3B0E6BF85E8B49F5B6D6F6E65795D'',',
  '''E7808FE5A983E69A9AE990A8E58BADE695A4E98EB4E587A4E7B49DE6B5A3E78AB3E6B9B0E5A886E280B2E6B0A6E98F84E692BBE599BEE6A3B0E6BF85E8B49F5B6D6F6E65795D'',',
  '''E9908FE5BF93EFB9A5E98F86E6B0B6E68383E98D95EE859FE69A8FE996B9E69D91E59A96E7BBB1E6BF87E68B85E99098E899ABE68BB1E6BF9EE5978FE282ACE58F89E59489E996BAE58BACE68D87E98DA3E782ACEFBC90E5A9B5E591B0E7A48B5B6D6F6E65795D''',
  ')'
);

PREPARE stmt FROM @update_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rollback:
-- ALTER TABLE `<merchant profile table>` MODIFY COLUMN `voice_tips` varchar(255) DEFAULT '<previous default>';
-- UPDATE `<merchant profile table>` SET `voice_tips` = '<previous default>' WHERE `voice_tips` = '尊敬的用户，您本次交易金额为[money]';
