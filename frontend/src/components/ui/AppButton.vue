<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'button' | 'submit' | 'reset'
    variant?: 'primary' | 'secondary'
    disabled?: boolean
  }>(),
  {
    type: 'button',
    variant: 'primary',
    disabled: false,
  },
)
</script>

<template>
  <button
    class="button"
    :class="`button--${variant}`"
    :type="type"
    :disabled="disabled"
  >
    <slot />
  </button>
</template>

<style scoped>
.button {
  min-height: 44px;
  padding: 10px 16px;
  border: 0;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
}

.button:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.button--primary {
  color: var(--color-text-inverse);
  background: var(--color-primary);
}

.button--primary:hover:not(:disabled) {
  background: var(--color-primary-hover);
}

.button--secondary {
  color: var(--color-text-strong);
  background: var(--color-secondary);
}

.button--secondary:hover:not(:disabled) {
  background: var(--color-secondary-hover);
}
</style>
