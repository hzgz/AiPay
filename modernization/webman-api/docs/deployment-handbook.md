# AiPay Windows / Linux 完整部署手册

## 1. 架构说明

这套系统上线时建议采用“前端壳 + Webman 后端”模式：

- 前端壳
  - 根地址：`/`
  - 游客首页：`/`
  - 商户端：`/#/merchant/...`
  - 管理员端：`/#/auth/...`
- Webman 后端
  - 本地监听：`127.0.0.1:8787`
  - 只负责 API、支付、回调、插件进程
  - 不作为对外首页站点使用

推荐上线方式：

- 对外只开放 80/443
- Nginx 承载 `console/` 静态资源
- Nginx 将 `/api/*`、`/submit.php`、`/mapi.php`、`/Pay/*` 等代理到 `127.0.0.1:8787`

这样做的好处：

- 前后端同域，最省心
- HTTPS 统一由 Nginx 终止
- Webman 可保持内网服务形态
- 管理员入口可以只保留直达地址

## 2. 发布包目录建议

### Windows

```text
D:\aipay\
  backend\
  console\
  database\
  docs\
```

### Linux

```text
/var/www/aipay/
  backend/
  console/
  database/
  docs/
```

## 2.1 账号初始化提醒

- 纯净发布包不会附带默认管理员账号、默认商户账号，也不会带出当前工作区数据库中的测试数据。
- 正式验收前，至少需要先准备：
  - 一个启用状态的管理员账号，用于 `/#/auth/login`
  - 一个可正常登录的商户账号，用于 `/#/merchant/login`
- 如果是全新纯净库，请先完成数据库导入，再按 `docs/database-installation.md` 的说明初始化管理员。
- 商户首个验收账号可以通过后台创建，也可以由你自己的初始化 SQL 提前写入。

## 3. 修改环境变量

编辑 `backend/.env`：

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_HOST=0.0.0.0
APP_PORT=8787

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pay
DB_USERNAME=pay
DB_PASSWORD=replace_me

