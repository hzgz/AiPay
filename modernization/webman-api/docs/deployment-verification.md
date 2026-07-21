# AiPay 部署验收说明

## 目标

这份文档用于正式上线前后做快速自检，确保下面几件事同时成立：

- 后端环境正常
- 数据库结构完整
- 插件目录完整
- 前端壳、商户端、管理员端都可访问
- Webman API 与支付入口可正常响应

## 标准访问路径

推荐部署完成后至少能访问：

- `https://portal.example.com/`
- `https://portal.example.com/#/merchant/login`
- `https://portal.example.com/#/auth/login`
- `http://127.0.0.1:8787/api/health`

管理员入口建议只作为直达地址使用，不在游客首页公开暴露。

## 自检脚本位置

- `backend/deploy/windows/verify-deployment.ps1`
- `backend/deploy/linux/verify-deployment.sh`
- `backend/deploy/shared/verify-deployment.php`

## 只检查文件与数据库

### Windows

```powershell
cd backend
powershell -ExecutionPolicy Bypass -File deploy/windows/verify-deployment.ps1 -SkipHttp
```

### Linux

```bash
cd backend
bash deploy/linux/verify-deployment.sh --skip-http
```

这一阶段会检查：

- `.env` 是否已改为真实值
- `runtime/`、`upload-assets/`、`plugins/payments/` 是否齐全
- 支付插件目录是否包含 `plugin.json` 与 `src/Plugin.php`
- 数据库连接是否可用
- 核心表和关键字段是否存在
- 支付方式数据表是否已经初始化

## 完整 HTTP 验收

### Windows

```powershell
cd backend
powershell -ExecutionPolicy Bypass -File deploy/windows/verify-deployment.ps1 `
  -BackendUrl http://127.0.0.1:8787 `
  -ConsoleUrl https://portal.example.com `
  -MerchantUrl https://portal.example.com `
  -PublicUrl https://portal.example.com `
  -AdminUser adminroot `
  -AdminPassword 'your-password'
```

### Linux

```bash
cd backend
bash deploy/linux/verify-deployment.sh \
  --backend-url=http://127.0.0.1:8787 \
  --console-url=https://portal.example.com \
  --merchant-url=https://portal.example.com \
  --public-url=https://portal.example.com \
  --admin-user=adminroot \
  --admin-password='your-password'
```

说明：

- `--backend-url` 传基础地址即可，脚本会自动检查 `/api/health`
- 前端壳统一部署时，`--console-url`、`--merchant-url`、`--public-url` 可以传同一个域名
- 传入管理员账号后，脚本会继续检查管理员登录、支付插件、支付方式、进程管理等关键接口

## 输出结果含义

- `PASS`：该项通过
- `WARN`：该项有风险，但不一定阻断上线
- `FAIL`：该项未通过，必须修复后再继续

## 必做人工验收

1. 游客首页能正常打开，主题、公告、开发文档、支付测试显示正常。
2. 商户登录页与注册页能正常打开并跳转。
3. 管理员登录页可通过 `/#/auth/login` 直接访问。
4. 管理后台能看到支付插件、支付方式、系统配置等关键菜单。
5. 商户端能正常创建通道、测试支付、查看订单。
6. 至少完成一笔真实支付或完整模拟支付，确认下单、回调、落账全部成功。
7. 如启用了依赖后端进程的插件，确认进程在线。

## 推荐验收顺序

1. 先跑 `--skip-http`
2. 再跑完整 HTTP 验收
3. 再做人工登录与页面巡检
4. 最后做真实订单闭环验收
