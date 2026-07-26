<template>
  <div v-if="enabled" class="MerchantAuthSlider">
    <div class="MerchantAuthSlider__shell" :class="{ 'is-invalid': invalid && !modelValue }">
      <ArtDragVerify
        ref="dragVerify"
        :value="modelValue"
        :text="text"
        textColor="var(--art-gray-700)"
        :successText="successText"
        progressBarBg="var(--main-color)"
        :background="isDark ? '#26272F' : '#F1F1F4'"
        handlerBg="var(--default-box-color)"
        @update:value="handleUpdate"
      />
    </div>

    <p class="MerchantAuthSlider__error" :class="{ 'is-visible': invalid && !modelValue }">
      请先完成滑动验证
    </p>
  </div>
</template>

<script setup lang="ts">
  import { useSettingStore } from '@/store/modules/setting'

  defineOptions({ name: 'MerchantAuthSlider' })

  const props = withDefaults(
    defineProps<{
      modelValue: boolean
      enabled?: boolean
      invalid?: boolean
      text?: string
      successText?: string
    }>(),
    {
      enabled: false,
      invalid: false,
      text: '请按住滑块拖动到最右侧',
      successText: '验证通过'
    }
  )

  const emit = defineEmits<{
    'update:modelValue': [value: boolean]
  }>()

  const settingStore = useSettingStore()
  const { isDark } = storeToRefs(settingStore)
  const dragVerify = ref<InstanceType<typeof import('@/components/core/forms/artDragVerify/index.vue').default> | null>(null)

  function handleUpdate(value: boolean) {
    emit('update:modelValue', value)
  }

  function reset() {
    dragVerify.value?.reset?.()
  }

  defineExpose({
    reset
  })
</script>

<style lang="scss" scoped>
  .MerchantAuthSlider {
    margin: 6px 0 18px;
  }

  .MerchantAuthSlider__shell {
    border: 1px solid transparent;
    border-radius: 16px;
    overflow: hidden;
    transition: border-color 0.2s ease;
  }

  .MerchantAuthSlider__shell.is-invalid {
    border-color: var(--el-color-danger);
  }

  .MerchantAuthSlider__error {
    height: 0;
    margin: 0;
    overflow: hidden;
    color: var(--el-color-danger);
    font-size: 12px;
    line-height: 1.6;
    transition: all 0.2s ease;
  }

  .MerchantAuthSlider__error.is-visible {
    height: 20px;
    margin-top: 6px;
  }
 </style>
