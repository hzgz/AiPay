CREATE TABLE IF NOT EXISTS `ypay_payment_transaction_claim` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` varchar(32) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `order_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL DEFAULT '0',
  `trade_no` varchar(64) DEFAULT NULL,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_provider_transaction` (`provider`,`transaction_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
