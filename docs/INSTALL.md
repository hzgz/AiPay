# AiPay 安装与部署教程

AiPay 推荐固定架构如下：

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
- 管理员登录：`http://127.0.0.1:8132/#/auth/login`
- Webman 后端：`http://127.0.0.1:8787`

## 2. Linux 正式部署

### 2.1 建议目录

```text
/var/www/aipay/
  backend/
  console/
```

- `console/` 放前端构建产物
- `backend/` 放 Webman 后端
- 公网域名直接指向 `console/`
- Nginx 反代 `/api`、`/submit.php`、`/mapi.php`、`/Pay/*` 到 `127.0.0.1:8787`

### 2.2 一键安装

```bash
cd /var/www/aipay/backend
sudo bash deploy/linux/install-oneclick.sh
```

脚本会自动处理：

- 基础依赖检查与安装
- 写入 `backend/.env`
- 初始化数据库
- 创建管理员账号
- 可选创建测试商户
- 安装 Webman systemd 服务
- 生成 Nginx 配置
- 可选申请 HTTPS
- 最后自动执行部署验收

### 2.3 非交互示例

```bash
cd /var/www/aipay/backend
sudo bash deploy/linux/install-oneclick.sh \
  --domain=p.973700.xyz \
  --backend-port=8787 \
  --db-name=aipay_prod \
  --db-user=aipay_prod \
  --db-password='ReplaceMe123!' \
  --admin-user=adminroot \
  --admin-password='ReplaceMe123!' \
  --install-deps \
  --certbot-no-email \
  --non-interactive
```

## 3. aaPanel 部署

```bash
cd /www/wwwroot/aipay/backend
sudo bash deploy/linux/install-aapanel.sh
```

非交互示例：

```bash
sudo bash deploy/linux/install-aapanel.sh \
  --domain=p.973700.xyz \
  --public-root=/www/wwwroot/p.973700.xyz \
  --nginx-conf=/www/server/panel/vhost/nginx/p.973700.xyz.conf \
  --db-name=aipay_prod \
  --db-user=aipay_prod \
  --db-password='ReplaceMe123!' \
  --admin-user=adminroot \
  --admin-password='ReplaceMe123!' \
  --install-deps \
  --certbot-no-email \
  --non-interactive
```

## 4. 80/443 反代与 HTTPS

公网只开放 80/443，Webman `8787` 只监听本机。

推荐反代规则：

- `/` -> 前端静态壳
- `/api/*` -> `http://127.0.0.1:8787/api/*`
- `/submit.php` -> `http://127.0.0.1:8787/submit.php`
- `/mapi.php` -> `http://127.0.0.1:8787/mapi.php`
- `/Pay/*` -> `http://127.0.0.1:8787/Pay/*`

如需自动申请 HTTPS，可传：

- `--certbot-email=you@example.com`
- 或 `--certbot-no-email`

手工申请示例：

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d p.973700.xyz
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
  --console-url=https://p.973700.xyz \
  --merchant-url=https://p.973700.xyz \
  --public-url=https://p.973700.xyz \
  --admin-user=adminroot \
  --admin-password='your-password'
```

## 7. 上线前检查

- 管理员口令已改为强密码
- 数据库、`.env`、HTTPS 已配置
- 管理员登录页 `/#/auth/login` 可正常使用
- 商户登录与注册页可正常使用
- 游客首页与移动端显示正常
- 支付插件、支付方式、商户通道已按正式环境配好
- 回调、查单轮询、订单落账已联调完成
