<!--
  版权归属 TG:RENBUZAIHA 所有
  唯一发布路径: https://github.com/hzgz/AiPay.git
-->

<template>
  <div>
    <div class="flex justify-end">
      <div
        @click="$emit('close')"
        class="flex-cc c-p size-7.5 !transition-all duration-200 rounded hover:bg-g-300/80"
      >
        <ArtSvgIcon icon="ri:close-fill" class="block text-xl text-g-600" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  defineEmits<{
    close: []
  }>()
</script>
