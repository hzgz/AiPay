-- Phase 3 upgrade-time plugin migration.
-- This file is executed by the audited payment-plugin lifecycle flow.

ALTER TABLE `pay_plugin_legacy_epay_log`
  ADD COLUMN IF NOT EXISTS `upgrade_note` VARCHAR(120) NULL;
