<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import * as organizationsApi from '@/api/organizations';
import { isUnauthorized, normalizeApiError } from '@/api/errors';
import AppPagination from '@/components/organizations/AppPagination.vue';
import OrganizationErrorState from '@/components/organizations/OrganizationErrorState.vue';
import OrganizationSummary from '@/components/organizations/OrganizationSummary.vue';
import ParseStatus from '@/components/organizations/ParseStatus.vue';
import ReviewCard from '@/components/organizations/ReviewCard.vue';
import AppPage from '@/components/layout/AppPage.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import AppAlert from '@/components/ui/AppAlert.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppCard from '@/components/ui/AppCard.vue';
import AppField from '@/components/ui/AppField.vue';
import LoadingOverlay from '@/components/ui/LoadingOverlay.vue';
import { authActions, authState } from '@/stores/auth';
import type {
  Organization,
  PaginationMeta,
  ParseRun,
  ParseRunStatus,
  Review
} from '@/types/api';

const router = useRouter();

const form = reactive({ url: '' });
const requestedUrl = ref('');
const organization = ref<Organization | null>(null);
const parseRun = ref<ParseRun | null>(null);
const reviews = ref<Review[]>([]);
const organizationDetails = ref<HTMLElement | null>(null);
const pagination = ref<PaginationMeta>({
  current_page: 1,
  last_page: 1,
  per_page: 50,
  total: 0
});

const isSubmitting = ref(false);
const isLoggingOut = ref(false);
const isReviewsLoading = ref(false);
const urlError = ref('');
const formError = ref('');
const pageError = ref('');
const pollingError = ref('');
const reviewsError = ref('');

let pollingTimer: ReturnType<typeof setTimeout> | null = null;
let pollingGeneration = 0;
let reviewsRequestId = 0;

const overlayVisible = computed(() => isSubmitting.value || isLoggingOut.value);
const overlayLabel = computed(() => {
  if (isLoggingOut.value) {
    return 'Выполняется выход…';
  }

  return 'Загружаем организацию…';
});

function isActiveStatus(status: ParseRunStatus): boolean {
  return status === 'queued' || status === 'running' || status === 'retrying';
}

function reviewSize(review: Review): 'compact' | 'medium' | 'full' {
  const textLength = review.text?.trim().length ?? 0;

  if (textLength <= 180) {
    return 'compact';
  }

  return textLength <= 520 ? 'medium' : 'full';
}

function validateUrl(): boolean {
  urlError.value = '';
  const value = form.url.trim();

  if (!value) {
    urlError.value = 'Вставьте ссылку на организацию.';

    return false;
  }

  try {
    const url = new URL(value);
    const host = url.hostname.replace(/^www\./, '').toLowerCase();
    const supportedHosts = [ 'yandex.ru', 'yandex.com', 'ya.ru' ];
    const supportedPath = /^\/maps\/(?:org(?:\/|$)|-\/[^/]+\/?$)/u.test(url.pathname);

    if (![ 'http:', 'https:' ].includes(url.protocol) || !supportedHosts.includes(host) || !supportedPath) {
      urlError.value = 'Введите ссылку в Яндекс Картах.';
    }
  } catch {
    urlError.value = 'Введите корректную ссылку.';
  }

  return !urlError.value;
}

function stopPolling(): void {
  pollingGeneration++;

  if (pollingTimer !== null) {
    clearTimeout(pollingTimer);
    pollingTimer = null;
  }
}

function schedulePolling(parseRunId: number): void {
  stopPolling();
  const generation = pollingGeneration;

  const poll = async (): Promise<void> => {
    if (generation !== pollingGeneration) {
      return;
    }

    try {
      const currentRun = await organizationsApi.getParseRun(parseRunId);

      if (generation !== pollingGeneration) {
        return;
      }

      parseRun.value = currentRun;
      pollingError.value = '';

      if (isActiveStatus(currentRun.status)) {
        pollingTimer = setTimeout(() => void poll(), 2000);

        return;
      }

      pollingTimer = null;

      if (currentRun.status === 'succeeded') {
        if (currentRun.organization_id === null) {
          pageError.value = 'Не удалось получить данные организации. Попробуйте ещё раз.';

          return;
        }

        const refreshedOrganization = await organizationsApi.getOrganization(
            currentRun.organization_id
        );

        if (generation !== pollingGeneration) {
          return;
        }

        organization.value = refreshedOrganization;
        parseRun.value = refreshedOrganization.latest_parse_run ?? currentRun;
        await loadReviews(1);
      }
    } catch (error: unknown) {
      if (isUnauthorized(error) || generation !== pollingGeneration) {
        return;
      }

      pollingError.value = normalizeApiError(
          error,
          'Не удалось обновить состояние загрузки.'
      ).message;
      pollingTimer = setTimeout(() => void poll(), 2000);
    }
  };

  pollingTimer = setTimeout(() => void poll(), 2000);
}

