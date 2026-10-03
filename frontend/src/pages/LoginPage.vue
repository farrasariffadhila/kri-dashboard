<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '../lib/api.js'
import { authStore, loadDemoAccounts, login, loginAsDemo } from '../lib/auth.js'
import LanguageToggle from '../components/LanguageToggle.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const selectedId = ref(null)
const submitting = ref(false)
const usePassword = ref(false)
// Message key (follows a language switch) or a server message.
const errorKey = ref(route.query.sesi === 'berakhir' ? 'login.sessionExpired' : '')
const serverError = ref('')

onMounted(loadDemoAccounts)

const accounts = computed(() => authStore.demoAccounts ?? [])
const loading = computed(() => authStore.demoAccounts === null)
const demoMode = computed(() => accounts.value.length > 0)
const showDemo = computed(() => demoMode.value && !usePassword.value)
const selected = computed(() => accounts.value.find((a) => a.id === selectedId.value) ?? null)

// Preselect the first account so one click is enough.
watch(accounts, (list) => {
  if (selectedId.value === null && list.length) selectedId.value = list[0].id
}, { immediate: true })

function describe(user) {
  return user.department ? `${t(`roles.${user.role}`)} · ${user.department.name}` : t(`roles.${user.role}`)
}

function goNext() {
  const next = typeof route.query.lanjut === 'string' && route.query.lanjut.startsWith('/') ? route.query.lanjut : '/'
  router.replace(next)
}

async function submit() {
  if (submitting.value) return
  submitting.value = true
  errorKey.value = ''
  serverError.value = ''
  try {
    if (showDemo.value) await loginAsDemo(selectedId.value)
    else await login(email.value.trim(), password.value)
    goNext()
  } catch (e) {
    if (!showDemo.value && e instanceof ApiError && (e.status === 422 || e.status === 429)) serverError.value = e.message
    else errorKey.value = showDemo.value ? 'demo.failed' : 'login.unreachable'
    password.value = ''
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
    <!-- Brand panel -->
    <aside class="flex flex-col justify-between bg-sidebar px-6 py-6 text-sidebar-text sm:px-10 lg:py-10" aria-hidden="true">
      <div class="flex items-center gap-3">
        <svg class="size-8" viewBox="0 0 32 32">
          <rect width="32" height="32" rx="4" fill="var(--ui-sidebar-active)" />
          <rect x="7" y="18" width="4" height="7" fill="#9fd9b0" />
          <rect x="14" y="13" width="4" height="12" fill="#f3cf78" />
          <rect x="21" y="7" width="4" height="18" fill="#ffb7b0" />
        </svg>
        <span class="font-serif text-lg font-semibold">{{ t('app.name') }}</span>
      </div>
      <div class="hidden max-w-md lg:block">
        <p class="font-serif text-4xl font-semibold leading-tight">{{ t('app.title') }}</p>
        <p class="mt-4 text-lg text-sidebar-muted">{{ t('login.panel') }}</p>
      </div>
      <p class="hidden text-sm text-sidebar-muted lg:block">{{ t('app.tagline') }}</p>
    </aside>

    <!-- Sign-in -->
    <main id="konten" class="relative flex items-center justify-center px-6 py-16 sm:px-10">
      <LanguageToggle class="absolute right-6 top-6 sm:right-10" />

      <form class="w-full max-w-sm" novalidate @submit.prevent="submit">
        <p v-if="demoMode" class="text-xs font-medium uppercase tracking-wide text-muted">{{ t('demo.badge') }}</p>
        <h1 id="page-title" tabindex="-1" class="mt-2 font-serif text-3xl font-semibold focus:outline-none">{{ t('login.title') }}</h1>
        <p class="mt-2 text-secondary">{{ showDemo ? t('demo.subtitle') : t('login.subtitle') }}</p>

        <div v-if="errorKey || serverError" role="alert" class="mt-6 border-l-4 border-critical-mark bg-critical-bg px-4 py-3 text-sm text-critical-fg">
          {{ serverError || t(errorKey) }}
        </div>

        <p v-if="loading" class="mt-8 text-secondary" role="status">{{ t('demo.loadingAccounts') }}</p>

        <!-- Demo accounts: a single radio list -->
        <fieldset v-else-if="showDemo" class="mt-8">
          <legend class="sr-only">{{ t('demo.accounts') }}</legend>
          <div class="divide-y divide-hairline border-y border-border">
            <label
              v-for="user in accounts"
              :key="user.id"
              class="flex cursor-pointer items-center gap-3 py-3 pl-3 pr-2 has-checked:bg-accent-subtle"
            >
              <input v-model="selectedId" type="radio" name="account" :value="user.id" class="size-4 shrink-0 accent-accent" />
              <span class="min-w-0">
                <span class="block font-medium">{{ user.name }}</span>
                <span class="block truncate text-sm text-muted">{{ describe(user) }}</span>
              </span>
            </label>
          </div>
        </fieldset>

        <!-- Email and password -->
        <div v-else class="mt-8 space-y-4">
          <div>
            <label class="block text-sm font-medium" for="email">{{ t('login.email') }}</label>
            <input id="email" v-model="email" type="email" autocomplete="username" required class="mt-1 w-full rounded-md border border-control bg-surface px-3 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium" for="password">{{ t('login.password') }}</label>
            <input
              id="password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
              class="mt-1 w-full rounded-md border border-control bg-surface px-3 py-2"
            />
          </div>
        </div>

        <button
          v-if="!loading"
          type="submit"
          class="mt-6 w-full rounded-md bg-accent px-4 py-2.5 font-medium text-on-accent hover:bg-accent-hover disabled:opacity-60"
          :disabled="submitting || (showDemo ? !selected : !email || !password)"
        >
          <template v-if="submitting">{{ t('common.processing') }}</template>
          <template v-else-if="showDemo && selected">{{ t('demo.submit', { name: selected.name.split(' ')[0] }) }}</template>
          <template v-else>{{ t('login.submit') }}</template>
        </button>

        <button
          v-if="demoMode"
          type="button"
          class="mt-4 py-1 text-sm text-secondary underline underline-offset-4 hover:text-ink"
          @click="usePassword = !usePassword; errorKey = ''; serverError = ''"
        >
          {{ usePassword ? t('demo.useDemo') : t('demo.usePassword') }}
        </button>
      </form>
    </main>
  </div>
</template>
