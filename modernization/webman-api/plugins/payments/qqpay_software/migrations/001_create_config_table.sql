-- 版权归属 TG:RENBUZAIHA 所有
-- 唯一发布路径: https://github.com/hzgz/AiPay.git

CREATE TABLE IF NOT EXISTS `pay_plugin_qqpay_software_config` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plugin_code` VARCHAR(64) NOT NULL DEFAULT 'qqpay_software',
  `config_key` VARCHAR(100) NOT NULL,
  `config_value` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_plugin_key` (`plugin_code`, `config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
