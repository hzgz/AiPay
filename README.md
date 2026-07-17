# AiPay

AiPay 是一款可商用的码支付系统，同时兼容易支付协议。

## 系统定位

- `8132` 承载统一前端壳：游客首页、商户端、管理员端
- `8787` 承载 Webman 后端：API、支付、回调、进程任务
- 游客首页走 `/`
- 商户端走 `/#/merchant/...`
- 管理员端走 `/#/auth/...`
- 管理员入口只保留直达地址，不在前台导航公开暴露

## 系统介绍

AiPay 是一款可商用的码支付系统，同时兼容易支付协议：

- 支持正式商用部署，适合独立运营与长期维护
- 兼容易支付协议，便于商户接入、接口对接与系统迁移
- 前后端分离，职责清晰
- 支付能力插件化，每个插件一个目录
- 管理员、商户、游客前台共用统一前端壳
- Webman 后端只负责 API、生成支付、支付回调、订单落账、轮询与进程任务
- 面向纯净发布包、全新安装、二次部署与运维交付

## 系统架构

```text
Browser
  |
  |-- 8132 / frontend shell
  |     |-- /                    -> index99 游客首页
  |     |-- /#/merchant/...      -> 商户端 SPA
  |     '-- /#/auth/...          -> 管理员端 SPA
  |
  '-- reverse proxy
        |-- /api/*
        |-- /submit.php
        |-- /mapi.php
        |-- /Pay/*
        '-- /notify/*
             |
             '-- 8787 / Webman
                   |-- app/controller
                   |-- plugins/payments/*
                   |-- order settle / callback / polling / process
                   '-- database / install / deploy
```

## 系统目录说明

```text
aipay/
  modernization/
    art-design-admin/    前端工程，负责游客首页、商户端、管理员端
    webman-api/          Webman 后端，负责 API、支付、回调、插件、进程
    database/            纯净安装数据库结构与种子
  tools/                 纯净化、打包、校验等工具
  docs/                  根仓库交付文档
```

关键子目录：

- `modernization/art-design-admin/src/views/public`
  - 游客首页、公告中心、开发文档、支付测试等公开页面
- `modernization/art-design-admin/src/views/merchant`
  - 商户端页面
- `modernization/art-design-admin/src/views/auth`
  - 管理员登录与登录相关流程
- `modernization/art-design-admin/src/views/system`
  - 管理员控制台、配置、插件管理
- `modernization/webman-api/plugins/payments`
  - 支付插件目录，每个插件独立管理
- `modernization/webman-api/deploy`
  - Linux / Windows / aaPanel 安装、部署、验收脚本
- `modernization/database/install`
  - 纯净安装库结构、后台权限种子

## 系统优势

- 支付插件化，便于启用、停用、卸载和后续扩展
- 前台、商户端、管理员端共用一个前端壳，部署简单
- Webman 后端更轻，适合 1C1G 到更高配置渐进式扩展
- 内置 Linux 一键安装、aaPanel 安装、Windows 启动与部署验收脚本
- 支持纯净发布包，不混入本地缓存、日志、测试数据与旧产物

## 本地开发

前端：

```bash
cd modernization/art-design-admin
pnpm install
pnpm dev --host 127.0.0.1 --port 8132
```

后端：

```bash
cd modernization/webman-api
composer install
php windows.php
```

本地默认地址：

- 游客首页：`http://127.0.0.1:8132/`
- 商户端：`http://127.0.0.1:8132/#/merchant/login`
- 管理员端：`http://127.0.0.1:8132/#/auth/login`
- Webman 后端：`http://127.0.0.1:8787`

## 文档索引

- [完整安装教程](./docs/INSTALL.md)
- [Linux 一键安装](./modernization/webman-api/docs/one-click-install.md)
- [aaPanel 安装说明](./modernization/webman-api/docs/aapanel-install.md)
- [发布包说明](./modernization/webman-api/docs/release-package.md)
- [部署验收说明](./modernization/webman-api/docs/deployment-verification.md)
