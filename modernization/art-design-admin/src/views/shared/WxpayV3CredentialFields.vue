<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div class="wxpay-v3-fields">
    <ElFormItem label="请选择可用的接口" required>
      <ElCheckboxGroup v-model="selectedModes" class="wxpay-v3-fields__mode-group">
        <ElCheckbox
          v-for="option in meta.qrTypeOptions"
          :key="option.value"
          :label="option.value"
          class="wxpay-v3-fields__mode-item"
        >
          {{ option.label }}
        </ElCheckbox>
      </ElCheckboxGroup>
    </ElFormItem>

    <ElFormItem label="服务号/小程序/开放平台AppID" required>
      <ElInput
        v-model="model.identifier"
        maxlength="50"
        placeholder="请输入服务号/小程序/开放平台AppID"
      />
    </ElFormItem>

    <ElFormItem label="商户号" required>
      <ElInput v-model="model.pid" maxlength="50" placeholder="请输入商户号" />
    </ElFormItem>

    <ElFormItem label="商户APIv3密钥" required>
      <ElInput
        v-model="model.remark"
        maxlength="225"
        placeholder="请输入商户APIv3密钥"
      />
    </ElFormItem>

    <ElFormItem label="商户API证书序列号" required>
      <ElInput
        v-model="model.wx_guid"
        maxlength="120"
        placeholder="请输入商户API证书序列号"
      />
    </ElFormItem>

    <ElFormItem label="微信支付公钥ID">
      <ElInput
        v-model="model.cloud_id"
        maxlength="50"
        placeholder="平台证书模式需要留空"
      />
    </ElFormItem>

    <ElFormItem label="商户API私钥" required>
      <div class="wxpay-v3-fields__pem-shell">
        <div class="wxpay-v3-fields__pem-actions">
          <ElUpload
            accept=".pem,.key,.crt,.cer,.txt,application/x-pem-file,text/plain"
            :auto-upload="false"
            :show-file-list="false"
            :on-change="(file) => handlePemChange(file, 'qr_url')"
          >
            <ElButton type="primary" plain>{{ uploadButtonText('qr_url') }}</ElButton>
          </ElUpload>
          <span class="wxpay-v3-fields__pem-tip">{{ uploadStatusText('qr_url') }}</span>
          <ElButton link @click="toggleFieldVisible('qr_url')">
            {{ fieldVisible.qr_url ? '隐藏内容' : '查看内容' }}
          </ElButton>
          <ElButton v-if="model.qr_url" link type="danger" @click="clearField('qr_url')">
            移除
          </ElButton>
        </div>
        <ElInput
          v-if="fieldVisible.qr_url"
          v-model="model.qr_url"
          type="textarea"
          :rows="5"
          maxlength="12000"
          placeholder="上传商户API私钥文件后自动填入"
        />
      </div>
    </ElFormItem>

    <ElFormItem label="微信支付公钥">
      <div class="wxpay-v3-fields__pem-shell">
        <div class="wxpay-v3-fields__pem-actions">
          <ElUpload
            accept=".pem,.key,.crt,.cer,.txt,application/x-pem-file,text/plain"
            :auto-upload="false"
            :show-file-list="false"
            :on-change="(file) => handlePemChange(file, 'cookie')"
          >
            <ElButton type="primary" plain>{{ uploadButtonText('cookie') }}</ElButton>
          </ElUpload>
          <span class="wxpay-v3-fields__pem-tip">{{ uploadStatusText('cookie') }}</span>
          <ElButton link @click="toggleFieldVisible('cookie')">
            {{ fieldVisible.cookie ? '隐藏内容' : '查看内容' }}
          </ElButton>
          <ElButton v-if="model.cookie" link type="danger" @click="clearField('cookie')">
            移除
          </ElButton>
        </div>
        <ElInput
          v-if="fieldVisible.cookie"
          v-model="model.cookie"
          type="textarea"
          :rows="5"
          maxlength="12000"
          placeholder="上传微信支付公钥文件后自动填入"
        />
      </div>
    </ElFormItem>

    <ElFormItem label="商户APIv2密钥">
      <ElInput
        v-model="model.extra_value"
        maxlength="50"
        placeholder="非必填，仅付款码支付需要填写"
      />
    </ElFormItem>
  </div>
