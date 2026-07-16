-- 2026-07-12
-- Normalize the default public welcome announcement for clean AiPay releases.
-- Apply once against the existing project database to remove preview-environment wording.

UPDATE `ypay_news`
SET `title` = '欢迎使用 AiPay',
    `content` = '<p>AiPay 已完成 Webman 架构升级，商户可通过首页完成注册、登录与支付接入。</p><p>如需对接支付、回调或订单查询，请前往开发文档查看完整说明。</p>',
    `update_time` = NOW()
WHERE `id` = 1
  AND `title` = '欢迎使用 AiPay'
  AND (
    `content` = '当前为本地纯净预览环境。'
    OR `content` = '<p>当前为本地纯净预览环境。</p>'
  );

-- Rollback:
-- UPDATE `ypay_news`
-- SET `content` = '当前为本地纯净预览环境。',
--     `update_time` = NOW()
-- WHERE `id` = 1 AND `title` = '欢迎使用 AiPay';
