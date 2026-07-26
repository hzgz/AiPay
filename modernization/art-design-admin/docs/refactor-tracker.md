<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

# 前端代码优化追踪

更新时间：2026-07-10

状态说明：

- `[ ]` 未开始
- `[-]` 进行中
- `[x]` 已完成

当前进度：`19 / 19`

## 清单

- `[x]` R-001 抽离支付账号/商户通道公共格式化与删除提示逻辑
  - 完成说明：已新增 `src/views/shared/paymentAccountPageShared.ts`，统一承接金额格式化、限额文案、状态标签、确认短语转义、弹窗取消判断，以及单条/批量删除提示文案；`支付配置 / 收款账号` 与 `经营中心 / 通道管理` 已接入共享实现。

- `[x]` R-002 拆分 `支付插件` 页面详情区与插件配置表单渲染器
  - 完成说明：已新增 `src/views/payments/shared/paymentPluginDisplay.ts`，统一承接插件状态文案、支付方式标签、概览卡片计算、字段分组、配置占位、快照/历史动作中文化等展示逻辑；已新增 `src/views/payments/plugins/modules/plugin-detail-overview.vue`、`plugin-detail-config.vue`、`plugin-detail-snapshot.vue`、`plugin-detail-cleanup.vue`，将详情抽屉里的 `概览 / 接入字段 / 快照 / 清理` 全部从主页面拆出；`src/views/payments/plugins/index.vue` 已从 `5779` 行收缩到 `4394` 行，本轮 `pnpm build` 与 `#/payments/plugins` 真实页面四个页签切换回归已通过。

- `[x]` R-003 拆分 `商户通道` 页面新增通道弹窗与测试弹窗
  - 完成说明：已新增 `src/views/merchant/channels/modules/channel-create-dialog.vue` 与 `channel-test-pay-dialog.vue`，将 `新增通道` 与 `通道测试` 两个重弹窗从主页面拆出；`src/views/merchant/channels/index.vue` 已从 `3700` 行收缩到 `2839` 行，本轮 `pnpm build` 已通过，并对 `#/merchant/channels` 实际完成了 `新增通道` 弹窗打开、支付方式/插件联动、`测试` 弹窗打开回归。

- `[x]` R-004 拆分 `系统配置` 页面分组渲染与可编辑字段归一化逻辑
  - 完成说明：已新增 `src/views/system/config/systemConfigDisplay.ts`，统一承接配置分组映射、字段排序、分段规则、高级字段识别、字段编辑器判断与示例值中文化；`配置总览` 主页面已从 `1642` 行收缩到 `990` 行，构建与真实页面回归已通过。

- `[x]` R-005 统一后台表格操作按钮与状态标签的共享样式
  - 完成说明：已新增 `src/assets/styles/core/table-shared.scss` 并在 `src/assets/styles/index.scss` 接入，统一承接 `.table-actions`、`.table-action-link`、`.status-cell`、`.operation-column-cell` 的共享样式；已清理 `支付配置 / 收款账号`、`支付方式`、`支付通道`、`通道池`、`菜单配置`、`数据清理`、`媒体库`、`内容中心` 等页面的重复局部定义，本轮 `pnpm build` 已通过，并对 `#/merchant/channels`、`#/payments/accounts`、`#/payments/methods`、`#/system/cleanup` 进行了真实浏览器回归。

- `[x]` R-006 拆分 `收款账号` 页面新增/凭证状态模块与动态字段校验逻辑
  - 完成说明：已新增 `src/views/payments/accounts/modules/payment-account-form-state.ts`，统一承接 `新增收款账号 / 编辑凭证` 的插件目录加载、支付方式联动、二维码图片上传、二维码解析、动态字段显隐归一、payload 组装与校验逻辑；`src/views/payments/accounts/index.vue` 已从 `1742` 行进一步收缩到 `1042` 行，本轮 `pnpm build` 已再次通过。

- `[x]` R-007 拆分 `支付插件` 页面列表工作区与高级治理面板
  - 完成说明：已新增 `src/views/payments/plugins/modules/plugin-list-workspace.vue` 与 `plugin-governance-panels.vue`，将插件列表工作区和三块治理面板从主页面拆出，并把 `formatBytes` 下沉到 `paymentPluginDisplay.ts` 统一导出；`src/views/payments/plugins/index.vue` 已从 `3826` 行降到 `2491` 行，本轮 `eslint`、`vue-tsc` 与 `#/payments/plugins` 实际页面回归已通过。

- `[x]` R-008 拆分 `商户通道` 页面编辑/状态弹窗并清理失效样式
  - 完成说明：已新增 `src/views/merchant/channels/modules/channel-edit-status-dialogs.vue`，将“编辑通道限额 / 更新通道状态”两类弹窗从主页面抽出，并同步清理主页面内长期失效的抽屉/弹窗样式残留；`src/views/merchant/channels/index.vue` 已从 `2140` 行降到 `1749` 行，本轮 `eslint`、`vue-tsc` 与 `#/merchant/channels` 的详情、编辑、状态弹窗实际回归已通过。

