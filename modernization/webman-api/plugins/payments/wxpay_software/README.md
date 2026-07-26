<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

# 微信软件版插件

该插件把旧 TP 中的 `wxpay_software` 能力收敛为独立支付插件目录。

## 作用

- 为后台生成并托管 `wxpay_software` 插件通道目录
- 独立记录插件安装、升级、卸载与清理审计
- 保持旧账户字段和处理逻辑继续走现有 `PaymentAccountController` 分支

## 注意

- 这是后台账户插件，不参与商户上游网关插件选择
- 插件清理不会删除账户主表、订单、资金、结算等业务数据
