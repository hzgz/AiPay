<?php

declare(strict_types=1);

namespace Plugins\Payments\Shared;

use app\payment\AbstractManagedPaymentPlugin;

abstract class AbstractJiaofeiyiManagedPlugin extends AbstractManagedPaymentPlugin
{
    abstract protected function displayName(): string;

    abstract protected function operatorNote(): string;

    abstract protected function accountHintText(): string;

    protected function pluginName(): string
    {
        return $this->displayName();
    }

    public function configSchema(): array
    {
        return [
            [
                'field' => 'display_name',
                'label' => '插件显示名称',
                'type' => 'text',
                'required' => true,
            ],
            [
                'field' => 'operator_note',
                'label' => '运维备注',
                'type' => 'textarea',
                'required' => false,
            ],
            [
                'field' => 'account_hint',
                'label' => '账户录入提示',
                'type' => 'textarea',
                'required' => false,
            ],
        ];
    }

    protected function defaultConfigValue(string $configKey): ?string
    {
        return match ($configKey) {
            'display_name' => $this->displayName(),
            'operator_note' => $this->operatorNote(),
            'account_hint' => $this->accountHintText(),
            default => parent::defaultConfigValue($configKey),
        };
    }
}