async function loadReviews(page: number): Promise<void> {
  const organizationId = organization.value?.id ?? null;

  if (organizationId === null) {
    return;
  }

  const requestId = ++reviewsRequestId;
  isReviewsLoading.value = true;
  reviewsError.value = '';

  try {
    const response = await organizationsApi.getReviews(organizationId, page);

    if (requestId !== reviewsRequestId || organizationId !== organization.value?.id) {
      return;
    }

    reviews.value = response.data;
    pagination.value = response.meta;
  } catch (error: unknown) {
    if (isUnauthorized(error)) {
      return;
    }

    reviewsError.value = normalizeApiError(
        error,
        'Не удалось загрузить отзывы.'
    ).message;
  } finally {
    if (requestId === reviewsRequestId) {
      isReviewsLoading.value = false;
    }
  }
}

async function changeReviewsPage(page: number): Promise<void> {
  organizationDetails.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  await loadReviews(page);
}

function resetResult(): void {
  stopPolling();
  reviewsRequestId++;
  organization.value = null;
  parseRun.value = null;
  reviews.value = [];
  reviewsError.value = '';
  pollingError.value = '';
  pagination.value = {
    current_page: 1,
    last_page: 1,
    per_page: 50,
    total: 0
  };

}

async function submit(): Promise<void> {
  formError.value = '';
  pageError.value = '';

  if (!validateUrl()) {
    return;
  }

  const submittedUrl = form.url.trim();

  resetResult();
  requestedUrl.value = submittedUrl;
  isSubmitting.value = true;

  try {
    const request = await organizationsApi.connectOrganization(submittedUrl);

    parseRun.value = request.parse_run;
    schedulePolling(request.parse_run.id);
  } catch (error: unknown) {
    if (isUnauthorized(error)) {
      return;
    }

    const apiError = normalizeApiError(error, 'Не удалось загрузить организацию.');

    if (apiError.status === 422) {
      urlError.value = apiError.fields.url?.[0] ?? '';
    }

    formError.value = apiError.message;
  } finally {
    isSubmitting.value = false;
  }
}

async function logout(): Promise<void> {
  pageError.value = '';
  isLoggingOut.value = true;

  try {
    await authActions.logout();
    await router.replace({ name: 'login' });
  } catch (error: unknown) {
    if (!isUnauthorized(error)) {
      pageError.value = normalizeApiError(error, 'Не удалось выйти из системы.').message;
    }
  } finally {
    isLoggingOut.value = false;
  }
}

onBeforeUnmount(() => {
  stopPolling();
  reviewsRequestId++;
});
</script>