- `[x]` R-009 拆分 `系统用户` 页面详情外的商户维护弹窗与表单状态模块
  - 完成说明：已新增 `src/views/system/user/modules/merchant-user-form-state.ts`、`merchant-user-create-dialog.vue`、`merchant-user-maintenance-dialogs.vue`、`merchant-user-email-dialog.vue`，将“新建商户 / 编辑资料 / VIP费率 / 通知设置 / 商户状态 / 运营邮件”从主页面全部抽离；`src/views/system/user/index.vue` 已从 `1862` 行进一步降到 `1130` 行，本轮 `eslint`、`vue-tsc` 与 `#/system/user` 的详情抽屉、编辑资料、VIP费率、通知设置、运营邮件、冻结状态实际页面回归已通过。

- `[x]` R-010 拆分 `会员套餐` 页面编辑表单与状态/排序弹窗状态模块
  - 完成说明：已新增 `src/views/system/vips/modules/vip-form-state.ts`、`vip-edit-dialog.vue`、`vip-status-sort-dialogs.vue`，将“新增/编辑会员套餐”及“状态/排序”弹窗从主页面抽离，并把表单同步、校验、payload 组装统一下沉到状态模块；`src/views/system/vips/index.vue` 已从 `1541` 行降到 `1179` 行，本轮 `eslint`、`vue-tsc` 与 `#/system/vips` 的详情抽屉、编辑套餐、状态弹窗、排序弹窗、新增套餐实际页面回归已通过。

- `[x]` R-011 拆分 `支付目录` 页面详情抽屉与通道写入弹窗状态模块
  - 完成说明：已新增 `src/views/payments/catalog/modules/channel-catalog-form-state.ts`、`channel-catalog-detail-drawer.vue`、`channel-catalog-write-dialog.vue`、`channel-catalog-status-dialog.vue`，将“详情抽屉 / 新增通道 / 编辑通道 / 通道状态”从主页面抽离，并把写入表单同步、状态同步、payload 校验统一下沉到状态模块；`src/views/payments/catalog/index.vue` 已从 `1522` 行降到 `1184` 行，本轮 `eslint` 与 `vue-tsc` 已通过。当前系统守卫会将遗留路径 `/payments/catalog` 强制收口到 `/payments/plugins`，因此独立路由的真实页面回归目前受限，后续如要做可视化回归，需要先恢复一个可访问入口或在承载页里挂出该工作区。

- `[x]` R-012 拆分 `管理员账号` 页面详情抽屉与写入/授权弹窗状态模块
  - 完成说明：已新增 `src/views/system/admins/modules/admin-form-state.ts`、`admin-detail-drawer.vue`、`admin-write-dialogs.vue`、`admin-access-dialogs.vue`，将“详情抽屉 / 新增管理员 / 编辑管理员 / 分配角色 / 直属权限”从主页面抽离，并把表单同步、校验规则、payload 组装统一下沉到状态模块；`src/views/system/admins/index.vue` 已从 `1505` 行降到 `1007` 行，本轮 `prettier`、`eslint`、`vue-tsc` 已通过，并对 `#/system/admins` 实际完成了详情抽屉、编辑弹窗、角色弹窗、直属权限弹窗回归。

- `[x]` R-013 拆分 `支付通道池` 页面详情抽屉、基础配置弹窗与通道分配状态模块
  - 完成说明：已新增 `src/views/payments/pools/modules/payment-pool-form-state.ts`、`payment-pool-detail-drawer.vue`、`payment-pool-maintenance-dialogs.vue`、`payment-pool-channel-editor-dialog.vue`，将“详情抽屉 / 新建轮询池 / 编辑基础配置 / 状态维护 / 通道分配”从主页面抽离，并把创建、编辑、状态、通道权重与排序、保存 payload 组装统一下沉到状态模块；`src/views/payments/pools/index.vue` 已从 `1340` 行降到 `762` 行，本轮 `prettier`、`eslint`、`vue-tsc` 已通过，并对 `#/payments/pools` 实际完成了详情抽屉、编辑基础配置、状态维护、通道分配弹窗回归。

- `[x]` R-014 拆分 `商户轮询池` 页面详情抽屉、维护弹窗与通道分配状态模块
  - 完成说明：已新增 `src/views/merchant/pools/modules/merchant-pool-form-state.ts`、`merchant-pool-detail-drawer.vue`、`merchant-pool-maintenance-dialogs.vue`、`merchant-pool-channel-editor-dialog.vue`，将“详情抽屉 / 新建轮询池 / 编辑轮询池 / 通道分配”从主页面抽离，并把创建、编辑、状态切换、通道权重与排序、保存 payload 组装统一下沉到状态模块；`src/views/merchant/pools/index.vue` 已从 `1274` 行降到 `884` 行，本轮 `prettier`、`eslint`、`vue-tsc` 已通过，并对 `#/merchant/pools` 实际完成了新建、详情、编辑、通道分配、状态切换、删除清理整条流程回归。

