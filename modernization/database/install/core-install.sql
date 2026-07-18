-- AiPay clean install asset
-- Generated from the current Webman database on 2026-07-17 12:28:57
-- This file is intended for brand-new installations.
-- Upgrade patches still stay under backend/database/migrations and plugins/payments/*/migrations.

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

-- AiPay admin authorization seed
-- Generated from the current Webman database on 2026-07-17 12:28:57
-- Safe to rerun on clean or existing databases.

INSERT IGNORE INTO `admin_permission` (`id`, `pid`, `title`, `href`, `icon`, `sort`, `type`, `status`) VALUES
(1, 0, '系统管理', '', 'layui-icon layui-icon layui-icon-username', 5, 0, 1),
(2, 1, '管理员账号', '/admin.admin/index', '', 1, 1, 1),
(3, 2, '新增管理员账号', '/admin.admin/add', '', 1, 1, 1),
(4, 2, '编辑管理员账号', '/admin.admin/edit', '', 1, 1, 1),
(5, 2, '管理员账号状态切换', '/admin.admin/status', '', 1, 1, 1),
(6, 2, '删除管理员账号', '/admin.admin/remove', '', 1, 1, 1),
(7, 2, '批量删除管理员账号', '/admin.admin/batchRemove', '', 1, 1, 1),
(8, 2, '管理员账号分配角色', '/admin.admin/role', '', 1, 1, 1),
(9, 2, '管理员账号分配权限', '/admin.admin/permission', '', 1, 1, 1),
(10, 2, '管理员账号回收站', '/admin.admin/recycle', '', 1, 1, 1),
(11, 1, '角色权限', '/admin.role/index', '', 99, 1, 1),
(12, 11, '新增角色权限', '/admin.role/add', '', 99, 1, 1),
(13, 11, '编辑角色权限', '/admin.role/edit', '', 99, 1, 1),
(14, 11, '删除角色权限', '/admin.role/remove', '', 99, 1, 1),
(15, 11, '角色权限分配权限', '/admin.role/permission', '', 99, 1, 1),
(16, 11, '角色权限回收站', '/admin.role/recycle', '', 99, 1, 1),
(17, 1, '菜单配置', '/admin.permission/index', '', 99, 1, 1),
(18, 17, '新增菜单配置', '/admin.permission/add', '', 99, 1, 1),
(19, 17, '编辑菜单配置', '/admin.permission/edit', '', 99, 1, 1),
(20, 17, '菜单配置状态切换', '/admin.permission/status', '', 99, 1, 1),
(21, 17, '删除菜单配置', '/admin.permission/remove', '', 99, 1, 1),
(22, 0, '系统管理', '', 'layui-icon layui-icon-set', 3, 0, 1),
(23, 22, '管理员日志', '/admin.admin/log', '', 2, 1, 1),
(24, 23, '清空管理员日志', '/admin.admin/removeLog', '', 1, 1, 1),
(25, 22, '配置总览', '/config/index', '', 1, 1, 1),
(26, 22, '图片素材', '/admin.photo/index', '', 2, 1, 1),
(27, 26, '新增图片素材', '/admin.photo/add', '', 2, 1, 1),
(28, 26, '删除图片素材', '/admin.photo/del', '', 2, 1, 1),
(29, 26, '图片素材列表', '/admin.photo/list', '', 2, 1, 1),
(30, 26, '新增单图素材', '/admin.photo/addPhoto', '', 2, 1, 1),
(31, 26, '新增多图素材', '/admin.photo/addPhotos', '', 2, 1, 1),
(32, 26, '删除图片素材', '/admin.photo/remove', '', 2, 1, 1),
(33, 26, '批量删除图片素材', '/admin.photo/batchRemove', '', 2, 1, 1),
(34, 0, '支付配置', '', 'layui-icon layui-icon layui-icon-app', 10, 0, 1),
(36, 35, '新增支付插件', '/admin.channel/add', NULL, 99, 1, 1),
(37, 35, '编辑支付插件', '/admin.channel/edit', NULL, 99, 1, 1),
(38, 35, '删除支付插件', '/admin.channel/remove', NULL, 99, 1, 1),
(39, 35, '批量删除支付插件', '/admin.channel/batchRemove', NULL, 99, 1, 1),
(40, 35, '支付插件回收站', '/admin.channel/recycle', NULL, 99, 1, 1),
(41, 34, '支付插件', '/admin.channel/index', 'layui-icon layui-icon layui-icon-fire', 97, 1, 1),
(42, 41, '新增支付插件', '/admin.channel/add', NULL, 99, 1, 1),
(43, 41, '编辑支付插件', '/admin.channel/edit', NULL, 99, 1, 1),
(44, 41, '删除支付插件', '/admin.channel/remove', NULL, 99, 1, 1),
(45, 41, '批量删除支付插件', '/admin.channel/batchRemove', NULL, 99, 1, 1),
(46, 41, '支付插件回收站', '/admin.channel/recycle', NULL, 99, 1, 1),
(53, 0, '商户管理', '', 'layui-icon layui-icon-username', 10, 0, 1),
(54, 53, '资金日志', '/money.log/index', 'layui-icon layui-icon-fire', 99, 1, 1),
(55, 54, '新增资金日志', '/money.log/add', NULL, 99, 1, 1),
(56, 54, '编辑资金日志', '/money.log/edit', NULL, 99, 1, 1),
(57, 54, '删除资金日志', '/money.log/remove', NULL, 99, 1, 1),
(58, 54, '批量删除资金日志', '/money.log/batchRemove', NULL, 99, 1, 1),
(59, 54, '资金日志回收站', '/money.log/recycle', NULL, 99, 1, 1),
(60, 53, '商户管理', '/aipay.user/index', 'layui-icon layui-icon layui-icon-fire', 98, 1, 1),
(61, 60, '新增商户管理', '/aipay.user/add', NULL, 99, 1, 1),
(62, 60, '编辑商户管理', '/aipay.user/edit', NULL, 99, 1, 1),
(63, 60, '删除商户管理', '/aipay.user/remove', NULL, 99, 1, 1),
(64, 60, '批量删除商户管理', '/aipay.user/batchRemove', NULL, 99, 1, 1),
(65, 60, '商户管理回收站', '/aipay.user/recycle', NULL, 99, 1, 1),
(66, 53, '会员套餐', '/aipay.vip/index', 'layui-icon layui-icon-fire', 99, 1, 1),
(67, 66, '新增会员套餐', '/aipay.vip/add', NULL, 99, 1, 1),
(68, 66, '编辑会员套餐', '/aipay.vip/edit', NULL, 99, 1, 1),
(69, 66, '删除会员套餐', '/aipay.vip/remove', NULL, 99, 1, 1),
(70, 66, '批量删除会员套餐', '/aipay.vip/batchRemove', NULL, 99, 1, 1),
(71, 66, '会员套餐回收站', '/aipay.vip/recycle', NULL, 99, 1, 1),
(72, 34, '收款账号', '/aipay.account/index', 'layui-icon layui-icon layui-icon-fire', 98, 1, 1),
(73, 72, '新增收款账号', '/aipay.account/add', NULL, 99, 1, 2),
(74, 72, '编辑收款账号', '/aipay.account/edit', NULL, 99, 1, 1),
(75, 72, '删除收款账号', '/aipay.account/remove', NULL, 99, 1, 1),
(76, 72, '批量删除收款账号', '/aipay.account/batchRemove', NULL, 99, 1, 1),
(77, 72, '收款账号回收站', '/aipay.account/recycle', NULL, 99, 1, 2),
(78, 0, '订单中心', '', 'layui-icon layui-icon-rmb', 10, 0, 1),
(79, 78, '订单中心', '/aipay.order/index', 'layui-icon layui-icon layui-icon-fire', 3, 1, 1),
(80, 79, '新增订单中心', '/aipay.order/add', NULL, 99, 1, 1),
(81, 79, '编辑订单中心', '/aipay.order/edit', NULL, 99, 1, 1),
(82, 79, '删除订单中心', '/aipay.order/remove', NULL, 99, 1, 1),
(83, 79, '批量删除订单中心', '/aipay.order/batchRemove', NULL, 99, 1, 1),
(84, 79, '订单中心回收站', '/aipay.order/recycle', NULL, 99, 1, 2),
(85, 78, '充值记录', '/aipay.recharge/index', 'layui-icon layui-icon layui-icon-fire', 2, 1, 1),
(86, 85, '新增充值记录', '/aipay.recharge/add', NULL, 99, 1, 1),
(87, 85, '编辑充值记录', '/aipay.recharge/edit', NULL, 99, 1, 1),
(88, 85, '删除充值记录', '/aipay.recharge/remove', NULL, 99, 1, 1),
(89, 85, '批量删除充值记录', '/aipay.recharge/batchRemove', NULL, 99, 1, 1),
(90, 85, '充值记录回收站', '/aipay.recharge/recycle', NULL, 99, 1, 1),
(91, 0, '风控中心', '', 'layui-icon layui-icon-diamond', 10, 0, 1),
(92, 91, '风控记录', '/aipay.risk/index', 'layui-icon layui-icon-fire', 99, 1, 1),
(93, 92, '新增风控记录', '/aipay.risk/add', NULL, 99, 1, 1),
(94, 92, '编辑风控记录', '/aipay.risk/edit', NULL, 99, 1, 1),
(95, 92, '删除风控记录', '/aipay.risk/remove', NULL, 99, 1, 1),
(96, 92, '批量删除风控记录', '/aipay.risk/batchRemove', NULL, 99, 1, 1),
(97, 92, '风控记录回收站', '/aipay.risk/recycle', NULL, 99, 1, 1),
(98, 0, '内容中心', '', 'layui-icon layui-icon-download-circle', 10, 0, 1),
(99, 98, '插件下载', '/aipay.plug/index', 'layui-icon layui-icon-fire', 99, 1, 1),
(100, 99, '新增插件下载', '/aipay.plug/add', NULL, 99, 1, 1),
(101, 99, '编辑插件下载', '/aipay.plug/edit', NULL, 99, 1, 1),
(102, 99, '删除插件下载', '/aipay.plug/remove', NULL, 99, 1, 1),
(103, 99, '批量删除插件下载', '/aipay.plug/batchRemove', NULL, 99, 1, 1),
(104, 99, '插件下载回收站', '/aipay.plug/recycle', NULL, 99, 1, 1),
(105, 22, '导航管理', '/aipay.navs/index', 'layui-icon layui-icon-fire', 99, 1, 1),
(106, 105, '新增导航管理', '/aipay.navs/add', NULL, 99, 1, 1),
(107, 105, '编辑导航管理', '/aipay.navs/edit', NULL, 99, 1, 1),
(108, 105, '删除导航管理', '/aipay.navs/remove', NULL, 99, 1, 1),
(109, 105, '批量删除导航管理', '/aipay.navs/batchRemove', NULL, 99, 1, 1),
(110, 105, '导航管理回收站', '/aipay.navs/recycle', NULL, 99, 1, 1),
(111, 22, '公告管理', '/aipay.news/index', 'layui-icon layui-icon-fire', 99, 1, 1),
(112, 111, '新增公告管理', '/aipay.news/add', NULL, 99, 1, 1),
(113, 111, '编辑公告管理', '/aipay.news/edit', NULL, 99, 1, 1),
(114, 111, '删除公告管理', '/aipay.news/remove', NULL, 99, 1, 1),
(115, 111, '批量删除公告管理', '/aipay.news/batchRemove', NULL, 99, 1, 1),
(116, 111, '公告管理回收站', '/aipay.news/recycle', NULL, 99, 1, 1),
(117, 0, '经营总览', '/index/home', 'layui-icon layui-icon layui-icon layui-icon-home', 1, 1, 1),
(118, 0, '在线更新', '/update', 'layui-icon layui-icon-auz', 99, 1, 1),
(119, 53, '商户日志', '/admin.front_log/index', 'layui-icon layui-icon-fire', 99, 1, 1),
(120, 119, '新增商户日志', '/admin.front_log/add', NULL, 99, 1, 1),
(121, 119, '编辑商户日志', '/admin.front_log/edit', NULL, 99, 1, 1),
(122, 119, '删除商户日志', '/admin.front_log/remove', NULL, 99, 1, 1),
(123, 119, '批量删除商户日志', '/admin.front_log/batchRemove', NULL, 99, 1, 1),
(124, 119, '商户日志回收站', '/admin.front_log/recycle', NULL, 99, 1, 1),
(138, 78, '商城总览', '/aipay.shop/index', 'layui-icon layui-inline layui-iconpicker-title', 1, 1, 1),
(139, 78, '人工调整资金日志', '/aipay.shop/plus', 'layui-icon layui-icon layui-icon layui-icon-face-s', 4, 1, 1),
(140, 22, '支付插件', '/aipay.paylist/index', 'layui-icon layui-icon layui-icon layui-icon layui-', 10, 1, 1),
(141, 22, '快捷登录', '/aipay.quicklogin/index', 'layui-icon layui-icon layui-icon-face-smile', 10, 1, 1),
(142, 53, '域名审核', '/aipay.domain/index', 'layui-icon layui-icon layui-icon-senior', 99, 1, 1),
(143, 78, '数据清理', '/aipay.shop/clear', 'layui-icon layui-icon layui-icon-face-smile', 99, 1, 1),
(144, 174, '主题模板', '/aipay.home/index', 'layui-icon layui-icon layui-icon layui-icon-face-s', 99, 1, 1),
(145, 148, '工单列表', '/aipay.shop/ticket', 'layui-icon layui-icon-tips', 99, 1, 1),
(146, 78, '卡券管理', '/aipay.shop/cdk', 'layui-icon layui-icon-face-smile', 99, 1, 1),
(147, 53, '商户管理邮件发送', '/aipay.user/email', 'layui-icon layui-icon-face-smile', 99, 1, 1),
(148, 0, '工单中心', '', 'layui-icon layui-icon-about', 10, 0, 1),
(161, 148, '工单分类', '/aipay.ticket_category/index', 'layui-icon layui-icon layui-icon-fire', 98, 1, 1),
(162, 161, '新增工单分类', '/aipay.ticket_category/add', NULL, 99, 1, 1),
(163, 161, '编辑工单分类', '/aipay.ticket_category/edit', NULL, 99, 1, 1),
(164, 161, '删除工单分类', '/aipay.ticket_category/remove', NULL, 99, 1, 1),
(165, 161, '批量删除工单分类', '/aipay.ticket_category/batchRemove', NULL, 99, 1, 1),
(166, 161, '工单分类回收站', '/aipay.ticket_category/recycle', NULL, 99, 1, 1),
(167, 34, '支付方式', '/aipay.payment/index', 'layui-icon layui-icon layui-icon-fire', 96, 1, 1),
(168, 167, '新增支付方式', '/aipay.payment/add', NULL, 99, 1, 1),
(169, 167, '编辑支付方式', '/aipay.payment/edit', NULL, 99, 1, 1),
(170, 167, '删除支付方式', '/aipay.payment/remove', NULL, 99, 1, 1),
(171, 167, '批量删除支付方式', '/aipay.payment/batchRemove', NULL, 99, 1, 1),
(172, 167, '支付方式回收站', '/aipay.payment/recycle', NULL, 99, 1, 1),
(173, 174, '主题模板', '/aipay.user_theme/index', 'layui-icon layui-icon-username', 99, 1, 1),
(174, 0, '主题模板', '', 'layui-icon layui-icon-layouts', 4, 0, 1),
(175, 174, '主题模板', '/aipay.pay_theme/index', 'layui-icon layui-icon-face-smile', 99, 1, 1);

INSERT IGNORE INTO `admin_role` (`id`, `name`, `desc`, `create_time`, `update_time`, `delete_time`) VALUES
(1, '超级管理员', '默认超级管理员角色', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL);

INSERT INTO `admin_admin_role` (`admin_id`, `role_id`)
SELECT 1, 1 FROM DUAL
WHERE EXISTS (SELECT 1 FROM `admin_admin` WHERE `id` = 1)
  AND NOT EXISTS (
    SELECT 1 FROM `admin_admin_role`
    WHERE `admin_id` = 1 AND `role_id` = 1
  );

INSERT INTO `admin_role_permission` (`role_id`, `permission_id`)
SELECT 1, p.`id`
FROM `admin_permission` AS p
WHERE NOT EXISTS (
  SELECT 1
  FROM `admin_role_permission` AS rp
  WHERE rp.`role_id` = 1
    AND rp.`permission_id` = p.`id`
);
