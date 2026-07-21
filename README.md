# AiPay

AiPay 是一款可商用的码支付系统，同时兼容易支付协议。

## 系统定位

- `8132`：统一前端壳，承载游客首页、商户端、管理员端
- `8787`：Webman 后端，仅负责 API、支付、回调、订单落账、轮询与进程任务
- 游客首页访问 `/`
- 商户端访问 `/#/merchant/...`
- 管理员端访问 `/#/auth/...`
- 管理员入口只保留直达地址，不在游客前台公开暴露

## 系统特点

- 前后端分离，职责清晰，部署结构更适合正式商用
- 支付能力全部插件化，每个插件一个独立目录，便于启用、停用、维护与后续扩展
- 游客前台、商户端、管理员端共用一个前端壳，统一品牌与交互风格
- Webman 后端仅承担接口、下单、回调、落账、轮询、进程管理等服务端职责
- 兼容易支付常见接入方式，便于第三方商户系统对接
- 面向纯净安装包、全新安装与正式环境交付，不再以旧 TP 项目兼容为核心

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
                   |-- services / callbacks / settle / polling / processes
                   '-- database / install / deploy
```

## 目录说明

```text
aipay/
  modernization/
    art-design-admin/    前端工程，负责游客首页、商户端、管理员端
    webman-api/          Webman 后端，负责 API、支付、回调、插件、进程
    database/            纯净安装数据库结构与种子
  tools/                 清理、打包、校验等工具
  docs/                  仓库级安装与交付文档
```

关键目录：

- `modernization/art-design-admin/src/views/public`
  - 游客首页、公告中心、开发文档、支付测试等公开页面
- `modernization/art-design-admin/src/views/merchant`
  - 商户端页面
- `modernization/art-design-admin/src/views/auth`
  - 管理员登录与认证流程
- `modernization/art-design-admin/src/views/system`
  - 管理后台、配置总览、菜单、媒体、快捷入口等
- `modernization/webman-api/plugins/payments`
  - 支付插件目录，每个插件独立管理
- `modernization/webman-api/deploy`
  - Linux、Windows、aaPanel 安装与验收脚本
- `modernization/database/install`
  - 纯净安装 SQL 与后台权限种子

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

## 部署文档

- [完整安装教程](./docs/INSTALL.md)
- [Linux 一键安装](./modernization/webman-api/docs/one-click-install.md)
- [aaPanel 安装说明](./modernization/webman-api/docs/aapanel-install.md)
- [发布包说明](./modernization/webman-api/docs/release-package.md)
- [部署验收说明](./modernization/webman-api/docs/deployment-verification.md)
