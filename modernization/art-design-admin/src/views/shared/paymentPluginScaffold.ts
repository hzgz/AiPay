import { computed, type Ref } from 'vue'

export interface PaymentPluginScaffoldForm {
  code: string
  name: string
  provider: string
  description: string
  version: string
  capabilities: string[]
}

export interface PaymentPluginScaffoldSubmitPayload {
  code: string
  name: string
  provider: string
  description: string
  version: string
  capabilities: string[]
}

export const scaffoldCapabilityOptions = [
  {
    value: 'create_order',
    label: '创建订单',
    description: '提供支付下单能力，并纳入插件生命周期管理。'
  },
  {
    value: 'query',
    label: '订单查询',
    description: '支持订单状态轮询与支付后的对账检查。'
  },
  {
    value: 'refund',
    label: '退款',
    description: '声明插件支持退款能力，便于后续接入和审核。'
  },
  {
    value: 'notify',
    label: '异步通知',
    description: '通过插件命名空间入口处理回调通知与结算更新。'
  }
] as const

const scaffoldBuiltinCapabilityNotes = {
  create_order: {
    label: '创建订单',
    summary: '生成下单能力骨架，并在默认配置中保留网关地址等核心字段。'
  },
  query: {
    label: '订单查询',
    summary: '将查询能力纳入清单声明，便于后续轮询、对账与状态核对。'
  },
  refund: {
    label: '退款',
    summary: '把退款能力纳入插件支持范围，方便后续补齐接入与冒烟验证。'
  },
  notify: {
    label: '异步通知',
    summary: '生成回调相关配置提示，并将异步结算行为约束在插件边界内。'
  }
} as const

export function createScaffoldForm(): PaymentPluginScaffoldForm {
  return {
    code: '',
    name: '',
    provider: 'AiPay官方',
    description: '为支付通道生成独立插件目录与基础配置。',
    version: '0.1.0',
    capabilities: scaffoldCapabilityOptions.map((item) => item.value)
  }
}

export function buildPaymentPluginScaffoldSubmitPayload(
  form: PaymentPluginScaffoldForm,
  normalizedCode: string
): PaymentPluginScaffoldSubmitPayload {
  return {
    code: normalizedCode,
    name: form.name.trim(),
    provider: form.provider.trim(),
    description: form.description.trim(),
    version: form.version.trim(),
    capabilities: [...form.capabilities]
  }
}

export function usePaymentPluginScaffoldPreview(
  scaffoldForm: PaymentPluginScaffoldForm,
  scaffoldStep: Ref<number>
) {
  const scaffoldCodeCandidate = computed(() => scaffoldForm.code.trim().toLowerCase())
  const scaffoldPreviewCode = computed(() => scaffoldCodeCandidate.value || 'your_plugin')
  const scaffoldPreviewNamespaceSuffix = computed(() => {
    const parts = scaffoldPreviewCode.value
      .split(/_+/)
      .map((segment) => segment.trim())
      .filter(Boolean)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))

    return parts.join('') || 'GeneratedPlugin'
  })
  const scaffoldPreviewNamespace = computed(
    () => `Plugins\\Payments\\${scaffoldPreviewNamespaceSuffix.value}`
  )
  const scaffoldPreviewClass = computed(() => `${scaffoldPreviewNamespace.value}\\Plugin`)
  const scaffoldPreviewDirectory = computed(() => `plugins/payments/${scaffoldPreviewCode.value}`)
  const scaffoldPreviewRuntimeDirectory = computed(
    () => `runtime/payment-plugins/${scaffoldPreviewCode.value}`
  )
  const scaffoldPreviewConfigTable = computed(
    () => `pay_plugin_${scaffoldPreviewCode.value}_config`
  )
  const scaffoldPreviewLogTable = computed(() => `pay_plugin_${scaffoldPreviewCode.value}_log`)
  const scaffoldPreviewConfigFields = computed(() => {
    const selected = new Set(scaffoldForm.capabilities)
    const fields = [
      { field: 'merchant_id', required: true },
      { field: 'merchant_key', required: true }
    ]

    if (selected.has('create_order') || selected.has('query') || selected.has('refund')) {
      fields.push({ field: 'gateway_url', required: true })
    }

    if (selected.has('notify')) {
      fields.push({ field: 'notify_secret', required: false })
    }

    return fields
  })
  const scaffoldPreviewRuntimeDefaults = computed(() => [
    {
      method: 'createOrder()',
      capability: 'create_order',
      status: scaffoldForm.capabilities.includes('create_order') ? 'not_implemented' : 'unsupported'
    },
    {
      method: 'query()',
      capability: 'query',
      status: scaffoldForm.capabilities.includes('query') ? 'not_implemented' : 'unsupported'
    },
    {
      method: 'refund()',
      capability: 'refund',
      status: scaffoldForm.capabilities.includes('refund') ? 'not_implemented' : 'unsupported'
    },
    {
      method: 'handleNotify()',
      capability: 'notify',
      status: scaffoldForm.capabilities.includes('notify') ? 'not_implemented' : 'unsupported'
    }
  ])
  const scaffoldSelectedCapabilityNotes = computed(() =>
    scaffoldForm.capabilities.map((capability) => {
      const builtin =
        scaffoldBuiltinCapabilityNotes[capability as keyof typeof scaffoldBuiltinCapabilityNotes]

      if (builtin) {
        return {
          capability,
          label: builtin.label,
          summary: builtin.summary,
          custom: false
        }
      }

      return {
        capability,
        label: capability.replaceAll('_', ' '),
        summary: '自定义能力默认只会写入插件清单，创建后请自行补充运行时实现和运维校验说明。',
        custom: true
      }
    })
  )
  const scaffoldPreviewFiles = computed(() => [
    `${scaffoldPreviewDirectory.value}/plugin.json`,
    `${scaffoldPreviewDirectory.value}/README.md`,
    `${scaffoldPreviewDirectory.value}/src/Plugin.php`,
    `${scaffoldPreviewDirectory.value}/migrations/001_create_config_table.sql`,
    `${scaffoldPreviewDirectory.value}/migrations/002_create_plugin_log_table.sql`
  ])
  const scaffoldStepHint = computed(() => {
    if (scaffoldStep.value === 0) {
      return '第 1 步 / 共 3 步：定义插件身份信息和命名空间目录。'
    }
    if (scaffoldStep.value === 1) {
      return '第 2 步 / 共 3 步：选择需要纳入生命周期治理的插件能力。'
    }

    return '第 3 步 / 共 3 步：确认生成文件、数据表和清理策略后再创建。'
  })

  return {
    scaffoldCodeCandidate,
    scaffoldPreviewClass,
    scaffoldPreviewCode,
    scaffoldPreviewConfigFields,
    scaffoldPreviewConfigTable,
    scaffoldPreviewDirectory,
    scaffoldPreviewFiles,
    scaffoldPreviewLogTable,
    scaffoldPreviewNamespace,
    scaffoldPreviewNamespaceSuffix,
    scaffoldPreviewRuntimeDefaults,
    scaffoldPreviewRuntimeDirectory,
    scaffoldSelectedCapabilityNotes,
    scaffoldStepHint
  }
}
