<script setup lang="ts">
import { computed } from 'vue'

import AppButton from '@/components/ui/AppButton.vue'

const props = withDefaults(
  defineProps<{
    currentPage: number
    lastPage: number
    disabled?: boolean
  }>(),
  {
    disabled: false,
  },
)

const emit = defineEmits<{
  change: [page: number]
}>()

const pages = computed(() =>
  Array.from({ length: Math.max(props.lastPage, 1) }, (_, index) => index + 1),
)

function change(page: number): void {
  if (props.disabled || page < 1 || page > props.lastPage || page === props.currentPage) {
    return
  }

  emit('change', page)
}
</script>

<template>
  <nav v-if="lastPage > 1" class="pagination" aria-label="Страницы отзывов">
    <AppButton
      variant="secondary"
      :disabled="disabled || currentPage === 1"
      @click="change(currentPage - 1)"
    >
      Назад
    </AppButton>

    <div class="pagination__pages">
      <button
        v-for="page in pages"
        :key="page"
        class="pagination__page"
        :class="{ 'pagination__page--active': page === currentPage }"
        type="button"
        :disabled="disabled"
        :aria-current="page === currentPage ? 'page' : undefined"
        @click="change(page)"
      >
        {{ page }}
      </button>
    </div>

    <AppButton
      variant="secondary"
      :disabled="disabled || currentPage === lastPage"
      @click="change(currentPage + 1)"
    >
      Вперед
    </AppButton>
  </nav>
</template>

<style scoped>
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.pagination__pages {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px;
}

.pagination__page {
  min-width: 36px;
  min-height: 36px;
  padding: 6px;
  border: 1px solid var(--color-secondary-hover);
  border-radius: 8px;
  color: var(--color-text-strong);
  background: var(--color-surface-card);
  cursor: pointer;
}

.pagination__page:hover:not(:disabled) {
  border-color: var(--color-primary);
}

.pagination__page--active {
  border-color: var(--color-primary);
  color: var(--color-surface-card);
  background: var(--color-primary);
}

.pagination__page:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

@media (max-width: 600px) {
  .pagination {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
