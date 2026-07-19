<?php

declare(strict_types=1);

namespace app\support;

use InvalidArgumentException;

final class BusinessTable
{
    private const DEFAULT_PREFIX = 'aipay_';

    /**
     * @var array<string, string>
     */
    private const MAP = [
        'account' => 'account',
        'cdk' => 'cdk',
        'domain' => 'domain',
        'nav' => 'navs',
        'news' => 'news',
        'order' => 'order',
        'order_callback_task' => 'order_callback_task',
        'order_reconcile_task' => 'order_reconcile_task',
        'payment' => 'payment',
        'payment_transaction_claim' => 'payment_transaction_claim',
        'paylist' => 'paylist',
        'poll_pool' => 'poll_pool',
        'poll_pool_item' => 'poll_pool_item',
        'plug' => 'plug',
        'quicklogin' => 'quicklogin',
        'recharge' => 'recharge',
        'risk' => 'risk',
        'ticket' => 'ticket',
        'ticket_category' => 'ticket_category',
        'user' => 'user',
        'userbasic' => 'userbasic',
        'vip' => 'vip',
    ];

    private static ?string $prefixCache = null;

    public static function prefix(): string
    {
        if (self::$prefixCache !== null) {
            return self::$prefixCache;
        }

        $prefix = Environment::string('AIPAY_BIZ_TABLE_PREFIX', self::DEFAULT_PREFIX);
        $prefix = trim($prefix);
        if ($prefix === '') {
            $prefix = self::DEFAULT_PREFIX;
        }
        if (!str_ends_with($prefix, '_')) {
            $prefix .= '_';
        }

        return self::$prefixCache = strtolower($prefix);
    }

    public static function name(string $logicalName, string $alias = ''): string
    {
        $table = self::physicalName($logicalName);

        return $alias !== '' ? $table . ' as ' . $alias : $table;
    }

    public static function column(string $logicalName, string $column, string $alias = ''): string
    {
        $qualifier = $alias !== '' ? $alias : self::physicalName($logicalName);

        return $qualifier . '.' . $column;
    }

    public static function account(string $alias = ''): string
    {
        return self::name('account', $alias);
    }

    public static function order(string $alias = ''): string
    {
        return self::name('order', $alias);
    }

    public static function cdk(string $alias = ''): string
    {
        return self::name('cdk', $alias);
    }

    public static function domain(string $alias = ''): string
    {
        return self::name('domain', $alias);
    }

    public static function nav(string $alias = ''): string
    {
        return self::name('nav', $alias);
    }

    public static function news(string $alias = ''): string
    {
        return self::name('news', $alias);
    }

    public static function orderCallbackTask(string $alias = ''): string
    {
        return self::name('order_callback_task', $alias);
    }

    public static function orderReconcileTask(string $alias = ''): string
    {
        return self::name('order_reconcile_task', $alias);
    }

    public static function payment(string $alias = ''): string
    {
        return self::name('payment', $alias);
    }

    public static function paymentTransactionClaim(string $alias = ''): string
    {
        return self::name('payment_transaction_claim', $alias);
    }

    public static function paylist(string $alias = ''): string
    {
        return self::name('paylist', $alias);
    }

    public static function plug(string $alias = ''): string
    {
        return self::name('plug', $alias);
    }

    public static function pollPool(string $alias = ''): string
    {
        return self::name('poll_pool', $alias);
    }

    public static function pollPoolItem(string $alias = ''): string
    {
        return self::name('poll_pool_item', $alias);
    }

    public static function quickLogin(string $alias = ''): string
    {
        return self::name('quicklogin', $alias);
    }

    public static function recharge(string $alias = ''): string
    {
        return self::name('recharge', $alias);
    }

    public static function risk(string $alias = ''): string
    {
        return self::name('risk', $alias);
    }

    public static function ticket(string $alias = ''): string
    {
        return self::name('ticket', $alias);
    }

    public static function ticketCategory(string $alias = ''): string
    {
        return self::name('ticket_category', $alias);
    }

    public static function user(string $alias = ''): string
    {
        return self::name('user', $alias);
    }

    public static function userBasic(string $alias = ''): string
    {
        return self::name('userbasic', $alias);
    }

    public static function vip(string $alias = ''): string
    {
        return self::name('vip', $alias);
    }

    private static function physicalName(string $logicalName): string
    {
        $logicalName = strtolower(trim($logicalName));
        if ($logicalName === '' || !isset(self::MAP[$logicalName])) {
            throw new InvalidArgumentException('Unknown business table: ' . $logicalName);
        }

        return self::prefix() . self::MAP[$logicalName];
    }
}