</template>

<script setup lang="ts">
  import { computed, reactive } from 'vue'
  import { ElMessage, type UploadFile } from 'element-plus'
  import { formatModeCsv, resolveQrTypeSelections } from '@/views/shared/paymentAccountCredential'
  import type { PaymentAccountCodeMeta } from '@/views/shared/paymentAccountMeta'

  interface WxpayV3FormModel {
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
    model: WxpayV3FormModel
    meta: PaymentAccountCodeMeta
    compactPlaceholder?: boolean
  }

  const props = withDefaults(defineProps<Props>(), {
    compactPlaceholder: false
  })

  const fieldVisible = reactive({
    qr_url: false,
    cookie: false
  })

  const uploadedFileNames = reactive({
    qr_url: '',
    cookie: ''
  })

  const selectedModes = computed<string[]>({
    get: () => resolveQrTypeSelections('wxpay_v3', props.model.qr_type, props.meta),
    set: (value) => {
      props.model.qr_type = formatModeCsv(value)
    }
  })

  function uploadButtonText(field: 'cookie' | 'qr_url') {
    return props.model[field] ? '已上传文件' : '上传文件'
  }

  function uploadStatusText(field: 'cookie' | 'qr_url') {
    if (uploadedFileNames[field]) {
      return uploadedFileNames[field]
    }

    if (props.model[field]) {
      return '已上传文件'
    }

    return '支持 .pem / .key / .crt / .cer / .txt'
  }

  function toggleFieldVisible(field: 'cookie' | 'qr_url') {
    fieldVisible[field] = !fieldVisible[field]
  }

  function clearField(field: 'cookie' | 'qr_url') {
    props.model[field] = ''
    uploadedFileNames[field] = ''
    fieldVisible[field] = false
  }

  async function handlePemChange(uploadFile: UploadFile, field: 'cookie' | 'qr_url') {
    const raw = uploadFile.raw
    if (!(raw instanceof File)) {
      return
    }

    const fileName = String(raw.name || '').trim()
    if (!/\.(?:pem|key|crt|cer|txt)$/i.test(fileName)) {
      ElMessage.warning('请上传 .pem、.key、.crt、.cer 或 .txt 文件')
      return
    }

    if (raw.size > 512 * 1024) {
      ElMessage.warning('文件不能超过 512KB')
      return
    }

    try {
      const content = (await raw.text()).replace(/\r\n?/g, '\n').trim()
      if (!content) {
        ElMessage.warning('文件内容为空，请重新选择')
        return
      }

      props.model[field] = content
      uploadedFileNames[field] = fileName || '已上传文件'
      fieldVisible[field] = false
      ElMessage.success('文件内容读取成功')
    } catch (_error) {
      ElMessage.error('文件读取失败，请稍后重试')
    }
  }
</script>

<style scoped lang="scss">
  .wxpay-v3-fields {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .wxpay-v3-fields__mode-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 18px;
    padding: 4px 0 2px;
  }

  .wxpay-v3-fields__mode-item {
    margin-right: 0;
  }

  .wxpay-v3-fields__pem-shell {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .wxpay-v3-fields__pem-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
  }

  .wxpay-v3-fields__pem-actions :deep(.el-button) {
    min-width: 96px;
    height: 34px;
    border-radius: 10px;
  }

  .wxpay-v3-fields__pem-actions :deep(.el-button--link) {
    min-width: auto;
    height: auto;
    padding: 0;
    border-radius: 0;
  }

  .wxpay-v3-fields__pem-tip {
    color: var(--el-text-color-secondary);
    font-size: 13px;
    line-height: 1.5;
  }
</style>
