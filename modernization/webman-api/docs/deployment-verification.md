# AiPay 部署验收说明

## 目标

这份文档用于正式上线前后做快速自检，确保下面几件事同时成立：

- 后端环境正常
- 数据库结构完整
- 插件目录完整
- 前端壳、商户端、管理员端都可访问
- Webman API 和支付入口可正常响应

## 标准访问口径

推荐部署完成后至少能访问：

- `https://portal.example.com/`
- `https://portal.example.com/#/merchant/login`
- `https://portal.example.com/#/auth/login`
- `http://127.0.0.1:8787/api/health` 或反代后的健康检查地址

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

这一步会检查：

- `.env` 是否已修改为真实值
- `runtime/`、`upload-assets/`、`plugins/payments/` 是否齐全
- 支付插件目录是否含有 `plugin.json` 和 `src/Plugin.php`
- 数据库连接是否可用
- 核心表和关键增量字段是否存在
- `ypay_payment` 是否已有基础支付方式数据

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
  -AdminPassword 你的管理员密码
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
  --admin-password='你的管理员密码'
```

说明：

- `--backend-url` 传基础地址即可，脚本会自动检查 `/api/health`
- 前端壳统一部署时，`--console-url`、`--merchant-url`、`--public-url` 可以传同一个域名
- 传入管理员账号后，脚本会继续检查：
  - 管理员登录接口
  - 支付插件接口
  - 支付方式接口
  - 进程管理接口
- 如果不传管理员密码，脚本会跳过后台接口链路并给出 `WARN`

## 输出结果含义

- `PASS`：该项已通过
- `WARN`：该项有风险，但不一定阻断上线
- `FAIL`：该项未通过，需修复后再继续

新增后台接口验收，主要是为了提前拦住这些线上高频问题：

- 管理员页能打开，但登录后关键接口报错
- `ypay_payment` 为空，导致支付插件页、支付方式页直接异常
- Linux 下 Webman 实际运行中，但进程管理误报“未运行”

退出码：

- `0`：无失败项
- `1`：存在失败项

## 必做人工验收

即便脚本通过，也建议人工过一遍：

1. 游客首页能打开，主题、公告、开发文档、支付测试页面正常显示。
2. 商户登录页能打开，注册入口可跳转。
3. 管理员登录页能通过 `/#/auth/login` 直达。
4. 管理员后台能看到支付插件、支付方式、系统配置等关键菜单。
5. 商户端能正常创建通道、测试支付、查看订单。
6. 至少完成一笔真实支付或完整模拟支付，确认：
   - 下单成功
   - 回调成功
   - 订单状态更新成功
   - 商户回调成功
   - 资金落账正确
7. 如果启用了需要后台进程的插件，确认对应进程在线。

## 推荐验收顺序

1. 先跑 `-SkipHttp`
2. 再跑完整 HTTP 验收
3. 再做人工登录和页面巡检
4. 最后做真实订单闭环验收

## 常见失败点

- `.env` 还是示例值
- 数据库密码未改
- Nginx 没把 `/api`、`/submit.php`、`/mapi.php`、`/Pay/*` 代理到 Webman
- 管理员账号未初始化
- 支付插件目录缺 `plugin.json`
- `upload-assets/` 无写权限
