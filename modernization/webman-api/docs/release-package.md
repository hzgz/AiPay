# AiPay 正式发布包说明

## 目标

这份发布包只保留正式上线需要的内容：

- `backend/`：Webman 后端，只负责 API、支付、回调、插件运行
- `console/`：前端壳，统一承载游客首页、商户端、管理员端
- `database/install/`：核心单文件安装 SQL、拆分结构 SQL 和后台权限种子
- `docs/`：部署、验收、交付说明

发布包默认遵循下面这套架构：

- 本地开发
  - `http://127.0.0.1:8132/`：游客首页、商户端、管理员端前端壳
  - `http://127.0.0.1:8787/`：Webman 后端
- 正式部署
  - `https://portal.example.com/`：前端壳根地址
  - `https://portal.example.com/#/merchant/login`：商户端入口
  - `https://portal.example.com/#/auth/login`：管理员直达地址
  - `127.0.0.1:8787`：仅供 Nginx 反向代理到 Webman，不直接对外放首页

管理员入口必须是直达地址，不建议在游客首页、页头导航或页脚中公开暴露。

## 发布包结构

```text
aipay-release-YYYYMMDD-HHmmss/
  backend/
  console/
  database/
    install/
      core-install.sql
      base-schema.sql
      admin-auth-seed.sql
  docs/
    release-package.md
    database-installation.md
    deployment-verification.md
    delivery-checklist.md
    deployment-handbook.md
    production-profile.example.json
  README-FIRST.txt
  release-manifest.json
```

后端目录内还会包含：

- `deploy/windows/*`
- `deploy/linux/*`
- `deploy/shared/*`
- `deploy/nginx/*`

其中 Linux 目录现在包含额外的首装脚本：

- `deploy/linux/install-production.sh`
  - 用于在 Debian / Ubuntu 上一次性完成目录权限、systemd、Nginx 站点和可选 Certbot HTTPS 初始化

## 打包时会自动清理什么

### 工作区残留

- `.codex-*` 临时目录
- `tmp-*` 临时文件
- 前端 `vite-8132*.log`、`.vite-8132*.log`
- 后端 `.webman*.log`
- `runtime/cache/*`
- `runtime/logs/*`
- `runtime/launch-cutover/*`
- `runtime/merchant-impersonation/*`
- `runtime/payment-plugin-audit/*`
- `runtime/payment-plugin-snapshots/*`
- `runtime/software-signatures/*`
- `runtime/tmp-config-audit.php`
- `runtime/legacy-api-compat-state.json`
- Smoke 主题和演示残留

### 发布包内不会带出的内容

- 本地联调脚本和 `tools/` 目录
- Vite / Webman 运行日志
- 插件运行时快照
- 软件签名缓存
- 本地测试上传文件
- 演示主题残留
- 历史测试模型文件

## 当前推荐部署方式

### 方案 A：单域名上线，推荐

同一个公开域名承载前端壳和后端接口：

- `/` 走静态前端 `console/`
- `/#/merchant/...` 走商户端
- `/#/auth/...` 走管理员端
- `/api/*`、`/submit.php`、`/mapi.php`、`/Pay/*` 等由 Nginx 反向代理到 `127.0.0.1:8787`

优点：

- 前后端同域，无需额外处理 CORS
- `VITE_API_URL=/api` 可直接使用
- 部署最简单，最适合正式商用

## 数据库文件说明

`database/install/` 现在分为两层：

- `core-install.sql`
  - 面向纯净安装包
  - 一份文件导入核心系统表和默认后台权限
- `base-schema.sql` + `admin-auth-seed.sql`
  - 面向排查、维护和拆分导入

而下面两类文件继续保留拆分状态，不并入单文件：

- `backend/database/migrations/*.sql`
  - Webman 后续升级补丁
- `backend/plugins/payments/*/migrations/*.sql`
  - 每个支付插件自己的独立库表

这样做的好处是：

- 全新安装足够简单
- 升级补丁不会污染首装 SQL
- 支付插件继续保持独立、可卸载、可清理

### 方案 B：分离 API 域名，可选

如果你确实需要把 Webman 单独挂到 `api.example.com`，可以使用 `deploy/nginx/public.example.conf` 作为后端专用域名模板。但发布包默认构建的前端产物仍然以 `/api` 相对路径为主，所以只有在你自己补齐反向代理或重新构建前端时才建议这样用。

## 快速上线顺序

1. 解压发布包。
2. 阅读 `docs/deployment-handbook.md`。
3. 修改 `backend/.env`。
4. 初始化数据库。
5. 启动 Webman 后端。
6. 配置 Nginx，把前端壳和后端代理接好。
7. 运行 `verify-deployment` 自检。
8. 使用真实订单做一次下单、回调、落账验收。

## 打包命令

仓库根目录执行：

```powershell
powershell -ExecutionPolicy Bypass -File tools/build-release-package.ps1
```

可选参数：

```powershell
powershell -ExecutionPolicy Bypass -File tools/build-release-package.ps1 `
  -Tag 20260714-release01 `
  -SkipFrontendBuild `
  -SkipWorkspaceCleanup
```

说明：

- `-Tag`：自定义发布包标签
- `-SkipFrontendBuild`：跳过前端重新构建，适合只改文档或发布脚本时使用
- `-SkipWorkspaceCleanup`：跳过工作区清理，一般不建议
- `-SkipPackageVerification`：跳过发布包结构校验，一般不建议

默认行为：

- 打包前自动清理工作区残留
- 打包后自动执行 `tools/verify-release-package.ps1`
- 只要结构、目录或文档不满足正式发布要求，就直接失败

## 交付前建议核对

- `console/` 是否为生产构建产物
- `backend/.env.example` 是否仍为示例值，未混入真实密码
- `upload-assets/` 是否为空白目录结构
- `plugins/payments/` 是否包含所有正式要交付的支付插件目录
- `docs/` 是否包含最新部署和验收文档
- 管理员入口是否只通过 `/#/auth/login` 直达，不在游客导航公开显示

## 相关文档

- `docs/deployment-handbook.md`
- `docs/database-installation.md`
- `docs/deployment-verification.md`
- `docs/delivery-checklist.md`
