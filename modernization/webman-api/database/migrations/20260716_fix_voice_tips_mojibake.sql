-- 2026-07-16
-- Repair known mojibake voice-tip defaults and existing merchant settings.

ALTER TABLE `ypay_userbasic`
  MODIFY COLUMN `voice_tips` varchar(255) DEFAULT '尊敬的用户，你本次交易金额为[money]';

UPDATE `ypay_userbasic`
SET `voice_tips` = '尊敬的用户，你本次交易金额为[money]'
WHERE `voice_tips` IN (
    '灏婃暚鐨勭敤鎴凤紝浣犳湰娆′氦鏄撻噾棰濅负[money]',
    '瀏婃暚鐨勭敤鎴凤紝浣犳湰娆′氦鏄撻噾棰濅负[money]',
    '鐏忓﹥鏆氶惃鍕暏閹村嚖绱濇担鐘虫拱濞嗏€叉唉閺勬捇鍣炬０婵呰礋[money]'
);

-- Rollback:
-- ALTER TABLE `ypay_userbasic`
--   MODIFY COLUMN `voice_tips` varchar(255) DEFAULT '灏婃暚鐨勭敤鎴凤紝浣犳湰娆′氦鏄撻噾棰濅负[money]';
-- UPDATE `ypay_userbasic`
-- SET `voice_tips` = '灏婃暚鐨勭敤鎴凤紝浣犳湰娆′氦鏄撻噾棰濅负[money]'
-- WHERE `voice_tips` = '尊敬的用户，你本次交易金额为[money]';
