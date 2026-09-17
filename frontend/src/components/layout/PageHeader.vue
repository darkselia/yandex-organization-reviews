<script setup lang="ts">
defineProps<{
  title: string
  titleId: string
}>()
</script>

<template>
  <header class="page-header">
    <div>
      <h1 :id="titleId" class="page-header__title">{{ title }}</h1>
      <div v-if="$slots.default" class="page-header__description">
        <slot />
      </div>
    </div>

    <div v-if="$slots.actions" class="page-header__actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<style scoped>
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
}

.page-header__title {
  margin: 0;
  font-size: 28px;
  line-height: 1.2;
}

.page-header__description {
  margin-top: 8px;
  color: var(--color-text-secondary);
}

.page-header__actions {
  flex: none;
}

@media (max-width: 520px) {
  .page-header {
    display: grid;
  }
}
</style>
