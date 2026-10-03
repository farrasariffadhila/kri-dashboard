<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { authStore, refreshWorkCounts } from '../lib/auth.js'
import { loadSummary, resetRiskStore, riskStore } from '../lib/store.js'
import PageHeader from '../components/PageHeader.vue'
import DisputeForm from '../components/DisputeForm.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

onMounted(() => loadSummary())

// A unit owner always disputes their own department (the API enforces the same).
const department = computed(() => riskStore.summary?.data.find((d) => d.id === authStore.user?.department?.id) ?? null)

async function onSubmitted(id) {
  // The department now carries an open dispute flag: refresh cached KRIs and the badge.
  resetRiskStore()
  loadSummary()
  refreshWorkCounts()
  router.push({ name: 'dispute-detail', params: { id }, query: { terkirim: '1' } })
}
</script>

<template>
  <PageHeader
    :title="t('dispute.new')"
    :breadcrumbs="[{ label: t('common.overview'), to: '/' }, { label: t('dispute.title'), to: '/sanggahan' }, { label: t('dispute.new') }]"
  >
    {{ authStore.user?.department?.name }}
  </PageHeader>

  <div v-if="riskStore.error" role="alert" class="max-w-2xl border-l-4 border-critical-mark bg-critical-bg px-5 py-4">
    <p class="font-semibold text-critical-fg">{{ t('common.loadFailed') }}</p>
  </div>
  <div v-else-if="!department" aria-busy="true" class="h-72 max-w-2xl animate-pulse rounded-md bg-sunken">
    <span class="sr-only" role="status">{{ t('common.loading') }}</span>
  </div>
  <DisputeForm v-else :department="department" :initial-kri="typeof route.query.kri === 'string' ? route.query.kri : null" @submitted="onSubmitted" />
</template>
