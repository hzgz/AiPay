<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

# AiPay aaPanel 安装说明

这套流程专门给 aaPanel 使用，核心原则是：

- 游客前台、商户端、管理员端共用一个前端壳
- Webman 后端只监听 `127.0.0.1:8787`
- aaPanel 的 Nginx 负责公网入口与 HTTPS

## 访问结构

1. 公网只开放 `80/443`
2. 前端静态文件由 aaPanel Nginx 对外提供
3. `/api/*`、`/submit.php`、`/mapi.php`、`/Pay/*` 等动态请求反代到 `127.0.0.1:8787`
4. `127.0.0.1:8787` 只允许本机访问，不对公网开放

公网健康检查使用：

- `https://你的域名/api/health`

不要把 `https://你的域名/health` 当成 aaPanel rewrite 主线下的公网验收地址。

## 部署步骤

1. 只安装 aaPanel 面板本体，不把首页软件一键安装当成主流程
2. 登录 aaPanel，先手工创建一个“静态网站”壳
3. 通过系统包安装 `MariaDB`、`PHP CLI`、`rsync` 等依赖
4. 上传 AiPay 纯净发布包
5. 执行 `deploy/linux/install-aapanel.sh --skip-nginx-apply`
   正式 HTTPS 域名建议同时补上 `--frontend-scheme=https`
6. 把脚本生成的 rewrite 模板粘贴到 aaPanel 的 `网站 -> URL Rewrite`
7. 在 aaPanel 的 `SSL` 页签里单独签发证书并启用 HTTPS

## 目录结构

```text
/www/wwwroot/
  pay.example.com/
  aipay-release/
    backend/
    console/
```

- `pay.example.com/` 由 aaPanel 手工创建成“静态网站”目录，对外提供 `80/443`
- `aipay-release/` 是你上传的纯净商用包目录，后端和原始前端产物都放这里
- 安装脚本会把 `aipay-release/console/` 同步到 `--public-root`

## 一键安装

```bash
cd /www/wwwroot/aipay-release/backend
sudo bash deploy/linux/install-aapanel.sh
```

脚本内容：

- 写入 `backend/.env`
- 自动写入 Redis 会话与共享缓存配置
- 创建数据库和数据库用户
- 导入基础库表
- 创建管理员账号
- 可选创建测试商户
- 同步 `console/` 到 aaPanel 公网目录
- 安装 Webman systemd 服务
- 生成 aaPanel rewrite 模板
- 生成 aaPanel 整站 Config 模板
- 可选自动覆盖 aaPanel 站点配置

## 先安装系统依赖

```bash
apt-get update
apt-get install -y mariadb-server redis-server rsync php-cli php-mysql php-curl php-mbstring php-xml php-zip php-bcmath php-intl php-gd php-redis php-opcache
systemctl enable --now mariadb
systemctl enable --now redis-server
```

## 非交互示例

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
  --frontend-scheme=https \
  --skip-nginx-apply \
  --non-interactive
```

## aaPanel 手工建站

- 站点类型使用“静态网站”
- 域名直接填写你的正式域名，例如 `pay.example.com`
- `Apply for SSL` 首轮部署先不要勾选
- `Path` 保持 aaPanel 默认的 `/www/wwwroot`，让它自动创建 `/www/wwwroot/<你的域名>`
- `FTP` 和 `Database` 首轮部署都先不要开
- 然后把发布包里的 `console/` 内容同步到这个目录
- 不要给这个站点套 PHP 运行规则
- 不要让 PHP 规则接管 `/submit.php`、`/mapi.php`、`/entry`、`/index.php`、`/admin.php`
- 如果同步前端时遇到 `.user.ini` 无法删除，先执行：

```bash
chattr -i /www/wwwroot/<你的域名>/.user.ini
rm -f /www/wwwroot/<你的域名>/.user.ini
```

站点目录示例：

```text
/www/wwwroot/pay.example.com/
  index.html
  assets/
  favicon.ico
```

## URL Rewrite 方案

1. `网站 -> Add site -> Static`
2. 保持 aaPanel 默认生成的站点 Config
3. 在 `SSL` 页签里申请证书并启用 `443`
4. 在 `网站 -> URL Rewrite` 里粘贴 AiPay rewrite 模板
5. Webman 继续只监听 `127.0.0.1:8787`

上线后对外验收优先检查：

- `https://你的域名/api/health`

## 前提

- 站点必须使用 aaPanel 的“静态网站”
- 站点主 Config 里必须保留 aaPanel 默认这行：

```nginx
include /www/server/panel/vhost/rewrite/<你的域名>.conf;
```

- `443` 和证书必须由 aaPanel `SSL` 页签负责
- 如果你之前已经手工接管过整份站点 Config，先恢复成 aaPanel 默认结构，再回到这条 rewrite 方案

## 注意事项

1. 不要把站点建成 PHP 网站。
如果给站点套了 PHP 运行规则，`/submit.php`、`/mapi.php` 这类入口可能会先被 PHP 规则截走。

2. rewrite 不能代替 SSL。
`443`、证书和 HTTPS 开关由 aaPanel `SSL` 页签负责。

3. 如果用了 Cloudflare，并且模板里保留了强制 HTTPS 这一行：

