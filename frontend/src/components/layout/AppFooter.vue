<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

const currentYear = new Date().getFullYear()
const footerAnchor = ref<HTMLElement | null>(null)
const isDocked = ref(false)

let observer: IntersectionObserver | null = null

onMounted(() => {
  if (!footerAnchor.value || !('IntersectionObserver' in window)) {
    isDocked.value = true

    return
  }

  observer = new IntersectionObserver(([entry]) => {
    isDocked.value = entry?.isIntersecting ?? false
  })
  observer.observe(footerAnchor.value)
})

onBeforeUnmount(() => {
  observer?.disconnect()
})
</script>

<template>
  <div ref="footerAnchor" class="footer-anchor">
    <footer class="footer" :class="{ 'footer--floating': !isDocked }">
      <div class="footer__content">
        <img
          class="footer__flower"
          src="@/assets/images/flower.svg"
          alt=""
          aria-hidden="true"
        />

        <p class="footer__author">© {{ currentYear }} Darkselia</p>

        <nav class="footer__links" aria-label="Ссылки автора">
          <a href="https://github.com/darkselia" target="_blank" rel="noreferrer">GitHub</a>
          <a href="https://darkselia.ru" target="_blank" rel="noreferrer">darkselia.ru</a>
        </nav>
      </div>
    </footer>
  </div>
</template>

<style scoped>
.footer-anchor {
  min-height: 84px;
}

.footer {
  padding: 0 16px 20px;
}

.footer--floating {
  position: fixed;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 20;
  pointer-events: none;
}

.footer__content {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  width: min(100%, 1180px);
  min-height: 64px;
  margin: 0 auto;
  padding: 10px 18px;
  border: 1px solid color-mix(in srgb, var(--color-primary) 24%, var(--color-border));
  border-radius: 18px;
  background: color-mix(in srgb, var(--color-surface-card) 86%, transparent);
  box-shadow: 0 10px 28px var(--color-shadow);
  backdrop-filter: blur(12px);
}

.footer--floating .footer__content {
  pointer-events: auto;
}

.footer__flower {
  width: 42px;
  height: 42px;
  margin-right: 12px;
  filter: saturate(82%);
}

.footer__author {
  margin: 0;
  color: var(--color-text-secondary);
  font-size: 14px;
}

.footer__links {
  display: flex;
  gap: 18px;
}

.footer__links a {
  color: var(--color-link);
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
}

.footer__links a:hover {
  text-decoration: underline;
  text-underline-offset: 3px;
}

@media (max-width: 520px) {
  .footer-anchor {
    min-height: 116px;
  }

  .footer__content {
    grid-template-columns: auto 1fr;
  }

  .footer__links {
    grid-column: 1 / -1;
    justify-content: center;
    padding-top: 8px;
    border-top: 1px solid var(--color-border);
  }
}
</style>
