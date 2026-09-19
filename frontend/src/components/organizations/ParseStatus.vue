<script setup lang="ts">
import { computed } from 'vue'

import AppAlert from '@/components/ui/AppAlert.vue'
import type { ParseRun } from '@/types/api'

const props = defineProps<{
  parseRun: ParseRun
}>()

const isActive = computed(() =>
  ['queued', 'running', 'retrying'].includes(props.parseRun.status),
)
const hasKnownProgress = computed(() => props.parseRun.reviews_expected !== null)
const label = computed(() => {
  if (props.parseRun.status === 'succeeded') {
    return 'Данные загружены'
  }

  if (props.parseRun.status === 'failed') {
    return 'Не удалось загрузить данные'
  }

  if (props.parseRun.status === 'retrying') {
    return 'Повторяем проверку организации'
  }

  return hasKnownProgress.value ? 'Загружаем отзывы' : 'Проверяем организацию'
})
const progress = computed(() => props.parseRun.progress_percent ?? 0)
const progressText = computed(() => {
  if (props.parseRun.reviews_expected === null) {
    return 'Проверяем ссылку и доступность карточки'
  }

  return (
    'Обработано: ' +
    props.parseRun.reviews_fetched +
    ' из ' +
    props.parseRun.reviews_expected
  )
})
</script>

<template>
  <section class="status" aria-live="polite">
    <div class="status__header">
      <strong>{{ label }}</strong>
      <span v-if="isActive && hasKnownProgress">{{ progress }}%</span>
    </div>

    <template v-if="isActive">
      <div
        class="status__track"
        :class="{ 'status__track--indeterminate': !hasKnownProgress }"
        role="progressbar"
        :aria-valuenow="hasKnownProgress ? progress : undefined"
        aria-valuemin="0"
        aria-valuemax="100"
      >
        <span
          class="status__fill"
          :class="{ 'status__fill--indeterminate': !hasKnownProgress }"
          :style="hasKnownProgress ? { width: progress + '%' } : undefined"
        />
      </div>
      <p class="status__details">
        {{ progressText }} · попытка {{ parseRun.attempt_count || 1 }}
      </p>
    </template>

    <AppAlert v-else-if="parseRun.status === 'failed'" type="error">
      Не удалось обновить данные. Попробуйте отправить ссылку ещё раз.
    </AppAlert>

    <AppAlert v-else type="success">
      Рейтинг, счётчики и отзывы обновлены.
    </AppAlert>
  </section>
</template>

<style scoped>
.status {
  display: grid;
  gap: 10px;
}

.status__header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  color: var(--color-text-strong);
}

.status__track {
  height: 12px;
  overflow: hidden;
  border-radius: 999px;
  background: color-mix(
    in srgb,
    var(--color-primary) 28%,
    var(--color-surface-card)
  );
}

.status__fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--color-primary);
  transition: width 0.25s ease;
}

.status__fill--indeterminate {
  width: 38%;
  animation: status-progress 1.2s ease-in-out infinite;
}

@keyframes status-progress {
  from {
    transform: translateX(-110%);
  }

  to {
    transform: translateX(290%);
  }
}

.status__details {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 13px;
}

@media (prefers-reduced-motion: reduce) {
  .status__fill {
    transition: none;
  }

  .status__fill--indeterminate {
    animation: none;
  }
}
</style>
