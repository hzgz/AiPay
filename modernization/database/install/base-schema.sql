-- AiPay core database schema
-- Generated from the current Webman database on 2026-07-17 12:28:57
-- Plugin-owned tables are excluded on purpose and remain managed by plugins/payments/*/migrations.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `admin_admin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL,
  `password` varchar(30) NOT NULL,
  `nickname` varchar(30) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `token` varchar(60) DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  `delete_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_admin_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) DEFAULT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `desc` text DEFAULT NULL,
  `ip` varchar(20) NOT NULL DEFAULT '',
  `user_agent` text NOT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_admin_permission` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `permission_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_admin_role` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_channel` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `create_type` int(1) DEFAULT 1,
  `code` varchar(50) DEFAULT NULL,
  `info` varchar(225) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `create_time` timestamp NULL DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `maxcount` int(11) NOT NULL DEFAULT 10,
  `delete_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_name` varchar(191) NOT NULL,
  `config_value` longtext DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_front_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(11) DEFAULT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `type` int(1) NOT NULL DEFAULT 0,
  `desc` text DEFAULT NULL,
  `ip` varchar(20) NOT NULL DEFAULT '',
  `user_agent` text NOT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_permission` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT 0,
  `title` varchar(50) DEFAULT NULL,
  `href` varchar(50) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `sort` tinyint(4) NOT NULL DEFAULT 99,
  `type` tinyint(1) DEFAULT 1,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_photo` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `href` varchar(255) DEFAULT NULL,
  `path` varchar(30) DEFAULT NULL,
  `mime` varchar(50) NOT NULL,
  `size` varchar(30) NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT 1,
  `ext` varchar(10) DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_role` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) DEFAULT NULL,
  `desc` varchar(100) DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  `delete_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_role_permission` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(11) DEFAULT NULL,
  `permission_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `money_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `type` int(1) DEFAULT NULL,
  `money` decimal(10,3) DEFAULT NULL,
  `beforemoney` decimal(10,3) DEFAULT NULL,
  `after` decimal(10,3) DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `memo` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_account` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `qr_url` varchar(2500) DEFAULT NULL,
  `qr_type` varchar(50) NOT NULL,
  `wxname` varchar(50) DEFAULT NULL,
  `zfb_pid` varchar(50) DEFAULT NULL,
  `wx_guid` varchar(50) DEFAULT NULL,
  `cloud_id` varchar(50) DEFAULT NULL,
  `qq` varchar(50) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `is_status` int(11) NOT NULL DEFAULT 1,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  `succcount` int(11) NOT NULL DEFAULT 0,
  `succprice` decimal(10,2) NOT NULL DEFAULT 0.00,
  `memo` varchar(50) DEFAULT NULL,
  `endtime` int(11) DEFAULT NULL,
  `cookie` text DEFAULT NULL,
  `tong_time` int(11) DEFAULT NULL,
  `allmaxcount` int(11) NOT NULL DEFAULT 0,
  `allmaxmoney` varchar(50) DEFAULT NULL,
  `daymaxcount` int(11) NOT NULL DEFAULT 0,
  `daymaxmoney` varchar(50) DEFAULT NULL,
  `remark` varchar(225) DEFAULT NULL,
  `money` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_cdk` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `type` int(3) DEFAULT NULL,
  `value` varchar(50) DEFAULT NULL,
  `code` varchar(50) NOT NULL,
  `status` int(1) NOT NULL DEFAULT 0,
  `create_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_domain` (
  `id` int(100) NOT NULL AUTO_INCREMENT,
  `user_id` int(100) NOT NULL,
  `sitename` varchar(255) DEFAULT NULL,
  `siteurl` varchar(255) DEFAULT NULL,
  `status` int(1) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `delete_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_navs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `url` text DEFAULT NULL,
  `is_target` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1,
  `create_time` timestamp NULL DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT 0,
  `delete_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_news` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` int(11) NOT NULL DEFAULT 1,
  `title` varchar(2500) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  `delete_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `sitename` varchar(50) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `trade_no` varchar(50) DEFAULT NULL,
  `out_trade_no` varchar(50) DEFAULT NULL,
  `alipay_order_no` varchar(255) DEFAULT NULL,
  `notify_url` text DEFAULT NULL,
  `return_url` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `money` decimal(10,2) DEFAULT NULL,
  `truemoney` decimal(10,2) DEFAULT NULL,
  `feilvmoney` decimal(10,3) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `return_num` int(50) DEFAULT 0,
  `ip` varchar(50) DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `out_time` int(11) DEFAULT NULL,
  `qrcode` text DEFAULT NULL,
  `h5_qrurl` text DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  `api_memo` text DEFAULT NULL,
  `pay_type` int(11) NOT NULL DEFAULT 1,
  `is_order_tips` int(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aipay_order_out_trade_no` (`out_trade_no`),
  KEY `idx_trade_no` (`trade_no`),
  KEY `idx_account_status_money_time` (`account_id`,`status`,`truemoney`,`out_time`),
  KEY `idx_alipay_order_no` (`alipay_order_no`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_order_callback_task` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `task_key` varchar(160) NOT NULL,
  `order_id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL DEFAULT 0,
  `trade_no` varchar(64) DEFAULT NULL,
  `out_trade_no` varchar(64) DEFAULT NULL,
  `scene` varchar(32) NOT NULL DEFAULT 'settlement',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 8,
  `next_run_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `notify_url` text DEFAULT NULL,
  `return_url` text DEFAULT NULL,
  `callback_url` text DEFAULT NULL,
  `payload_json` longtext DEFAULT NULL,
  `last_http_status` int(11) DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `last_response_body` longtext DEFAULT NULL,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_task_key` (`task_key`),
  KEY `idx_status_next_run` (`status`,`next_run_at`),
  KEY `idx_order_scene` (`order_id`,`scene`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_order_reconcile_task` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `task_key` varchar(160) NOT NULL,
  `order_id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL DEFAULT 0,
  `account_id` int(11) NOT NULL DEFAULT 0,
  `trade_no` varchar(64) DEFAULT NULL,
  `out_trade_no` varchar(64) DEFAULT NULL,
  `plugin_code` varchar(64) NOT NULL DEFAULT '',
  `channel_code` varchar(64) NOT NULL DEFAULT '',
  `payment_type` varchar(32) DEFAULT NULL,
  `query_identifier` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 30,
  `next_run_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `last_result_json` longtext DEFAULT NULL,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_task_key` (`task_key`),
  KEY `idx_status_next_run` (`status`,`next_run_at`),
  KEY `idx_plugin_status` (`plugin_code`,`status`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_paylist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `status` int(11) DEFAULT 1,
  `name` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `pid` text DEFAULT NULL,
  `key` text DEFAULT NULL,
  `other` text DEFAULT NULL,
  `create_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_payment` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `sort` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  `delete_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_payment_transaction_claim` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(32) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `order_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL DEFAULT 0,
  `trade_no` varchar(64) DEFAULT NULL,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_provider_transaction` (`provider`,`transaction_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_plug` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `downurl` text DEFAULT NULL,
  `introduce` text DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  `delete_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_poll_pool` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `type` varchar(20) NOT NULL,
  `round_type` tinyint(1) NOT NULL DEFAULT 1,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `current_index` int(11) NOT NULL DEFAULT 0,
  `current_weight` int(11) NOT NULL DEFAULT 1,
  `last_account_id` int(11) NOT NULL DEFAULT 0,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_type_status` (`user_id`,`type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_poll_pool_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `pool_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `weight` int(11) NOT NULL DEFAULT 1,
  `sort` int(11) NOT NULL DEFAULT 0,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pool_account` (`pool_id`,`account_id`),
  KEY `idx_user_pool` (`user_id`,`pool_id`),
  KEY `idx_account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_proxy` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `sort` int(25) DEFAULT 0,
  `address` varchar(225) DEFAULT NULL,
  `prot` varchar(50) DEFAULT NULL,
  `user` varchar(50) DEFAULT NULL,
  `pass` varchar(50) DEFAULT NULL,
  `status` int(11) DEFAULT 1,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_quicklogin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(255) DEFAULT NULL,
  `status` int(1) DEFAULT 1,
  `name` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `appid` varchar(50) DEFAULT NULL,
  `appkey` varchar(255) DEFAULT NULL,
  `create_time` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_recharge` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) DEFAULT NULL,
  `rtype` int(1) DEFAULT 0,
  `out_trade_no` varchar(225) DEFAULT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `money` decimal(10,2) DEFAULT 0.00,
  `qrcode` varchar(50) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `regdata` text DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  `out_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_out_trade_no` (`out_trade_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_risk` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(225) DEFAULT NULL,
  `url` varchar(2500) DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_ticket` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `type` int(1) unsigned zerofill NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `reply_content` text DEFAULT NULL,
  `creator_id` int(11) NOT NULL,
  `assignee_id` int(11) DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  `reply_time` timestamp NULL DEFAULT NULL,
  `status` int(1) unsigned zerofill NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_ticket_category` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `sort` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(50) DEFAULT NULL,
  `superior_id` int(11) DEFAULT NULL,
  `salt` varchar(50) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `wxpusher_uid` varchar(50) DEFAULT NULL,
  `tg_chat_id` varchar(50) DEFAULT NULL,
  `is_realName` int(11) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `idCard` varchar(255) DEFAULT NULL,
  `money` decimal(10,2) DEFAULT 0.00,
  `user_key` varchar(50) DEFAULT NULL,
  `vip_id` int(15) DEFAULT NULL,
  `vip_time` datetime DEFAULT NULL,
  `feilv` varchar(50) DEFAULT NULL,
  `is_bindqq` int(11) NOT NULL DEFAULT 0,
  `qq_sid` varchar(225) DEFAULT NULL,
  `is_bindwx` int(11) NOT NULL DEFAULT 0,
  `wx_sid` varchar(225) DEFAULT NULL,
  `googlekey` varchar(50) DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `token` varchar(225) DEFAULT NULL,
  `is_frozen` int(1) NOT NULL DEFAULT 0,
  `frozen_reason` varchar(255) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_userbasic` (
  `id` int(255) NOT NULL AUTO_INCREMENT,
  `user_id` int(255) NOT NULL,
  `timeout_method` int(1) NOT NULL DEFAULT 2,
  `timeout_url` varchar(255) DEFAULT '/',
  `timeout_time` varchar(255) DEFAULT '180',
  `loginfailure` int(10) DEFAULT 0,
  `console_notity` varchar(255) DEFAULT NULL,
  `console_temp` varchar(50) DEFAULT 'console',
  `is_voice_tips` int(1) DEFAULT 0,
  `voice_tips` varchar(255) DEFAULT '尊敬的用户，你本次交易金额为[money]',
  `login_tips` varchar(20) DEFAULT '0',
  `is_money_tips` varchar(20) DEFAULT '0',
  `money_tips` varchar(50) DEFAULT '0',
  `appkey` varchar(50) DEFAULT NULL,
  `order_tips` varchar(20) DEFAULT '0',
  `lose_tips` varchar(20) DEFAULT '0',
  `is_payPopUp` int(1) DEFAULT 0,
  `is_rate` int(1) DEFAULT 0,
  `cashierMode` int(3) DEFAULT 1,
  `channelMode` int(10) DEFAULT 1,
  `floating_amount` varchar(255) DEFAULT '0.01,0.02,0.03,0.04,0.05,0.06,0.07,0.08,0.09,0.1',
  `is_jump` int(1) DEFAULT 1,
  `hidden_sacnName` int(1) DEFAULT 0,
  `callback_hiddenName` int(1) DEFAULT 0,
  `diy_name` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `aipay_vip` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `icon` text DEFAULT NULL,
  `avatar_frame` varchar(255) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `feilv` varchar(50) DEFAULT NULL,
  `money` decimal(10,2) DEFAULT NULL,
  `viptime` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1,
  `sort` int(11) NOT NULL DEFAULT 0,
  `is_profiteer` int(1) DEFAULT NULL,
  `is_addChannelNum` int(1) DEFAULT NULL,
  `addChannelNum` int(100) DEFAULT NULL,
  `is_quota` int(1) DEFAULT 0,
  `today_quota` varchar(255) DEFAULT '0',
  `moon_quota` varchar(255) DEFAULT NULL,
  `is_passage` int(1) DEFAULT 0,
  `passage` varchar(255) DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `delete_time` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
