<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

# AiPay 正式发布包说明

## 目标

这份发布包只保留正式上线需要的内容：

- `backend/`：Webman 后端，只负责 API、支付、回调、插件运行
- `console/`：前端壳，统一承载游客首页、商户端、管理员端
- `database/install/`：纯净安装 SQL 与后台权限种子
- `docs/`：部署、验收、交付说明

发布包默认遵循以下架构：

- 本地开发
  - `http://127.0.0.1:8132/`：游客首页、商户端、管理员端前端壳
  - `http://127.0.0.1:8787/`：Webman 后端
- 正式部署
  - `https://portal.example.com/`：前端壳根地址
  - `https://portal.example.com/#/merchant/login`：商户端入口
  - `https://portal.example.com/#/auth/login`：管理员端直达地址
  - `127.0.0.1:8787`：仅供 Nginx 反向代理到 Webman，不对外直接暴露页面

## 发布包结构

```text
aipay-release-YYYYMMDD-HHmmss/
  backend/
  console/
  database/
    install/
      core-install.sql
  docs/
    release-package.md
    deployment-verification.md
    deployment-handbook.md
  README-FIRST.txt
  release-manifest.json
```

后端目录内还会包含：

- `deploy/windows/*`
- `deploy/linux/*`
- `deploy/shared/*`
- `deploy/nginx/*`

## 打包时自动清理的内容

工作区残留：

- `.codex-*` 临时目录
- `tmp-*` 临时文件
- 前端 `vite-8132*.log`
- 后端 `.webman*.log`
- `runtime/cache/*`
- `runtime/logs/*`
- 本地联调快照、测试缓存、临时签名文件

发布包内不会带出的内容：

- 本地联调脚本和无关工具残留
- Vite / Webman 运行日志
- 插件运行时快照
- 软件签名缓存
- 本地测试上传文件
- 历史演示或旧模板残留

## 部署结构

单域名部署，推荐正式商用：

- `/` 走静态前端 `console/`
- `/#/merchant/...` 走商户端
- `/#/auth/...` 走管理员端
- `/api/*`、`/submit.php`、`/mapi.php`、`/Pay/*` 统一由 Nginx 反代到 `127.0.0.1:8787`

## 数据库文件说明

`database/install/` 分为两层：

- `core-install.sql`
  - 面向纯净安装，一次导入核心系统表与默认后台权限
- `base-schema.sql` 与 `admin-auth-seed.sql`
  - 用于开发排查、拆分导入与生成单文件安装 SQL

以下文件继续保持拆分状态，不并入单文件：

- `backend/database/migrations/*.sql`
  - Webman 后续升级补丁
- `backend/plugins/payments/*/migrations/*.sql`
  - 每个支付插件各自的独立库表

## 打包命令

```powershell
powershell -ExecutionPolicy Bypass -File tools/build-release-package.ps1
```

脚本会同时生成：

- 发布目录 `modernization/releases/aipay-release-<tag>/`
- 同名 Linux 可直接 `unzip` 的 `.zip` 压缩包

可选参数：

```powershell
powershell -ExecutionPolicy Bypass -File tools/build-release-package.ps1 `
  -Tag 20260721-release01 `
  -SkipFrontendBuild `
  -SkipWorkspaceCleanup
```

参数：

- `-Tag`：自定义发布包标记
- `-SkipFrontendBuild`：跳过前端重新构建
- `-SkipWorkspaceCleanup`：跳过工作区清理
- `-SkipPackageVerification`：跳过发布包结构校验
