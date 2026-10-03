<script setup>
import { useI18n } from 'vue-i18n'
import { computed, nextTick, provide, ref, watch } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { loadSummary, riskStore } from './lib/store.js'
import { authStore } from './lib/auth.js'
import { formatMonthLong } from './lib/format.js'
import { useTheme } from './lib/theme.js'
import AppIcon from './components/AppIcon.vue'
import AppSidebar from './components/AppSidebar.vue'
import ThemeToggle from './components/ThemeToggle.vue'
import LanguageToggle from './components/LanguageToggle.vue'
import PersonaSwitcher from './components/PersonaSwitcher.vue'
import { updateDocumentTitle } from './router.js'

const { mode: themeMode, version: themeVersion, setMode: setTheme } = useTheme()
provide('themeVersion', themeVersion)

const route = useRoute()
const drawer = ref(null)
const menuButton = ref(null)

// Risk data is only fetched for a signed-in user (and again after signing in as someone else).
watch(
  () => authStore.user?.id,
  (id) => {
    if (id) loadSummary()
  },
  { immediate: true },
)

// When the drawer closes because of navigation, focus goes to the new page title instead of the menu button.
let closingForNavigation = false

function openDrawer() {
  drawer.value.showModal()
}
function closeDrawer() {
  if (drawer.value?.open) drawer.value.close()
}
function closeForNavigation() {
  closingForNavigation = true
  closeDrawer()
}
function onDrawerClose() {
  if (!closingForNavigation) menuButton.value?.focus()
  closingForNavigation = false
}
function onDrawerClick(event) {
  if (event.target === drawer.value) closeDrawer()
}

// Real page changes (not filter changes) move focus to the new page title, so screen readers announce it.
watch(
  () => route.path,
  async (to, from) => {
    if (drawer.value?.open) closeForNavigation()
    if (from === undefined) return
    await nextTick()
    document.getElementById('page-title')?.focus()
  },
)

const period = computed(() => (riskStore.summary ? formatMonthLong(riskStore.summary.reporting_month) : null))

// Keep the tab title in the chosen language.
const { t, locale } = useI18n()
watch(locale, () => updateDocumentTitle())
</script>

<template>
  <RouterView v-if="route.meta.layout === 'bare'" />

  <template v-else>
  <a
    href="#konten"
    class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-4 focus:py-2 focus:text-accent"
  >
    {{ t('app.skipToContent') }}
  </a>

  <div class="min-h-screen lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">
    <!-- Desktop: persistent sidebar -->
    <aside class="hidden lg:block">
      <div class="sticky top-0 h-screen">
        <AppSidebar />
      </div>
    </aside>

    <!-- Mobile and tablet: the same navigation in a modal drawer -->
    <dialog
      ref="drawer"
      :aria-label="t('app.navMenu')"
      class="m-0 h-full max-h-none w-72 max-w-[85vw] border-0 p-0 backdrop:bg-(--ui-backdrop) lg:hidden"
      @click="onDrawerClick"
      @close="onDrawerClose"
    >
      <div class="relative h-full">
        <button
          type="button"
          class="absolute right-3 top-3 z-10 rounded-md p-2 text-sidebar-text hover:bg-sidebar-hover"
          @click="closeDrawer"
        >
          <AppIcon name="close" />
          <span class="sr-only">{{ t('app.closeMenu') }}</span>
        </button>
        <AppSidebar @navigate="closeForNavigation" />
      </div>
    </dialog>

    <div class="min-w-0">
      <header class="sticky top-0 z-20 flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-border bg-page px-4 py-3 sm:px-8">
        <button
          ref="menuButton"
          type="button"
          class="inline-flex items-center gap-2 rounded-md border border-control px-3 py-1.5 text-sm font-medium hover:bg-sunken lg:hidden"
          @click="openDrawer"
        >
          <AppIcon name="menu" /> <span class="max-[359px]:sr-only">{{ t('app.menu') }}</span>
        </button>
        <p v-if="period" class="hidden text-sm text-secondary xl:block">
          {{ t('app.topbar', { period }) }}
        </p>
        <!-- Phones: the persona picker wraps to its own full-width row. -->
        <PersonaSwitcher class="order-last w-full sm:order-none sm:ml-auto sm:w-auto" />
        <div class="ml-auto flex items-center gap-2 sm:ml-0">
          <LanguageToggle />
          <ThemeToggle :mode="themeMode" @change="setTheme" />
        </div>
      </header>

      <main id="konten" tabindex="-1" class="px-4 pb-16 pt-8 focus:outline-none sm:px-8">
        <div class="mx-auto max-w-360">
          <div v-if="riskStore.error" role="alert" class="max-w-2xl border-l-4 border-critical-mark bg-critical-bg px-5 py-4">
            <p class="font-semibold text-critical-fg">{{ t('common.loadFailed') }}</p>
            <p class="mt-1 text-secondary">{{ t('common.checkApi') }}</p>
            <button
              type="button"
              class="mt-3 rounded-md bg-accent px-4 py-2 text-sm font-medium text-on-accent hover:bg-accent-hover"
              @click="loadSummary({ force: true })"
            >
              {{ t('common.retry') }}
            </button>
          </div>
          <RouterView v-else :key="authStore.user?.id" />
        </div>
      </main>
    </div>
  </div>
  </template>
</template>
