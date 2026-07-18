# Legacy Epay Compatibility

This plugin started as a compatibility sample for the new Webman payment plugin layout.

Current migration role:

- `legacy_epay` is still the active bridge engine behind the legacy payment entry paths such as `submit.php`, `mapi.php`, `/Pay/submit`, `/Pay/apisubmit`, `/Notify/epay_notifyzj`, and `/Notify/epay_returnzj`.
- It currently owns Webman-side order creation, callback verification, settlement, and merchant callback replay for that compatibility chain.
- Do not remove or disable it in production unless the legacy payment entry paths have been fully replaced by dedicated Webman-native plugin routing.
- The legacy notify endpoints now use a fixed compatibility binding to `legacy_epay`: they require the plugin to stay installed and notify-capable, but they intentionally continue accepting in-flight callbacks even while the plugin is temporarily disabled for drain-mode cutovers.
- The long-term target is still to replace this bridge with explicit plugin/channel routing, then retire the compatibility entry chain.

Current phase 1 behavior:

- Plugin discovery is driven by `plugin.json`.
- Install and upgrade use `plugin.json -> migrations.releases[]` so SQL is tracked per release version instead of blindly rerunning every file.
- Install creates `runtime/payment-plugins/legacy_epay/lifecycle.json`.
- The lifecycle flow also records `runtime/payment-plugins/legacy_epay/migration_journal.json` so applied SQL files can be audited and only new migrations run on upgrade.
- Install seeds the plugin-owned `pay_plugin_legacy_epay_config` table with a compatibility config skeleton for `merchant_id`, `merchant_key`, `gateway_url`, and `notify_url`.
- The shared payment-plugin config API can now read and save those rows from the admin detail drawer.
- Secret config such as `merchant_key` is returned to the admin as masked metadata only; leaving the password input blank preserves the stored value.
- Those compatibility fields are optional defaults only. `legacy_epay` can now be enabled without filling them because the live legacy bridge resolves real upstream credentials from each merchant payment-gateway row.
- Release `0.1.2` adds `pay_plugin_legacy_epay_log` through a dedicated upgrade migration so future plugin-owned audit traces stay isolated for purge review.
- Release `0.1.3` adds `upgrade_note` to `pay_plugin_legacy_epay_log` so release-specific diagnostics can be preserved without touching shared merchant data.
- `plugin.json -> upgrade` now documents impact level, validation downtime, release changelog, operator checklist, and rollback policy for the admin upgrade preview.
- Upgrade compares the installed registry version with `plugin.json`, baselines pre-journal installs when safe, runs only pending SQL releases, and then executes the plugin upgrade hook.
- Install, enable, disable, and uninstall update `runtime/payment_plugins.json`.
- Recovery snapshots are stored separately under `runtime/payment-plugin-snapshots/legacy_epay` so destructive purge cleanup does not remove the archived restore points.
- The admin list page also exposes those archives through a global Recovery Vault so operators can restore `legacy_epay` even after the plugin directory has been purged and the original detail drawer is gone.
- The same list page now audits orphan registry residue for catalog-missing plugin codes, so leftover runtime/history/package/table scope can be cleaned even when the original plugin drawer no longer exists.
- Orphan residue cleanup also writes a global receipt to `runtime/payment-plugin-audit/registry-residue-ledger.json`, so destructive residue governance still has an audit trail after the plugin-scoped history directory has been removed.
- Obsolete recovery snapshots can be deleted from either the plugin drawer or the global Recovery Vault with an explicit confirmation phrase; when the last snapshot is removed, the empty `runtime/payment-plugin-snapshots/{code}` directory is cleaned up automatically.
- Purge cleanup now inspects the available recovery snapshots first. When no restore point exists, the admin flow escalates the destructive confirmation phrase to `PURGE WITHOUT SNAPSHOT legacy_epay` so operators cannot treat a no-recovery purge like a normal cleanup.
- Orphan residue cleanup uses its own confirmation phrases: `CLEAN RESIDUE {code}` when a Recovery Vault snapshot exists, and `CLEAN RESIDUE WITHOUT SNAPSHOT {code}` when the orphan code has no restore point.
- Uninstall only records lifecycle metadata and cleanup plans; it does not delete tables or files automatically.
- Safe cleanup can remove the plugin-owned runtime directory and namespaced config table after uninstall audit.
- The sample plugin now implements an optional cleanup hook contract so safe/purge cleanup can run plugin-defined handoff logic before audited targets are removed.
- Recovery snapshot restore can rebuild the plugin package directory, lifecycle history, runtime workspace, and namespaced plugin tables even after `cleanup-purge` has removed the local plugin directory.
- Real payment request migration will be implemented after the plugin lifecycle and admin management flow are stable.
