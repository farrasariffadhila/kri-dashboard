<script setup>
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { authStore, logout } from '../lib/auth.js'
import { riskStore } from '../lib/store.js'
import { roleLabel, statusFromRating } from '../lib/kri.js'
import { formatDate, formatMonthLong, formatNumber, formatScore } from '../lib/format.js'
import AppIcon from './AppIcon.vue'
import StatusBadge from './StatusBadge.vue'

const { t } = useI18n()

const emit = defineEmits(['navigate'])

const router = useRouter()
const summary = computed(() => riskStore.summary)
const user = computed(() => authStore.user)
const pending = computed(() => authStore.workCounts.action_needed)

async function signOut() {
  emit('navigate')
  await logout()
  router.replace({ name: 'login' })
}
const openHigh = computed(() => summary.value?.totals.current.open_high_audits.value)

const linkClass =
  'relative flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover'
const activeClass =
  'bg-sidebar-active font-medium before:absolute before:inset-y-1.5 before:-left-3 before:w-1 before:rounded-r-sm before:bg-sidebar-indicator'
</script>

<template>
  <div class="sidebar-scope flex h-full flex-col bg-sidebar text-sidebar-text">
    <div class="flex items-center gap-3 px-6 pb-6 pt-7">
      <svg class="size-8 shrink-0" viewBox="0 0 32 32" aria-hidden="true">
        <rect width="32" height="32" rx="4" fill="var(--ui-sidebar-active)" />
        <rect x="7" y="18" width="4" height="7" fill="#9fd9b0" />
        <rect x="14" y="13" width="4" height="12" fill="#f3cf78" />
        <rect x="21" y="7" width="4" height="18" fill="#ffb7b0" />
      </svg>
      <div>
        <p class="font-serif text-lg font-semibold leading-tight">{{ t('app.name') }}</p>
        <p class="text-xs text-sidebar-muted">{{ t('app.tagline') }}</p>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3" :aria-label="t('nav.main')">
      <ul class="space-y-1">
        <li>
          <RouterLink to="/" :class="linkClass" :exact-active-class="activeClass" @click="emit('navigate')">
            <AppIcon name="overview" /> {{ t('nav.overview') }}
          </RouterLink>
        </li>
        <li v-if="user?.has_work_queue">
          <RouterLink :to="{ name: 'work-queue' }" :class="linkClass" :active-class="activeClass" @click="emit('navigate')">
            <AppIcon name="inbox" />
            <span class="flex-1">{{ t('nav.workQueue') }}</span>
            <span
              v-if="pending > 0"
              class="rounded-full bg-sidebar-indicator px-2 py-0.5 text-xs font-semibold tabular text-sidebar"
              :aria-label="t('nav.pendingAria', { count: formatNumber(pending) })"
            >
              {{ formatNumber(pending) }}
            </span>
          </RouterLink>
        </li>
        <li class="pt-4">
          <p id="nav-departments" class="flex items-center gap-3 px-3 pb-1 text-xs font-medium uppercase tracking-wide text-sidebar-muted">
            <AppIcon name="building" /> {{ t('nav.departments') }}
          </p>
          <ul v-if="summary" class="space-y-0.5" aria-labelledby="nav-departments">
            <li v-for="dept in summary.data" :key="dept.id">
              <RouterLink
                :to="{ name: 'department', params: { id: dept.id } }"
                :class="[linkClass, 'pl-11']"
                :active-class="activeClass"
                @click="emit('navigate')"
              >
                <span class="flex-1 truncate">{{ dept.name }}</span>
                <span class="text-xs tabular text-sidebar-muted">{{ formatScore(dept.composite_score) }}</span>
                <StatusBadge size="sm" icon-only :status="statusFromRating(dept.rating)" />
              </RouterLink>
            </li>
          </ul>
        </li>
        <li class="pt-4">
          <RouterLink :to="{ name: 'audits' }" :class="linkClass" :active-class="activeClass" @click="emit('navigate')">
            <AppIcon name="clipboard" />
            <span class="flex-1">{{ t('nav.audits') }}</span>
            <span
              v-if="openHigh != null"
              class="rounded-full bg-sidebar-hover px-2 py-0.5 text-xs tabular"
              :aria-label="t('nav.openHighAria', { count: formatNumber(openHigh) })"
            >
              {{ formatNumber(openHigh) }}
            </span>
          </RouterLink>
        </li>
        <li>
          <RouterLink :to="{ name: 'servers' }" :class="linkClass" :active-class="activeClass" @click="emit('navigate')">
            <AppIcon name="server" /> {{ t('nav.servers') }}
          </RouterLink>
        </li>
        <li>
          <RouterLink :to="{ name: 'methodology' }" :class="linkClass" :active-class="activeClass" @click="emit('navigate')">
            <AppIcon name="book" /> {{ t('nav.methodology') }}
          </RouterLink>
        </li>
      </ul>
    </nav>

    <div v-if="summary" class="border-t border-sidebar-hover px-6 py-4 text-xs text-sidebar-muted">
      <p class="font-medium text-sidebar-text">{{ t('nav.periodLabel', { month: formatMonthLong(summary.reporting_month) }) }}</p>
      <p>{{ t('nav.dataAsOf', { date: formatDate(summary.as_of) }) }}</p>
    </div>

    <div v-if="user" class="flex items-center gap-3 border-t border-sidebar-hover px-6 py-4">
      <div class="min-w-0 flex-1 text-sm">
        <p class="truncate font-medium">{{ user.name }}</p>
        <p class="truncate text-xs text-sidebar-muted">
          {{ roleLabel(user.role) }}{{ user.department ? ` · ${user.department.name}` : '' }}
        </p>
      </div>
      <button type="button" class="rounded-md px-2.5 py-1.5 text-xs font-medium hover:bg-sidebar-hover" @click="signOut">
        {{ t('nav.signOut') }}
      </button>
    </div>
  </div>
</template>
