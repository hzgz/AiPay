# AiPay 安装与部署教程

AiPay 推荐使用以下正式结构：

- `8132`：前端壳，只负责游客首页、商户端、管理员端
- `8787`：Webman 后端，只负责 API、支付、回调

## 1. Windows 本地开发

### 1.1 环境要求

- Node.js 20+
- pnpm 8+
- PHP 8.2+
- Composer 2
- MySQL / MariaDB

### 1.2 启动前端

```powershell
cd C:\path\to\aipay\modernization\art-design-admin
pnpm install
pnpm dev --host 127.0.0.1 --port 8132
```

### 1.3 启动后端

```powershell
cd C:\path\to\aipay\modernization\webman-api
composer install
php windows.php
```

### 1.4 本地访问地址

- 游客首页：`http://127.0.0.1:8132/`
- 商户登录：`http://127.0.0.1:8132/#/merchant/login`
- 商户注册：`http://127.0.0.1:8132/#/merchant/register`
- 管理员登录：`http://127.0.0.1:8132/#/auth/login`
- Webman 后端：`http://127.0.0.1:8787`

## 2. Linux 正式部署

### 2.1 推荐目录结构

```text
/var/www/aipay/
  backend/
  console/
```

- `console/`：前端静态产物
- `backend/`：Webman 后端
- 公网域名直接指向 `console/`
- Nginx 反代 `/api`、`/submit.php`、`/mapi.php`、`/Pay/*` 到 `127.0.0.1:8787`

### 2.2 一键安装

```bash
cd /var/www/aipay/backend
sudo bash deploy/linux/install-oneclick.sh
```

脚本会自动处理：

- 检查并安装基础依赖
- 写入 `backend/.env`
- 初始化数据库
- 创建管理员账号
- 可选创建测试商户
- 安装 Webman systemd 服务
- 生成 Nginx 配置
- 可选申请 HTTPS
- 自动执行部署验收

### 2.3 非交互示例

```bash
cd /var/www/aipay/backend
sudo bash deploy/linux/install-oneclick.sh \
  --domain=pay.example.com \
  --backend-port=8787 \
  --db-name=aipay \
  --db-user=aipay \
  --db-password='ReplaceMe123!' \
  --admin-user=adminroot \
  --admin-password='ReplaceMe123!' \
  --install-deps \
  --certbot-no-email \
  --non-interactive
```

## 3. aaPanel 部署

```bash
cd /www/wwwroot/aipay-release/backend
sudo bash deploy/linux/install-aapanel.sh --skip-nginx-apply
```

aaPanel 操作顺序：

1. 在 aaPanel 里 `Add site -> Static`
2. 保持 aaPanel 默认生成的站点 `Config`
3. 运行安装脚本生成 rewrite 模板
4. 把 rewrite 粘贴到 `网站 -> URL Rewrite`
5. 在 `SSL` 页签里申请证书并启用 `443`
6. 详细步骤见 `modernization/webman-api/docs/aapanel-install.md`

目录示例：

```text
/www/wwwroot/
  pay.example.com/
  aipay-release/
    backend/
    console/
```

非交互示例：

```bash
sudo bash deploy/linux/install-aapanel.sh \
  --domain=pay.example.com \
  --public-root=/www/wwwroot/pay.example.com \
  --nginx-conf=/www/server/panel/vhost/nginx/pay.example.com.conf \
  --rewrite-conf=/www/server/panel/vhost/rewrite/pay.example.com.conf \
  --db-name=aipay \
  --db-user=aipay \
  --db-password='ReplaceMe123!' \
  --admin-user=adminroot \
  --admin-password='ReplaceMe123!' \
  --skip-nginx-apply \
  --non-interactive
```

## 4. 80/443 反代与 HTTPS

公网只开放 `80/443`，Webman `8787` 只监听本机。

推荐反代规则：

- `/` -> 前端静态壳
- `/api/*` -> `http://127.0.0.1:8787/api/*`
- `/submit.php` -> `http://127.0.0.1:8787/submit.php`
- `/mapi.php` -> `http://127.0.0.1:8787/mapi.php`
- `/Pay/*` -> `http://127.0.0.1:8787/Pay/*`

aaPanel 场景注意：

- HTTPS 由 aaPanel 的 `SSL` 页签负责，rewrite 不能代替 `443`
- 如果模板里开启强制 HTTPS，并且你前面还挂了 Cloudflare，SSL 模式要用 `Full` 或 `Full (strict)`
- 不建议在 aaPanel 自带 Nginx 场景里首轮直接走 `certbot --nginx`

非 aaPanel 的通用 Linux Nginx 如需自动申请 HTTPS，可传：

- `--certbot-email=you@example.com`
- 或 `--certbot-no-email`

通用 Linux Nginx 手工申请示例：

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d pay.example.com
```

## 5. 纯净发布包

打包前清理本地残留：

```powershell
cd C:\path\to\aipay
powershell -ExecutionPolicy Bypass -File .\tools\prepare-pure-workspace.ps1
```

生成发布包：

```powershell
cd C:\path\to\aipay
powershell -ExecutionPolicy Bypass -File .\tools\build-release-package.ps1
```

## 6. 部署验收

Linux：

```bash
cd /var/www/aipay/backend
bash deploy/linux/verify-deployment.sh \
  --backend-url=http://127.0.0.1:8787 \
  --console-url=https://pay.example.com \
  --merchant-url=https://pay.example.com \
  --public-url=https://pay.example.com \
  --admin-user=adminroot \
  --admin-password='your-password'
```

## 7. 上线前检查

- 管理员密码已改为强密码
- 数据库、`.env`、HTTPS 已配置完成
- 管理员登录页 `/#/auth/login` 可正常使用
- 商户登录与注册页可正常使用
- 游客首页与移动端显示正常
- 支付插件、支付方式、商户通道已按正式环境配置好
- 回调、查单轮询、订单落账已联调完成
