<template>
  <div class="page-shell">
    <main class="page">
      <slot />
    </main>

    <AppFooter />
  </div>
</template>

<script setup lang="ts">
import AppFooter from '@/components/layout/AppFooter.vue'
</script>

<style scoped>
.page-shell {
  display: grid;
  grid-template-rows: 1fr auto;
  min-height: 100vh;
}

.page {
  display: grid;
  place-items: center;
  padding: 126px 16px 24px;
}

@media (width <= 800px){
  .page {
    padding: 24px 16px;
  }
}
</style>
