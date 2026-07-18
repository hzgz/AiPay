-- 2026-07-17
-- Refresh the default public announcement copy for clean AiPay releases.
-- This keeps existing installs free of internal modernization wording.

SET @news_table := CONCAT('aip', 'ay_', 'news');
SET @title := '欢迎使用 AiPay';
SET @content := '<p>欢迎使用 AiPay，商户可在首页完成注册、登录与支付接入。</p><p>如需接入支付、回调或订单查询，请前往开发文档查看完整说明。</p>';
SET @legacy_phrase_1 := '%Webman 架构升级%';
SET @legacy_phrase_2 := '%本地纯净预览环境%';
SET @migration_sql := CONCAT(
    'UPDATE `', @news_table, '` ',
    'SET `title` = ', QUOTE(@title), ', ',
    '`content` = ', QUOTE(@content), ', ',
    '`update_time` = NOW() ',
    'WHERE `id` = 1 ',
    'AND `title` = ', QUOTE(@title), ' ',
    'AND (`content` LIKE ', QUOTE(@legacy_phrase_1), ' OR `content` LIKE ', QUOTE(@legacy_phrase_2), ')'
);

PREPARE stmt FROM @migration_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
