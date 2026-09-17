<script setup lang="ts">
withDefaults(
  defineProps<{
    id: string
    label: string
    type?: 'email' | 'password' | 'text' | 'url'
    name?: string
    autocomplete?: string
    placeholder?: string
    disabled?: boolean
    error?: string
  }>(),
  {
    type: 'text',
    name: undefined,
    autocomplete: undefined,
    placeholder: undefined,
    disabled: false,
    error: '',
  },
)

const model = defineModel<string>({ required: true })
</script>

<template>
  <div class="field">
    <label class="field__label" :for="id">{{ label }}</label>
    <input
      :id="id"
      v-model="model"
      class="field__input"
      :class="{ 'field__input--invalid': error }"
      :type="type"
      :name="name"
      :autocomplete="autocomplete"
      :placeholder="placeholder"
      :disabled="disabled"
      :aria-invalid="Boolean(error)"
      :aria-describedby="error ? `${id}-error` : undefined"
    />
    <span v-if="error" :id="`${id}-error`" class="field__error">{{ error }}</span>
  </div>
</template>

<style scoped>
.field {
  display: grid;
  gap: 7px;
}

.field__label {
  font-size: 14px;
  font-weight: 600;
}

.field__input {
  width: 100%;
  padding: 11px 12px;
  border: 1px solid var(--color-border-input);
  border-radius: 8px;
  color: var(--color-text-strong);
  background: var(--color-surface-card);
  outline: none;
}

.field__input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--color-focus-ring);
}

.field__input--invalid {
  border-color: var(--color-error-input);
}

.field__input:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}

.field__error {
  color: var(--color-error);
  font-size: 14px;
}
</style>
