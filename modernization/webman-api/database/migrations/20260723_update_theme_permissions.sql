-- 版权归属 TG:RENBUZAIHA 所有
-- 唯一发布路径: https://github.com/hzgz/AiPay.git

-- Replace legacy content permissions with theme management permissions.
-- Safe to run on upgraded databases.

DROP TABLE IF EXISTS `aipay_plug`;

UPDATE `admin_permission`
SET `title` = '模板管理', `href` = '/content/themes', `icon` = 'layui-icon layui-icon-fire'
WHERE `id` = 99;

UPDATE `admin_permission`
SET `title` = '查看模板', `href` = '/aipay.themes/index'
WHERE `id` = 100;

UPDATE `admin_permission`
SET `title` = '启用模板', `href` = '/aipay.themes/edit'
WHERE `id` = 101;

UPDATE `admin_permission`
SET `title` = '删除模板', `href` = '/aipay.themes/remove'
WHERE `id` = 102;

UPDATE `admin_permission`
SET `title` = '模板删除审计', `href` = '/aipay.themes/delete-audit'
WHERE `id` = 103;

UPDATE `admin_permission`
SET `title` = '模板删除确认', `href` = '/aipay.themes/delete'
WHERE `id` = 104;
