<script setup lang="ts">
import { computed } from 'vue'

import type { Organization } from '@/types/api'

const props = defineProps<{
  organization: Organization
}>()

const synchronizedAt = computed(() => {
  if (!props.organization.last_synced_at) {
    return 'Ещё не синхронизирована'
  }

  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(props.organization.last_synced_at))
})

const ratingTone = computed(() => {
  const rating = props.organization.rating

  if (rating === null) {
    return 'neutral'
  }

  if (rating <= 2) {
    return 'low'
  }

  return rating < 4 ? 'medium' : 'high'
})
</script>

<template>
  <section class="summary" aria-labelledby="organization-name">
    <div class="summary__main">
      <div class="summary__identity">
        <p class="summary__eyebrow">Организация</p>
        <h2 id="organization-name" class="summary__title">
          {{ organization.name }}
        </h2>
        <a
          class="summary__link"
          :href="organization.source_url"
          target="_blank"
          rel="noreferrer"
        >
          Открыть на Яндекс Картах
        </a>
      </div>

      <dl class="summary__metrics">
        <div class="summary__metric" :class="'summary__metric--' + ratingTone">
          <dt>Рейтинг</dt>
          <dd>
            <span v-if="organization.rating !== null" aria-hidden="true">★</span>
            {{ organization.rating ?? '—' }}
          </dd>
        </div>
        <div class="summary__metric summary__metric--count">
          <dt>Оценки</dt>
          <dd>{{ organization.ratings_count }}</dd>
        </div>
        <div class="summary__metric summary__metric--count">
          <dt>Отзывы</dt>
          <dd>{{ organization.reviews_count }}</dd>
        </div>
      </dl>
    </div>

    <p class="summary__synced">Последняя синхронизация: {{ synchronizedAt }}</p>
  </section>
</template>

<style scoped>
.summary {
  display: grid;
  gap: 20px;
}

.summary__main {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(430px, 0.85fr);
  align-items: center;
  gap: 32px;
}

.summary__identity {
  min-width: 0;
}

.summary__eyebrow {
  margin: 0 0 6px;
  color: var(--color-text-secondary);
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.summary__title {
  margin: 0;
  color: var(--color-text-strong);
  font-size: 26px;
  line-height: 1.2;
}

.summary__link {
  display: inline-block;
  margin-top: 10px;
  color: var(--color-link);
  font-size: 14px;
}

.summary__metrics {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin: 0;
}

.summary__metric {
  min-width: 0;
  padding: 16px;
  border: 1px solid var(--color-border);
  border-radius: 14px;
  background: var(--color-surface-subtle);
  box-shadow: 0 8px 24px var(--color-shadow);
}

.summary__metric dt {
  color: var(--color-text-secondary);
  font-size: 13px;
}

.summary__metric dd {
  margin: 5px 0 0;
  color: var(--color-text-strong);
  font-size: 24px;
  font-weight: 700;
}

.summary__metric--count {
  box-shadow:
    inset 0 3px 0 var(--color-primary),
    0 8px 24px var(--color-shadow);
}

.summary__metric--low {
  border-color: color-mix(
    in srgb,
    var(--color-error) 30%,
    var(--color-surface-card)
  );
  background: color-mix(
    in srgb,
    var(--color-error) 8%,
    var(--color-surface-card)
  );
}

.summary__metric--low dd {
  color: var(--color-error);
}

.summary__metric--medium {
  border-color: color-mix(
    in srgb,
    var(--color-warning) 30%,
    var(--color-surface-card)
  );
  background: color-mix(
    in srgb,
    var(--color-warning) 8%,
    var(--color-surface-card)
  );
}

.summary__metric--medium dd {
  color: var(--color-warning);
}

.summary__metric--high {
  border-color: color-mix(
    in srgb,
    var(--color-success) 30%,
    var(--color-surface-card)
  );
  background: color-mix(
    in srgb,
    var(--color-success) 8%,
    var(--color-surface-card)
  );
}

.summary__metric--high dd {
  color: var(--color-success);
}

.summary__synced {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 13px;
}

@media (max-width: 900px) {
  .summary__main {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 520px) {
  .summary__metrics {
    grid-template-columns: 1fr;
  }
}
</style>
