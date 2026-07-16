<template>
  <div class="alipay-official-fields">
    <ElFormItem :label="fieldLabel('请选择可用的接口')">
      <ElCheckboxGroup v-model="selectedModes" class="alipay-official-fields__mode-group">
        <ElCheckbox
          v-for="option in meta.qrTypeOptions"
          :key="option.value"
          :label="option.value"
          class="alipay-official-fields__mode-item"
        >
          {{ option.label }}
        </ElCheckbox>
      </ElCheckboxGroup>
    </ElFormItem>

    <ElFormItem :label="fieldLabel('接口加签方式')">
      <ElRadioGroup v-model="signMode" class="alipay-official-fields__sign-group">
        <ElRadio value="key">RSA2密钥</ElRadio>
        <ElRadio value="cert">RSA2证书</ElRadio>
      </ElRadioGroup>
    </ElFormItem>

    <ElFormItem :label="fieldLabel('应用APPID')">
      <ElInput
        v-model="model.identifier"
        maxlength="50"
        :placeholder="fieldPlaceholder('请输入应用APPID')"
      />
    </ElFormItem>

    <ElFormItem :label="fieldLabel(meta.qrUrlLabel)">
      <ElInput
        v-model="model.qr_url"
        type="textarea"
        :rows="5"
        maxlength="12000"
        :placeholder="fieldPlaceholder('请输入支付宝应用私钥')"
      />
    </ElFormItem>

    <ElFormItem v-if="!isCertMode" :label="fieldLabel(meta.cookieLabel)">
      <ElInput
        v-model="model.cookie"
        type="textarea"
        :rows="5"
        maxlength="12000"
        :placeholder="fieldPlaceholder('请输入支付宝公钥')"
      />
    </ElFormItem>

    <template v-else>
      <ElFormItem :label="fieldLabel(meta.wxGuidLabel)">
        <div class="alipay-official-fields__cert-shell">
          <div class="alipay-official-fields__cert-actions">
            <ElUpload
              accept=".crt,.pem,.txt,application/x-pem-file"
              :auto-upload="false"
              :show-file-list="false"
              :on-change="(file) => handleCertificateChange(file, 'wx_guid')"
            >
              <ElButton type="primary" plain>点击上传</ElButton>
            </ElUpload>
            <span class="alipay-official-fields__cert-tip">
              （appCertPublicKey开头的crt格式证书）
            </span>
            <span v-if="model.wx_guid" class="alipay-official-fields__cert-ready">已读取</span>
            <ElButton link @click="toggleFieldVisible('wx_guid')">
              {{ fieldVisible.wx_guid ? '收起内容' : '手动粘贴/查看内容' }}
            </ElButton>
            <ElButton v-if="model.wx_guid" link type="danger" @click="model.wx_guid = ''">
              清空
            </ElButton>
          </div>
          <ElInput
            v-if="fieldVisible.wx_guid"
            v-model="model.wx_guid"
            type="textarea"
            :rows="4"
            maxlength="12000"
            :placeholder="fieldPlaceholder('请粘贴应用公钥证书内容')"
          />
        </div>
      </ElFormItem>

      <ElFormItem :label="fieldLabel(meta.cloudIdLabel || '支付宝公钥证书')">
        <div class="alipay-official-fields__cert-shell">
          <div class="alipay-official-fields__cert-actions">
            <ElUpload
              accept=".crt,.pem,.txt,application/x-pem-file"
              :auto-upload="false"
              :show-file-list="false"
              :on-change="(file) => handleCertificateChange(file, 'cloud_id')"
            >
              <ElButton type="primary" plain>点击上传</ElButton>
            </ElUpload>
            <span class="alipay-official-fields__cert-tip">
              （alipayCertPublicKey开头的crt格式证书）
            </span>
            <span v-if="model.cloud_id" class="alipay-official-fields__cert-ready">已读取</span>
            <ElButton link @click="toggleFieldVisible('cloud_id')">
              {{ fieldVisible.cloud_id ? '收起内容' : '手动粘贴/查看内容' }}
            </ElButton>
            <ElButton v-if="model.cloud_id" link type="danger" @click="model.cloud_id = ''">
              清空
            </ElButton>
          </div>
          <ElInput
            v-if="fieldVisible.cloud_id"
            v-model="model.cloud_id"
            type="textarea"
            :rows="4"
            maxlength="12000"
            :placeholder="fieldPlaceholder('请粘贴支付宝公钥证书内容')"
          />
        </div>
      </ElFormItem>

      <ElFormItem :label="fieldLabel(meta.extraValueLabel)">
        <div class="alipay-official-fields__cert-shell">
          <div class="alipay-official-fields__cert-actions">
            <ElUpload
              accept=".crt,.pem,.txt,application/x-pem-file"
              :auto-upload="false"
              :show-file-list="false"
              :on-change="(file) => handleCertificateChange(file, 'extra_value')"
            >
              <ElButton type="primary" plain>点击上传</ElButton>
            </ElUpload>
            <span class="alipay-official-fields__cert-tip">
              （alipayRootCert开头的crt格式证书）
            </span>
            <span v-if="model.extra_value" class="alipay-official-fields__cert-ready">已读取</span>
            <ElButton link @click="toggleFieldVisible('extra_value')">
              {{ fieldVisible.extra_value ? '收起内容' : '手动粘贴/查看内容' }}
            </ElButton>
            <ElButton v-if="model.extra_value" link type="danger" @click="model.extra_value = ''">
              清空
            </ElButton>
          </div>
          <ElInput
            v-if="fieldVisible.extra_value"
            v-model="model.extra_value"
            type="textarea"
            :rows="5"
            maxlength="20000"
            :placeholder="fieldPlaceholder('请粘贴支付宝根证书内容')"
          />
        </div>
      </ElFormItem>
    </template>

    <ElFormItem :label="fieldLabel(meta.pidLabel)">
      <ElInput
        v-model="model.pid"
        maxlength="50"
        :placeholder="fieldPlaceholder(meta.pidPlaceholder)"
      />
    </ElFormItem>

    <div class="alipay-official-fields__bottom-tip">
      选择可用的接口，只能选择已经签约的产品，否则会无法支付！
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, reactive } from 'vue'
  import { ElMessage, type UploadFile } from 'element-plus'
  import {
    formatModeCsv,
    isAlipayOfficialCertMode,
    normalizeAlipayOfficialSignMode,
    resolveQrTypeSelections
  } from '@/views/shared/paymentAccountCredential'
  import type { PaymentAccountCodeMeta } from '@/views/shared/paymentAccountMeta'
  import {
    displayAccountFieldLabel,
    displayAccountFieldPlaceholder,
    displayAccountFieldPlaceholderCompact
  } from '@/views/shared/paymentAccountDisplay'

  interface AlipayOfficialFormModel {
    identifier: string
    pid: string
    qr_type: string
    qr_url: string
    cookie: string
    remark: string
    wx_guid: string
    cloud_id: string
    extra_value: string
  }

  interface Props {
    model: AlipayOfficialFormModel
    meta: PaymentAccountCodeMeta
    compactPlaceholder?: boolean
  }

  const props = withDefaults(defineProps<Props>(), {
    compactPlaceholder: false
  })

  const fieldLabel = (value?: string, fallback = '--') =>
    displayAccountFieldLabel(value, fallback)
  const fieldPlaceholder = (value?: string, fallback = '') =>
    props.compactPlaceholder
      ? displayAccountFieldPlaceholderCompact(value, fallback)
      : displayAccountFieldPlaceholder(value, fallback)

  const selectedModes = computed<string[]>({
    get: () => resolveQrTypeSelections('alipay_official', props.model.qr_type, props.meta),
    set: (value) => {
      props.model.qr_type = formatModeCsv(value)
    }
  })
  const fieldVisible = reactive({
    wx_guid: false,
    cloud_id: false,
    extra_value: false
  })

  const signMode = computed<string>({
    get: () => normalizeAlipayOfficialSignMode(props.model.remark),
    set: (value) => {
      props.model.remark = value === 'cert' ? 'cert' : 'key'
    }
  })

  const isCertMode = computed(() => isAlipayOfficialCertMode(props.model.remark))

  function toggleFieldVisible(field: 'cloud_id' | 'extra_value' | 'wx_guid') {
    fieldVisible[field] = !fieldVisible[field]
  }

  async function handleCertificateChange(
    uploadFile: UploadFile,
    field: 'cloud_id' | 'extra_value' | 'wx_guid'
  ) {
    const raw = uploadFile.raw
    if (!(raw instanceof File)) {
      return
    }

    const fileName = String(raw.name || '').trim()
    if (!/\.(?:crt|pem|txt)$/i.test(fileName)) {
      ElMessage.warning('请读取 .crt、.pem 或 .txt 证书文件。')
      return
    }

    try {
      const content = (await raw.text()).trim()
      if (!content) {
        ElMessage.warning('证书内容为空，请重新选择文件。')
        return
      }

      props.model[field] = content
      ElMessage.success('证书内容读取成功。')
    } catch (_error) {
      ElMessage.error('证书内容读取失败，请稍后重试。')
    }
  }
</script>

<style scoped lang="scss">
  .alipay-official-fields {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .alipay-official-fields__mode-group {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px 16px;
    padding: 6px 0 2px;
  }

  .alipay-official-fields__mode-item {
    margin-right: 0;
  }

  .alipay-official-fields__sign-group {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
  }

  .alipay-official-fields__cert-shell {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .alipay-official-fields__cert-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
  }

  .alipay-official-fields__cert-tip {
    color: var(--el-color-success);
    font-size: 13px;
    line-height: 1.4;
  }

  .alipay-official-fields__cert-ready {
    color: var(--el-color-primary);
    font-size: 13px;
    font-weight: 600;
  }

  .alipay-official-fields__bottom-tip {
    padding: 11px 12px;
    border-radius: 12px;
    background: rgb(240 249 235 / 0.95);
    color: rgb(103 142 69 / 0.98);
    font-size: 13px;
    line-height: 1.6;
  }

  @media (width <= 991px) {
    .alipay-official-fields__mode-group {
      grid-template-columns: 1fr;
    }
  }
</style>
