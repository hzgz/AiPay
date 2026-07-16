CREATE TABLE IF NOT EXISTS `pay_plugin_jiaofeiyi_alipay_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_code` VARCHAR(64) NOT NULL DEFAULT 'jiaofeiyi_alipay',
  `level` VARCHAR(32) NOT NULL DEFAULT 'info',
  `message` VARCHAR(255) NOT NULL,
  `context` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plugin_created_at` (`plugin_code`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
