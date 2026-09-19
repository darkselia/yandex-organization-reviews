<script setup lang="ts">
import { computed } from 'vue'

import type { ParseRun, ParseRunErrorCode } from '@/types/api'

const props = defineProps<{
  parseRun: ParseRun
  sourceUrl: string
}>()

const errorMessages: Record<ParseRunErrorCode, { title: string; message: string }> = {
  organization_unavailable: {
    title: 'Не удалось найти организацию',
    message:
      'Неверная ссылка или данные временно недоступны.',
  },
  source_temporarily_unavailable: {
    title: 'Яндекс Карты временно недоступны',
    message: 'Не удалось получить данные организации. Подождите немного и попробуйте ещё раз.',
  },
  processing_failed: {
    title: 'Не удалось загрузить организацию',
    message: 'Во время обработки произошла ошибка. Попробуйте отправить ссылку ещё раз.',
  },
}

const errorMessage = computed(() =>
  errorMessages[props.parseRun.error?.code ?? 'processing_failed'],
)
</script>

<template>
  <section class="organization-error" role="alert" aria-live="assertive">
    <div class="organization-error__icon" aria-hidden="true">!</div>

    <div class="organization-error__content">
      <p class="organization-error__eyebrow">Не удалось получить данные</p>
      <h2 class="organization-error__title">{{ errorMessage.title }}</h2>
      <p class="organization-error__message">{{ errorMessage.message }}</p>

      <div class="organization-error__source">
        <span>Проверяемая ссылка</span>
        <a :href="sourceUrl" target="_blank" rel="noreferrer">{{ sourceUrl }}</a>
      </div>

      <p class="organization-error__hint">
        Убедитесь, что ссылка открывает карточку организации, затем исправьте её в поле выше и
        повторите запрос.
      </p>
    </div>
  </section>
</template>

<style scoped>
.organization-error {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 20px;
  padding: 24px;
  border: 1px solid color-mix(in srgb, var(--color-error) 28%, var(--color-surface-card));
  border-radius: 16px;
  background:
    linear-gradient(
      135deg,
      color-mix(in srgb, var(--color-error) 9%, var(--color-surface-card)),
      var(--color-surface-card)
    );
}

.organization-error__icon {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  color: var(--color-surface-card);
  background: var(--color-error);
  font-size: 26px;
  font-weight: 800;
}

.organization-error__content {
  min-width: 0;
}

.organization-error__eyebrow {
  margin: 0 0 5px;
  color: var(--color-error);
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.organization-error__title {
  margin: 0;
  color: var(--color-text-strong);
  font-size: 24px;
}

.organization-error__message {
  margin: 10px 0 0;
  line-height: 1.6;
}

.organization-error__source {
  display: grid;
  gap: 4px;
  margin-top: 18px;
  padding: 12px 14px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--color-surface-card) 75%, transparent);
}

.organization-error__source span,
.organization-error__hint {
  color: var(--color-text-secondary);
  font-size: 13px;
}

.organization-error__source a {
  overflow: hidden;
  color: var(--color-link);
  text-overflow: ellipsis;
  white-space: nowrap;
}

.organization-error__hint {
  margin: 14px 0 0;
  line-height: 1.5;
}

@media (max-width: 600px) {
  .organization-error {
    grid-template-columns: 1fr;
  }
}
</style>
