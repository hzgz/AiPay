<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

# AiPay 交付检查清单

## 出包前

- 已确认 `console/` 为生产构建产物
- 已确认 `backend/.env.example` 不含真实生产密码
- 已确认发布包不携带本地日志、联调缓存、Smoke 残留
- 已确认 `plugins/payments/` 只保留需要交付的正式插件
- 已确认 `upload-assets/` 为干净目录结构
- 已确认 `docs/` 包含最新部署、验收、交付文档

## 交付给服务器前

- 已准备目标数据库
- 已准备 PHP 运行环境和 `pdo_mysql`
- 已准备 Nginx 与 HTTPS 证书
- 已确定前端壳域名
- 已确定 Webman 进程监听 `127.0.0.1:8787` 或内网地址

## 部署时

- 已修改 `backend/.env`
- 已执行数据库安装脚本
- 已启动 Webman
- 已接通前端壳静态站点
- 已把 `/api/*`、`/submit.php`、`/mapi.php`、`/Pay/*` 等反代到 Webman
- 已确认管理员入口只允许直达 `/#/auth/login`

## 部署后

- 游客首页可访问
- 商户登录页可访问
- 管理员登录页可访问
- 插件列表可访问
- 关键配置页可编辑
- `verify-deployment` 已通过
- 已完成真实支付与回调验收
- 已完成数据落账和商户回调验收
