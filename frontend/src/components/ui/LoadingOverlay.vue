<script setup lang="ts">
withDefaults(
  defineProps<{
    visible: boolean
    label?: string
  }>(),
  {
    label: 'Загрузка…',
  },
)
</script>

<template>
  <Teleport to="body">
    <Transition name="overlay">
      <div v-if="visible" class="loading-overlay" role="status" aria-live="polite">
        <span class="loading-overlay__spinner" aria-hidden="true" />
        <span>{{ label }}</span>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.loading-overlay {
  position: fixed;
  z-index: 1000;
  inset: 0;
  display: grid;
  align-content: center;
  justify-items: center;
  gap: 12px;
  color: var(--color-text-strong);
  background: var(--color-overlay);
  backdrop-filter: blur(2px);
}

.loading-overlay__spinner {
  width: 42px;
  height: 42px;
  border: 4px solid var(--color-info-border);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: spin 0.75s linear infinite;
}

.overlay-enter-active,
.overlay-leave-active {
  transition: opacity 0.15s ease;
}

.overlay-enter-from,
.overlay-leave-to {
  opacity: 0;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .loading-overlay__spinner {
    animation-duration: 1.5s;
  }
}
</style>
