<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { fetchDisputes } from '../lib/api.js'
import { authStore } from '../lib/auth.js'
import { disputeStatus } from '../lib/disputes.js'
import PageHeader from '../components/PageHeader.vue'
import DisputeTable from '../components/DisputeTable.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const STATUSES = ['pending', 'accepted', 'rejected']
const status = computed(() => (STATUSES.includes(route.query.status) ? route.query.status : ''))
const data = ref(null)
const error = ref(false)

async function load() {
  error.value = false
  try {
    data.value = await fetchDisputes(status.value ? { status: status.value } : {})
  } catch {
    error.value = true
  }
}
watch(status, load, { immediate: true })

const isOwner = computed(() => authStore.user?.role === 'unit_owner')

function setStatus(value) {
  router.replace({ query: value ? { status: value } : {} })
}
</script>

<template>
  <PageHeader :title="t('dispute.title')" :breadcrumbs="[{ label: t('common.overview'), to: '/' }, { label: t('dispute.title') }]">
    <template #aside>
      <RouterLink
        v-if="isOwner"
        :to="{ name: 'dispute-new' }"
        class="rounded-md bg-accent px-4 py-2.5 text-sm font-medium text-on-accent hover:bg-accent-hover"
      >
        {{ t('dispute.new') }}
      </RouterLink>
    </template>
  </PageHeader>

  <label class="mb-4 flex w-fit flex-col gap-1 text-sm">
    <span class="text-secondary">{{ t('dispute.filterStatus') }}</span>
    <select class="rounded-md border border-control bg-surface px-3 py-2" :value="status" @change="setStatus($event.target.value)">
      <option value="">{{ t('dispute.all') }}</option>
      <option v-for="s in STATUSES" :key="s" :value="s">{{ disputeStatus(s).label }}</option>
    </select>
  </label>

  <div v-if="error" role="alert" class="max-w-2xl border-l-4 border-critical-mark bg-critical-bg px-5 py-4">
    <p class="font-semibold text-critical-fg">{{ t('dispute.loadFailed') }}</p>
    <button type="button" class="mt-2 rounded-md border border-control bg-surface px-3 py-1.5 text-sm font-medium" @click="load">
      {{ t('common.retry') }}
    </button>
  </div>

  <div v-else-if="!data" aria-busy="true" class="h-48 animate-pulse rounded-md bg-sunken">
    <span class="sr-only" role="status">{{ t('common.loading') }}</span>
  </div>

  <p v-else-if="!data.data.length" class="border border-border bg-surface px-6 py-10 text-center text-secondary">{{ t('dispute.empty') }}</p>

  <DisputeTable v-else :disputes="data.data" :label="t('dispute.tableAria')" />
</template>
