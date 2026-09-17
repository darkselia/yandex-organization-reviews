<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import { normalizeApiError } from '@/api/errors'
import AppPage from '@/components/layout/AppPage.vue'
import PageHeader from '@/components/layout/PageHeader.vue'
import AppAlert from '@/components/ui/AppAlert.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppCard from '@/components/ui/AppCard.vue'
import AppField from '@/components/ui/AppField.vue'
import LoadingOverlay from '@/components/ui/LoadingOverlay.vue'
import { authActions, authState } from '@/stores/auth'

const router = useRouter()

const form = reactive({
  email: '',
  password: '',
})

const errors = reactive({
  email: '',
  password: '',
})

const isSubmitting = ref(false)
const submitError = ref('')
const displayedError = computed(() => submitError.value || authState.initializationError || '')

function validate(): boolean {
  errors.email = ''
  errors.password = ''

  if (!form.email.trim()) {
    errors.email = 'Введите email.'
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    errors.email = 'Введите корректный email.'
  }

  if (!form.password) {
    errors.password = 'Введите пароль.'
  }

  return !errors.email && !errors.password
}

async function submit(): Promise<void> {
  submitError.value = ''

  if (!validate()) {
    return
  }

  isSubmitting.value = true

  try {
    await authActions.login({
      email: form.email.trim(),
      password: form.password,
    })
    await router.replace({ name: 'organizations' })
  } catch (error: unknown) {
    const apiError = normalizeApiError(error, 'Не удалось войти. Попробуйте ещё раз.')

    if (apiError.status === 422) {
      errors.email = apiError.fields.email?.[0] ?? ''
      errors.password = apiError.fields.password?.[0] ?? ''
    }

    submitError.value = apiError.message
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <AppPage>
    <img src="@/assets/images/flower.svg" alt="flower" class="flower" />
    <AppCard aria-labelledby="login-title">
      <PageHeader title="Вход" title-id="login-title"/>

      <form class="login-form" novalidate @submit.prevent="submit">
        <AppField
          id="email"
          v-model="form.email"
          label="Email"
          type="email"
          name="email"
          autocomplete="username"
          placeholder="demo@example.com"
          :disabled="isSubmitting"
          :error="errors.email"
        />

        <AppField
          id="password"
          v-model="form.password"
          label="Пароль"
          type="password"
          name="password"
          autocomplete="current-password"
          placeholder="demo-password"
          :disabled="isSubmitting"
          :error="errors.password"
        />

        <AppAlert v-if="displayedError" type="error">{{ displayedError }}</AppAlert>

        <AppButton type="submit" :disabled="isSubmitting">Войти</AppButton>
      </form>

      <AppAlert class="credentials" type="info">
        Тестовый аккаунт:<br />
        <strong>demo@example.com</strong><br />
        <strong>demo-password</strong>
      </AppAlert>
    </AppCard>

    <LoadingOverlay :visible="isSubmitting" label="Выполняется вход…" />
  </AppPage>
</template>

<style scoped>
.login-form {
  display: grid;
  gap: 18px;
  margin-top: 28px;
}

.credentials {
  margin-top: 20px;
}

.flower{
  position: absolute;
  top: -8%;
  right: 0;
  z-index: -1;
  width: 600px;
  filter: blur(10px) grayscale(30%);
}

@media (width <= 600px)  {
  .flower{
    right: auto;
    width: 100%;
  }
}
</style>