<template>
  <AppPage>
    <div class="dashboard">
      <PageHeader title="Отзывы организаций" title-id="organizations-title">
        Вставьте ссылку на организацию в Яндекс Картах, чтобы посмотреть рейтинг и отзывы.

        <template #actions>
          <div class="dashboard__user">
            <span>{{ authState.user?.name || authState.user?.email }}</span>
            <AppButton
                variant="secondary"
                :disabled="isLoggingOut"
                @click="logout"
            >
              Выйти
            </AppButton>
          </div>
        </template>
      </PageHeader>

      <AppAlert v-if="pageError" type="error">{{ pageError }}</AppAlert>

      <AppCard wide>
        <form class="connect-form" novalidate @submit.prevent="submit">
          <AppField
              id="organization-url"
              v-model="form.url"
              label="Ссылка на организацию"
              type="url"
              name="organization-url"
              autocomplete="url"
              placeholder="https://yandex.ru/maps/org/..."
              :disabled="isSubmitting"
              :error="urlError"
          />
          <AppButton type="submit" :disabled="isSubmitting">
            Просмотр
          </AppButton>
        </form>
        <AppAlert v-if="formError" class="connect-form__error" type="error">
          {{ formError }}
        </AppAlert>
      </AppCard>

      <section class="dashboard__details">
        <AppCard v-if="parseRun?.status === 'failed'" wide>
          <OrganizationErrorState :parse-run="parseRun" :source-url="requestedUrl"/>
        </AppCard>

        <AppCard v-else-if="parseRun && isActiveStatus(parseRun.status)" wide>
          <ParseStatus :parse-run="parseRun"/>
          <AppAlert v-if="pollingError" class="dashboard__parse-status" type="error">
            {{ pollingError }}
          </AppAlert>
        </AppCard>

        <template v-else-if="organization">
          <div ref="organizationDetails" class="organization-details">
            <AppCard wide>
              <OrganizationSummary :organization="organization"/>
              <ParseStatus
                  v-if="parseRun"
                  class="dashboard__parse-status"
                  :parse-run="parseRun"
              />
              <AppAlert v-if="pollingError" class="dashboard__parse-status" type="error">
                {{ pollingError }}
              </AppAlert>
            </AppCard>
          </div>

          <AppCard wide>
            <div class="reviews-header">
              <h2 class="section-title">Отзывы</h2>
              <span v-if="isReviewsLoading" class="reviews-header__loading">
                  Загружаем отзывы…
              </span>
            </div>

            <AppAlert v-if="reviewsError" type="error">{{ reviewsError }}</AppAlert>
            <AppAlert v-if="isReviewsLoading && reviews.length === 0" type="info">
              Загружаем отзывы…
            </AppAlert>
            <AppAlert v-else-if="reviews.length === 0" type="info">
              У организации пока нет доступных отзывов.
            </AppAlert>

            <template v-if="reviews.length > 0">
              <AppPagination
                  class="reviews-pagination reviews-pagination--top"
                  :current-page="pagination.current_page"
                  :last-page="pagination.last_page"
                  :disabled="isReviewsLoading"
                  @change="changeReviewsPage"
              />

              <div
                  class="reviews-grid"
                  :class="{ 'reviews-grid--loading': isReviewsLoading }"
                  :aria-busy="isReviewsLoading"
              >
                <div
                    v-for="review in reviews"
                    :key="review.external_id"
                    class="reviews-grid__item"
                    :class="'reviews-grid__item--' + reviewSize(review)"
                >
                  <ReviewCard :review="review"/>
                </div>
              </div>
            </template>

            <AppPagination
                class="reviews-pagination"
                :current-page="pagination.current_page"
                :last-page="pagination.last_page"
                :disabled="isReviewsLoading"
                @change="changeReviewsPage"
            />
          </AppCard>
        </template>
      </section>
    </div>

    <LoadingOverlay :visible="overlayVisible" :label="overlayLabel"/>
  </AppPage>
</template>

<style scoped>
.dashboard {
  display: grid;
  gap: 24px;
  width: min(100%, 1180px);
}

.dashboard__user {
  display: flex;
  align-items: center;
  gap: 14px;
  color: var(--color-text-secondary);
  font-size: 14px;
}

.dashboard__details {
  display: grid;
  gap: 24px;
  min-width: 0;
}

.dashboard__parse-status {
  margin-top: 24px;
}

.organization-details {
  scroll-margin-top: 24px;
}

.connect-form {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 12px;
}

.connect-form__error {
  margin-top: 14px;
}

.section-title {
  margin: 0 0 18px;
  color: var(--color-text-strong);
  font-size: 20px;
}

.reviews-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.reviews-header .section-title {
  margin-bottom: 0;
}

.reviews-header__loading {
  color: var(--color-text-secondary);
  font-size: 13px;
}

.reviews-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  grid-auto-flow: dense;
  align-items: stretch;
  gap: 14px;
  margin: 18px 0;
  transition: opacity 0.2s ease;
}

.reviews-grid--loading {
  opacity: 0.55;
  pointer-events: none;
}

.reviews-grid__item {
  min-width: 0;
}

.reviews-grid__item--compact {
  grid-column: span 1;
}

.reviews-grid__item--medium {
  grid-column: span 2;
}

.reviews-grid__item--full {
  grid-column: 1 / -1;
}

.reviews-pagination--top {
  margin-top: 18px;
}

@media (max-width: 900px) {
  .reviews-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .reviews-grid__item--compact {
    grid-column: span 1;
  }

  .reviews-grid__item--medium {
    grid-column: 1 / -1;
  }
}

@media (max-width: 600px) {
  .reviews-grid {
    grid-template-columns: 1fr;
  }

  .reviews-grid__item--compact,
  .reviews-grid__item--medium,
  .reviews-grid__item--full {
    grid-column: 1 / -1;
  }

  .dashboard__user,
  .connect-form {
    align-items: stretch;
    grid-template-columns: 1fr;
  }

  .dashboard__user {
    display: grid;
  }
}
</style>
