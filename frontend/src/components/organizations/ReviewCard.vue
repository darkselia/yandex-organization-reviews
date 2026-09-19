<script setup lang="ts">
import { computed } from 'vue'

import type { Review } from '@/types/api'

const props = defineProps<{
  review: Review
}>()

const publishedAt = computed(() =>
  new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
  }).format(new Date(props.review.published_at)),
)

const ratingTone = computed(() => {
  if (props.review.rating <= 2) {
    return 'low'
  }

  return props.review.rating === 3 ? 'medium' : 'high'
})
</script>

<template>
  <article class="review">
    <header class="review__header">
      <div>
        <h3 class="review__author">{{ review.author_name }}</h3>
        <time class="review__date" :datetime="review.published_at">{{ publishedAt }}</time>
      </div>
      <span
        class="review__rating"
        :class="'review__rating--' + ratingTone"
        :aria-label="'Оценка ' + review.rating + ' из 5'"
      >
        {{ review.rating }} ★
      </span>
    </header>

    <p v-if="review.text" class="review__text">{{ review.text }}</p>
    <p v-else class="review__empty">Пользователь оставил оценку без текста.</p>
  </article>
</template>

<style scoped>
.review {
  display: grid;
  height: 100%;
  gap: 14px;
  padding: 20px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  background: var(--color-surface-card);
}

.review__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.review__author {
  margin: 0;
  color: var(--color-text-strong);
  font-size: 16px;
}

.review__date {
  display: block;
  margin-top: 4px;
  color: var(--color-text-secondary);
  font-size: 13px;
}

.review__rating {
  flex: none;
  padding: 5px 9px;
  border: 1px solid currentColor;
  border-radius: 999px;
  font-weight: 700;
}

.review__rating--low {
  color: var(--color-error);
  background: color-mix(in srgb, var(--color-error) 8%, var(--color-surface-card));
}

.review__rating--medium {
  color: var(--color-warning);
  background: color-mix(in srgb, var(--color-warning) 8%, var(--color-surface-card));
}

.review__rating--high {
  color: var(--color-success);
  background: color-mix(in srgb, var(--color-success) 8%, var(--color-surface-card));
}

.review__text,
.review__empty {
  margin: 0;
  line-height: 1.6;
  overflow-wrap: anywhere;
  white-space: pre-line;
}

.review__empty {
  color: var(--color-text-secondary);
  font-style: italic;
}
</style>
