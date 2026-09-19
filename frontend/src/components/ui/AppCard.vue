<script setup lang="ts">
withDefaults(
  defineProps<{
    wide?: boolean
  }>(),
  {
    wide: false,
  },
)
</script>

<template>
  <section class="card" :class="{ 'card--wide': wide }">
    <slot />
  </section>
</template>

<style scoped>
.card {
  width: min(100%, 420px);
  padding: 32px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  background: var(--color-surface-card);
  box-shadow: 0 12px 30px var(--color-shadow);
}

.card--wide {
  width: 100%;
  max-width: none;
}

@media (max-width: 520px) {
  .card {
    padding: 24px;
  }
}
</style>
