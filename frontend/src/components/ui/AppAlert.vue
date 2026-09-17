<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'error' | 'info' | 'success'
  }>(),
  {
    type: 'info',
  },
)
</script>

<template>
  <div
    class="alert"
    :class="`alert--${type}`"
    :role="type === 'error' ? 'alert' : 'status'"
  >
    <slot />
  </div>
</template>

<style scoped>
.alert {
  padding: 10px 12px;
  border: 1px solid transparent;
  border-radius: 8px;
  font-size: 14px;
  line-height: 1.55;
}

.alert--error {
  border-color: var(--color-error-border);
  color: var(--color-error);
  background: var(--color-error-surface);
}

.alert--info {
  border-color: var(--color-info-border);
  color: var(--color-info);
  background: var(--color-info-surface);
}

.alert--success {
  border-color: var(--color-success-border);
  color: var(--color-success);
  background: var(--color-success-surface);
}
</style>