- `[x]` R-015 拆分 `支付插件` 页面脚手架创建弹窗与脚手架提交状态模块
  - 完成说明：已新增 `src/views/payments/plugins/modules/plugin-scaffold-dialog.vue`，将“新建插件”三步脚手架弹窗从主页面抽离，并在 `src/views/shared/paymentPluginScaffold.ts` 新增提交 payload 组装，统一承接编码归一、分步校验、能力标签维护、生成预览与提交数据归一；`src/views/payments/plugins/index.vue` 已从 `2491` 行进一步降到 `1846` 行，本轮 `prettier`、`eslint`、`vue-tsc` 已通过，并对 `#/payments/plugins` 实际完成了脚手架弹窗打开、三步流转、插件创建、详情打开、彻底清理回归；同时修复了清理成功提示里托管通道数量显示为 `undefined` 的文案缺陷。

- `[x]` R-016 拆分 `商户通道` 页面表单状态模块与剩余校验/回填逻辑
  - 完成说明：已新增 `src/views/merchant/channels/modules/channel-form-state.ts`，统一承接“新增通道 / 编辑限额 / 编辑凭证 / 更新状态 / 测试金额”的表单初始状态、支付方式/插件联动、payload 组装、额度校验、凭证校验、详情回填与测试金额兜底；`src/views/merchant/channels/index.vue` 已从 `1749` 行进一步降到 `1394` 行，本轮 `prettier`、`eslint`、`vue-tsc` 已通过，并使用本机 Edge 对 `#/merchant/channels` 实际完成了“新增通道”弹窗打开与支付方式/插件联动、详情抽屉打开、`编辑凭证` 弹窗打开、`修改状态` 弹窗打开、`测试` 弹窗打开回归。

- `[x]` R-017 拆分 `收款账号` 页面限额/状态维护状态模块并完成真实后台回归
  - 完成说明：已新增 `src/views/payments/accounts/modules/payment-account-maintenance-state.ts`，统一承接“编辑限额 / 修改状态”的表单初始状态、详情回填、可编辑模型构建、payload 校验与提交参数组装；`src/views/payments/accounts/index.vue` 已从 `946` 行降到 `898` 行，本轮 `prettier`、`eslint`、`vue-tsc` 已通过，并使用本机 Edge 对 `#/payments/accounts` 实际完成了详情抽屉、`编辑限额`、`编辑凭证`、`修改状态` 的后台登录态真实页面回归；回归过程中额外补建并清理了一条临时 `支付宝软件版` 账号，用于覆盖凭证编辑入口。

- `[x]` R-018 拆分 `支付插件` 页面生命周期文案/配置同步辅助模块并完成真实后台回归
  - 完成说明：已新增 `src/views/payments/plugins/modules/payment-plugin-lifecycle-display.ts`，统一承接“导出记录包文件名 / 快照恢复与删除确认短语 / 残留清理确认短语 / 生命周期确认文案 / 配置表单同步 / 配置提交 payload / 清理成功提示”等辅助逻辑；`src/views/payments/plugins/index.vue` 已从 `1846` 行进一步降到 `1546` 行，本轮 `prettier`、`eslint`、`vue-tsc` 已通过，并使用本机 Codex In-app Browser 对 `#/payments/plugins` 实际完成了后台登录、插件详情抽屉打开、`接入字段` 页签、`清理` 页签、`卸载插件` 确认弹窗回归；过程中还真实点通并恢复了 `微信软件版插件` 的停用/启用动作，确认生命周期链路可用且未留下测试残留。

- `[x]` R-019 拆分 `系统配置` 页面分组表单状态与字段显隐规则模块并完成真实后台回归
  - 完成说明：已新增 `src/views/system/config/systemConfigFormState.ts`，统一承接“配置汇总映射 / 搜索参数构建 / 分组表单模型同步 / 当前分组选中 / 高级配置显隐状态 / 字段值读写 / 动态字段显隐规则 / 分组可见字段计数”等状态逻辑；`src/views/system/config/index.vue` 已从 `1020` 行进一步降到 `822` 行，本轮 `prettier`、`eslint`、`vue-tsc` 已通过，并使用本机 Codex In-app Browser 对 `#/system/config` 实际完成了页面加载、侧边分组切换、`安全验证` 分组高级配置折叠/展开回归，确认新的状态模块未影响字段显示与高级开关联动。

## 当前结论

- 最危险的大文件仍然是：
  - `src/views/payments/accounts/index.vue`
  - `src/views/payments/plugins/index.vue`
  - `src/views/merchant/channels/index.vue`

- 下一步优先顺序：
  1. `支付账号页继续压缩创建弹窗动态字段与上传编排逻辑`
  2. `支付插件页继续拆详情抽屉外壳与生命周期动作协调逻辑`
  3. `商户通道页继续压缩详情抽屉与通道维护协调逻辑`