AIPAY_ADMIN_FRONTEND_URL=https://portal.example.com
AIPAY_MERCHANT_FRONTEND_URL=https://portal.example.com
AIPAY_PUBLIC_FRONTEND_URL=https://portal.example.com
```

说明：

- 三个 `AIPAY_*_FRONTEND_URL` 建议保持同一个前端壳域名
- `APP_PORT=8787` 是 Webman 内部监听端口，不是对外公开端口

## 4. Windows 部署步骤

### 4.1 安装依赖

建议准备：

- PHP 8.1 或 8.2
- MySQL / MariaDB
- Nginx for Windows

PHP 至少需要：

- `pdo_mysql`
- `openssl`
- `curl`
- `mbstring`
- `fileinfo`

### 4.2 初始化数据库

全新空库：

```powershell
cd D:\aipay\backend
powershell -ExecutionPolicy Bypass -File deploy/windows/install-database.ps1 -WithBaseSchema
```

已有库补迁移：

```powershell
cd D:\aipay\backend
powershell -ExecutionPolicy Bypass -File deploy/windows/install-database.ps1
```

### 4.3 启动 Webman

```powershell
cd D:\aipay\backend
powershell -ExecutionPolicy Bypass -File deploy/windows/start-backend.ps1
```

健康检查：

```powershell
curl http://127.0.0.1:8787/api/health
```

### 4.4 配置 Nginx

把 `backend/deploy/nginx/console.example.conf` 复制为你的站点配置，修改：

- 域名
- 证书路径
- `root`
- 反代地址

关键点：

- `root` 指向 `console/`
- `/api/*` 等动态路径转发到 `127.0.0.1:8787`
- 80 全部跳转到 443

### 4.5 验收

```powershell
cd D:\aipay\backend
powershell -ExecutionPolicy Bypass -File deploy/windows/verify-deployment.ps1 `
  -BackendUrl http://127.0.0.1:8787 `
  -ConsoleUrl https://portal.example.com `
  -MerchantUrl https://portal.example.com `
  -PublicUrl https://portal.example.com
```

## 5. Linux 部署步骤

### 5.1 安装依赖

以 Debian / Ubuntu 为例：

```bash
sudo apt update
sudo apt install -y nginx unzip mariadb-client php-cli php-mysql php-curl php-mbstring php-xml php-zip php-bcmath php-intl
```

### 5.2 解压并授权

```bash
sudo mkdir -p /var/www/aipay
sudo unzip aipay-release.zip -d /var/www/aipay-release
sudo rsync -a /var/www/aipay-release/ /var/www/aipay/
sudo chown -R www-data:www-data /var/www/aipay
```

### 5.3 初始化数据库

全新空库：

```bash
cd /var/www/aipay/backend
bash deploy/linux/install-database.sh --with-base-schema
```

已有库补迁移：

```bash
cd /var/www/aipay/backend
bash deploy/linux/install-database.sh
```

### 5.4 启动 Webman

临时启动：

```bash
cd /var/www/aipay/backend
bash deploy/linux/start-backend.sh
```

### 5.4.1 推荐首装方式

如果你是全新 Debian / Ubuntu 服务器，建议直接使用：

```bash
cd /var/www/aipay/backend
sudo bash deploy/linux/install-production.sh \
  --domain=portal.example.com \
  --site-name=aipay \
  --certbot-email=ops@example.com
```

这个脚本会一次性完成：

- 运行目录和上传目录初始化
- `www-data` 权限修正
- `systemd` 服务写入与重启
- Nginx 站点渲染与重载
- 可选 `certbot --nginx` 申请 HTTPS 并开启 80 跳转

如果你暂时只想先跑 HTTP，可先不传 `--certbot-email`。

### 5.5 配置 systemd

```bash
sudo cp /var/www/aipay/backend/deploy/linux/aipay-webman.service.example /etc/systemd/system/aipay-webman.service
sudo systemctl daemon-reload
sudo systemctl enable aipay-webman
sudo systemctl restart aipay-webman
sudo systemctl status aipay-webman
```

如果已经运行了 `install-production.sh`，这一步通常已经自动完成，无需重复执行。

### 5.6 配置 Nginx

推荐直接以 `backend/deploy/nginx/console.example.conf` 为底稿。

示例流程：

```bash
sudo cp /var/www/aipay/backend/deploy/nginx/console.example.conf /etc/nginx/sites-available/aipay.conf
sudo ln -s /etc/nginx/sites-available/aipay.conf /etc/nginx/sites-enabled/aipay.conf
sudo nginx -t
sudo systemctl reload nginx
```

如果已经运行了 `install-production.sh`，Nginx 站点也会自动写好，通常只需要检查生成结果。

### 5.7 HTTPS 与 80 跳转

证书已经准备好的情况下，直接把模板中的：

- `ssl_certificate`
- `ssl_certificate_key`

改为你的真实路径即可。

如果你使用 Let’s Encrypt，可参考：

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d portal.example.com
```

如果你在 `install-production.sh` 中传了 `--certbot-email`，脚本会直接执行这一步。

## 6. 管理员入口策略

管理员端建议只保留直达地址：

- `https://portal.example.com/#/auth/login`

不要在游客首页公开放入口按钮，不要在页头直接暴露“管理员登录”。

商户端入口则可以公开：

- `https://portal.example.com/#/merchant/login`
- `https://portal.example.com/#/merchant/register`

## 7. 上线后必须检查的地址

- 游客首页：`https://portal.example.com/`
- 商户登录：`https://portal.example.com/#/merchant/login`
- 管理员登录：`https://portal.example.com/#/auth/login`
- 后端健康检查：`http://127.0.0.1:8787/api/health`

## 8. 正式验收建议

1. 先跑 `verify-deployment`
2. 再登录管理员端检查配置与插件
3. 再登录商户端检查通道与测试支付
4. 最后做真实订单闭环

真实闭环至少要确认：

- 下单成功
- 二维码或拉起页正常
- 订单回调成功
- 商户回调成功
- 订单状态和金额正确落账

## 9. 纯净交付说明

这份发布包是面向正式上线的纯净包，默认不应包含：

- 本地日志
- 测试上传文件
- 联调截图
- Smoke 快照
- 调试缓存

如果你需要再次打纯净包，回到仓库根目录执行：

```powershell
powershell -ExecutionPolicy Bypass -File tools/build-release-package.ps1
```
