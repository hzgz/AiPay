ALTER TABLE `ypay_order_reconcile_task`
  ADD KEY `idx_order_id` (`order_id`);

ALTER TABLE `ypay_order`
  ADD KEY `idx_alipay_order_no` (`alipay_order_no`(191));
