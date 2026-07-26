<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

# AiPay 数据库安装

## 作用

1. 在空库时优先导入单一核心安装文件
2. 执行 `backend/database/migrations/*.sql`
3. 执行 `backend/plugins/payments/*/migrations/*.sql`

同时会自动补齐后台权限种子，避免纯净安装后管理员有界面但没有写权限。

## 安装前准备

- 已创建好 MySQL 或 MariaDB 数据库
- `backend/.env` 中的 `DB_*` 已改成真实值
- 运行 PHP 已安装 `pdo_mysql`
- 推荐先备份目标数据库

推荐版本：

- MySQL 8.x
- MariaDB 10.5+

## 相关文件

- `backend/deploy/shared/install-database.php`
- `backend/deploy/windows/install-database.ps1`
- `backend/deploy/linux/install-database.sh`
- `database/install/core-install.sql`
- 仓库开发资产：`database/install/base-schema.sql`
- 仓库开发资产：`database/install/admin-auth-seed.sql`

## 全新空库安装

### Windows

```powershell
cd backend
powershell -ExecutionPolicy Bypass -File deploy/windows/install-database.ps1 -WithBaseSchema
```

### Linux

```bash
cd backend
bash deploy/linux/install-database.sh --with-base-schema
```

行为：

- `--with-base-schema` 只用于空库
- 脚本会优先导入 `core-install.sql`
- 如果没有单文件，则回退为 `base-schema.sql + admin-auth-seed.sql`
- 插件表不会塞进核心安装文件，仍按插件迁移独立安装
- 正式发布包默认只携带 `core-install.sql`

## 已有库补迁移

如果基础表已经存在，只需要补 Webman 新增迁移：

### Windows

```powershell
cd backend
powershell -ExecutionPolicy Bypass -File deploy/windows/install-database.ps1
```

### Linux

```bash
cd backend
bash deploy/linux/install-database.sh
```

## 预检查模式

只看脚本准备执行什么，不真正写库：

### Windows

```powershell
cd backend
powershell -ExecutionPolicy Bypass -File deploy/windows/install-database.ps1 -WithBaseSchema -DryRun
```

### Linux

```bash
cd backend
bash deploy/linux/install-database.sh --with-base-schema --dry-run
```

## 迁移跟踪表

安装脚本会自动创建：

- `aipay_deploy_migrations`

它用于记录：

- 基础结构是否已导入
- 核心迁移是否已执行
- 插件迁移是否已执行
- 已执行 SQL 的校验摘要

作用：

- 同一份迁移被重复执行
- 迁移文件被改动后仍然悄悄继续跑

## SQL 文件分工

- `database/install/core-install.sql`
  - 给全新空库安装使用
  - 一份文件就能导入核心系统表和后台权限种子
- `database/install/base-schema.sql`
  - 保留拆分版核心表结构，便于人工排查
- `database/install/admin-auth-seed.sql`
  - 保留后台权限种子，便于单独补权限
- `backend/database/migrations/*.sql`
  - 只负责 Webman 后续版本升级补丁
- `backend/plugins/payments/*/migrations/*.sql`
  - 只负责各支付插件自己的库表，保持插件可装可卸、可清理无残留

也就是说：

- 正式发布包只需要携带 `core-install.sql`
- 插件数据库不并入核心安装文件
- 升级补丁不并入首装 SQL

## 安全规则

- 数据库非空但核心表缺失时，脚本会拒绝直接导入基础结构
- 已记录的迁移不会重复执行
- 已执行过的迁移如果文件内容被改动，会直接报错
- 后台权限种子缺失时会自动补齐

## 管理员初始化说明

这套脚本负责补齐后台权限、角色、角色授权关系，但不会替你“猜一个默认管理员密码”。

上线前请确认：

- `admin_admin` 中存在可登录管理员
- 该管理员状态为启用
- `admin_admin_role` 中已经绑定到超级管理员角色

如果你使用的是纯净基础库而没有预置管理员账号，请在首次上线前通过你的初始化脚本或运维 SQL 先创建管理员，再进行登录验收。

## 安装完成后建议检查

- `admin_permission` 有数据
- `admin_role` 有数据
- `admin_role_permission` 有数据
- `plugins/payments/*/migrations/*.sql` 均已按需执行
- `verify-deployment` 能通过数据库检查

## 推荐顺序

1. 修改 `backend/.env`
2. 运行数据库安装脚本
3. 启动 Webman
4. 部署前端壳
5. 运行 `verify-deployment`
6. 完成一次真实下单与回调验收
