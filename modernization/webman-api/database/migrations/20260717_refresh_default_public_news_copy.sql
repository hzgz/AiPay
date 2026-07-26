-- 版权归属 TG:RENBUZAIHA 所有
-- 唯一发布路径: https://github.com/hzgz/AiPay.git

-- 2026-07-17
-- Refresh the default public announcement copy for clean AiPay releases.
-- This keeps existing installs free of internal modernization wording.

SET @news_table := CONCAT('aip', 'ay_', 'news');
SET @title := '欢迎使用 AiPay';
SET @content := '<p>欢迎使用 AiPay，可在首页完成商户注册、登录与接入配置。</p><p>支付接入、回调规则与订单查询请查看开发文档。</p>';
SET @legacy_phrase_1 := '%系统升级%';
SET @legacy_phrase_2 := '%本地纯净预览环境%';
SET @migration_sql := CONCAT(
    'UPDATE `', @news_table, '` ',
    'SET `title` = ', QUOTE(@title), ', ',
    '`content` = ', QUOTE(@content), ', ',
    '`update_time` = NOW() ',
    'WHERE `id` = 1 ',
    'AND `title` = ', QUOTE(@title), ' ',
    'AND (`content` LIKE ', QUOTE(@legacy_phrase_1), ' OR `content` LIKE ', QUOTE(@legacy_phrase_2), ' OR `content` LIKE ''%Webman 架构升级%'')'
);

PREPARE stmt FROM @migration_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
