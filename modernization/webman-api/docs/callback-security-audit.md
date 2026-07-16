# 回调与兼容接口安全审计

更新时间：2026-07-08

## 1. `legacy_epay` 兼容通知入口

适用入口：

- `/Notify/epay_notifyzj`
- `/Notify/epay_returnzj`

当前策略：

- 固定绑定到 `legacy_epay`，不再允许隐式切换到其它插件。
- 插件必须存在、已安装，并声明 `notify` 能力。
- 即使 `legacy_epay` 处于 `disabled`，兼容通知入口仍保持可用，用于迁移切流期间清理历史在途回调。
- 验签方式维持旧网关兼容模式：`MD5(sign)`，密钥来自上游通道 `paylist.key`。
- 不强制时间窗与 `nonce`，避免破坏旧网关兼容性。
- 防重放依赖订单结算幂等：同一订单重复回调不会重复扣费或重复入账。
- 失败回包保持旧兼容行为：
  - `notify` 返回 `fail`
  - `return` 返回安全文本或跳转

## 2. 软件回调与软件上报入口

适用入口：

- `/api/Software/verify`
- `/api/Software/checkOrder`
- `/api/Software/heartbeat`
- `/api/Software/PCNotify`
- `/api/Software/pcnotify`
- `/api/report`
- `/api/report/{id}`

当前策略：

- 所有入口都先走商户凭据校验：`pid + key` 或 `id + token`。
- 支持两种签名模式，由 `software_callback_sign_mode` 控制：
  - `strict`
  - `compat`
- 在 `strict` 模式下，强制要求：
  - `signature`
  - `timestamp`
  - `nonce`
- 强签名算法：
  - `HMAC-SHA256`
- 时间窗：
  - `software_callback_sign_window`
  - 默认 `300` 秒
- 防重放：
  - `nonce` 记录在 `runtime/software-signatures/`
  - 同商户、同作用域下重复 `nonce` 会被拒绝
- 兼容模式下，如果旧软件没有提交 `signature/timestamp/nonce`：
  - 仍允许继续按旧凭据模式访问
  - 写操作的最终防重放退化为“订单结算幂等”

## 3. 旧公开兼容 API

适用入口：

- `/Api/getSoftwareConfig`
- `/api/getSoftwareConfig`
- `/Api/findorder`
- `/api/findorder`

当前策略：

- 这一组入口按“公开只读兼容接口”处理。
- 不要求签名。
- 不做 `nonce` / 时间窗校验。
- 不涉及资金写入，因此重放保护标记为 `not_applicable`。
- 风险边界通过只读约束表达：
  - 允许读取兼容配置或订单状态
  - 不允许订单更新、回调重放、状态重置

## 4. 验证说明

- 纯净工作区不再内置 smoke 回归脚本。
- 当前文档保留的是安全策略、验签约束和防重放边界说明。
- 正式上线前，应结合真实支付链路再做一次人工联调验证。