```nginx
if ($scheme != "https") {
    return 301 https://$host$request_uri;
}
```

Cloudflare 的 SSL 模式必须用 `Full` 或 `Full (strict)`，不要用 `Flexible`。

4. 不要在 Cloudflare 回源后继续用 `return 444` 拦截恶意路径。
源站 `444` 走过 Cloudflare 公网后通常会表现成 `520`，模板里已改成 `404/403`。

5. 正式 HTTPS 域名建议安装时直接传 `--frontend-scheme=https`。
脚本会同步写入 `AIPAY_*_FRONTEND_URL=https://你的域名` 和 `SESSION_SECURE=true`。
如果你先按 HTTP 装完，再回头启用 HTTPS，也要把这两项一起改掉。

6. 推荐同时启用 Redis 与 PHP CLI Opcache。
实测部署中，Webman 实际跑的是 CLI SAPI，不开 `php-opcache` 的 CLI 配置，高并发表现会明显偏低。

7. 如果是 Debian 13，且 `redis-server` 启动时报：
`error while loading shared libraries: libjemalloc.so.2: failed to map segment from shared object`
先不要直接删除宿主机里的 `/usr/local/lib/libjemalloc.so.2`，优先用 systemd override 强制 Redis 走系统库：

```bash
mkdir -p /etc/systemd/system/redis-server.service.d
cat >/etc/systemd/system/redis-server.service.d/override.conf <<'EOF'
[Service]
Environment="LD_LIBRARY_PATH=/usr/lib/x86_64-linux-gnu:/lib/x86_64-linux-gnu"
EOF

cat >/etc/sysctl.d/99-aipay-redis.conf <<'EOF'
vm.overcommit_memory = 1
net.core.somaxconn = 1024
EOF

sysctl -p /etc/sysctl.d/99-aipay-redis.conf
systemctl daemon-reload
systemctl reset-failed redis-server.service
systemctl restart redis-server.service
```

## 模板路径

1. 安装脚本生成的 rewrite 文件：
   `/www/server/panel/vhost/rewrite/<你的域名>.conf.aipay.rewrite.new`
2. 仓库内置 rewrite 模板：
   `backend/deploy/nginx/aapanel.rewrite.example.conf`
3. 整站 Config 模板：
   `backend/deploy/nginx/aapanel.site.example.conf`

## 成品 rewrite：直接粘贴到 aaPanel 的“网站 -> URL Rewrite”

只需要改 1 个地方：

- `8787` 改成你的 Webman 端口
- 如果你没改端口，就原样复制保存即可

```nginx
if ($scheme != "https") {
    return 301 https://$host$request_uri;
}

location ~* ^/(\.git|\.vscode|\.env|\.aws|console|setup|trace\.axd|info\.php|server-status|actuator|debug|telescope|ecp|graphql|api/graphql|api/gql|v2/_catalog|___proxy_subdomain|_internal|\.well-known/security\.txt|config\.json|\.DS_Store|settings\.js|js/env\.js|robots\.txt) {
    access_log off;
    return 404;
}

if ($http_user_agent ~* (l9scan|leakix|FAST-WebCrawler|rust_sniffer|CMS-Checker)) {
    return 403;
}

location ^~ /assets/ {
    expires 7d;
    add_header Cache-Control "public, immutable";
    try_files $uri =404;
}

location ^~ /theme-assets/ {
    proxy_pass http://127.0.0.1:8787;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection "";
}

location ^~ /upload/ {
    proxy_pass http://127.0.0.1:8787;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection "";
}

location ^~ /static/ {
    proxy_pass http://127.0.0.1:8787;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection "";
}

location ^~ /web/ {
    proxy_pass http://127.0.0.1:8787;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection "";
}

location ^~ /Deal/ {
    proxy_pass http://127.0.0.1:8787;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection "";
}

location ^~ /deal/ {
    proxy_pass http://127.0.0.1:8787;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection "";
}

location ~ ^/(Api|api|Pay|pay|User|user|My|my|Deal|deal|Index|index|News|news|Doc|doc|Demo|demo|Notify|notify)(/|$) {
    proxy_pass http://127.0.0.1:8787;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection "";
}

location = /favicon.ico {
    proxy_pass http://127.0.0.1:8787/favicon.ico;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection "";
}

location ~ ^/(submit\.php|mapi\.php|entry|index\.php|admin\.php|qrcode\.php)$ {
    proxy_pass http://127.0.0.1:8787;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Connection "";
}

location = /index.html {
    add_header Cache-Control "no-store, no-cache, must-revalidate";
}

location / {
    add_header Cache-Control "no-store, no-cache, must-revalidate";
    try_files $uri $uri/ /index.html;
}
```

## 整站 Config 备用方案

如果你需要手工接管整份 `server { ... }`，再看下面两个来源：

1. `backend/deploy/nginx/aapanel.site.example.conf`
2. 安装脚本生成的 `/www/server/panel/vhost/nginx/<你的域名>.conf.aipay.new`

但这已经不是本文推荐主线。对交付和售后来说，优先用 aaPanel 默认 Config + SSL 页签 + URL Rewrite，成功率更高。
