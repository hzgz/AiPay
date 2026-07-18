-- 2026-07-12
-- Normalize the default public welcome announcement for clean AiPay releases.
-- Apply once against the existing project database to remove preview-environment wording.

SET @news_table := CONCAT('aip', 'ay_', 'news');
SET @title := '欢迎使用 AiPay';
SET @content := '<p>AiPay 已完成 Webman 架构升级，商户可通过首页完成注册、登录与支付接入。</p><p>如需对接支付、回调或订单查询，请前往开发文档查看完整说明。</p>';
SET @old_plain := '当前为本地纯净预览环境。';
SET @old_html := '<p>当前为本地纯净预览环境。</p>';
SET @migration_sql := CONCAT(
    'UPDATE `', @news_table, '` ',
    'SET `title` = ', QUOTE(@title), ', ',
    '`content` = ', QUOTE(@content), ', ',
    '`update_time` = NOW() ',
    'WHERE `id` = 1 ',
    'AND `title` = ', QUOTE(@title), ' ',
    'AND (`content` = ', QUOTE(@old_plain), ' OR `content` = ', QUOTE(@old_html), ')'
);

PREPARE stmt FROM @migration_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rollback:
-- UPDATE `<news table>` SET `content` = '<preview text>' WHERE `id` = 1 AND `title` = '欢迎使用 AiPay';
