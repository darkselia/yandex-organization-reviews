<template>
  <main class="page">
    <slot />
  </main>
</template>

<style scoped>
.page {
  display: grid;
  min-height: 100vh;
  place-items: center;
  padding: 24px 16px;
}
</style>
