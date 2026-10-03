<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { fetchWorkQueue } from '../lib/api.js'
import { authStore } from '../lib/auth.js'
import { RISK_LEVELS } from '../lib/kri.js'
import { REMEDIATION_STATUS, TONE_CLASSES } from '../lib/workflow.js'
import { formatDate, formatNumber } from '../lib/format.js'
import PageHeader from '../components/PageHeader.vue'
import DisputeTable from '../components/DisputeTable.vue'

const { t } = useI18n()

const data = ref(null)
const error = ref(false)

async function load() {
  // Re-mounted for a persona without a queue (or after sign-out) just before the redirect: skip the request.
  if (!authStore.user?.has_work_queue) return
  error.value = false
  try {
    data.value = await fetchWorkQueue()
    authStore.workCounts = data.value.counts
  } catch {
    error.value = true
  }
}
onMounted(load)

const role = computed(() => authStore.user?.role)
const intro = computed(() => {
  if (role.value === 'unit_owner') return t('workQueue.introOwner', { department: authStore.user?.department?.name ?? '' })
  if (role.value === 'risk_officer') return t('workQueue.introRiskOfficer')
  return t('workQueue.introAuditor')
})

const sections = computed(() => {
  if (!data.value) return []
  // IT Risk only decides disputes; the audit sections would always be empty for them.
  if (role.value === 'risk_officer') return [{ key: 'disputes', kind: 'disputes', title: t('workQueue.disputesToReview'), items: data.value.disputes }]

  const list = [
    {
      key: 'action',
      kind: 'issues',
      title: role.value === 'auditor' ? t('workQueue.awaitingYourReview') : t('workQueue.actionNeeded'),
      items: data.value.action_needed,
    },
  ]
  if (role.value === 'unit_owner') {
    list.push({ key: 'waiting', kind: 'issues', title: t('workQueue.waitingAuditor'), items: data.value.waiting })
    if (data.value.disputes.length) list.push({ key: 'disputes', kind: 'disputes', title: t('workQueue.disputesWaiting'), items: data.value.disputes })
  }
  return list
})
</script>

<template>
  <PageHeader :title="t('workQueue.title')" :breadcrumbs="[{ label: t('common.overview'), to: '/' }, { label: t('workQueue.title') }]">
    {{ intro }}
  </PageHeader>

  <div v-if="error" role="alert" class="max-w-2xl border-l-4 border-critical-mark bg-critical-bg px-5 py-4">
    <p class="font-semibold text-critical-fg">{{ t('workQueue.loadFailed') }}</p>
    <button type="button" class="mt-2 rounded-md border border-control bg-surface px-3 py-1.5 text-sm font-medium" @click="load">
      {{ t('common.retry') }}
    </button>
  </div>

  <div v-else-if="!data" aria-busy="true" class="h-72 animate-pulse rounded-md bg-sunken">
    <span class="sr-only" role="status">{{ t('common.loading') }}</span>
  </div>

  <div v-else class="space-y-10">
    <section v-for="section in sections" :key="section.key" :aria-labelledby="`wq-${section.key}`">
      <h2 :id="`wq-${section.key}`" class="font-serif text-xl font-semibold">
        {{ section.title }} <span class="text-muted tabular">({{ formatNumber(section.items.length) }})</span>
      </h2>
      <p v-if="!section.items.length" class="mt-3 border border-border bg-surface px-6 py-8 text-center text-secondary">
        {{ t('workQueue.empty') }}
      </p>
      <DisputeTable v-else-if="section.kind === 'disputes'" class="mt-3" :disputes="section.items" :label="section.title" :show-status="false" />
      <div v-else class="relative mt-3 overflow-x-auto border-y border-border bg-surface" tabindex="0" role="region" :aria-label="section.title">
        <table class="w-full min-w-3xl border-collapse text-sm">
          <thead>
            <tr class="border-b border-border text-left text-xs font-medium text-secondary">
              <th scope="col" class="px-4 py-3">{{ t('audits.finding') }}</th>
              <th scope="col" class="px-3 py-3">{{ t('audits.department') }}</th>
              <th scope="col" class="px-3 py-3">{{ t('audits.risk') }}</th>
              <th scope="col" class="px-3 py-3">{{ t('workQueue.stage') }}</th>
              <th scope="col" class="px-4 py-3 text-right">{{ t('audits.due') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in section.items" :key="item.id" class="border-b border-hairline align-top">
              <th scope="row" class="px-4 py-3 text-left font-normal">
                <RouterLink :to="{ name: 'audit-detail', params: { id: item.id } }" class="text-accent hover:underline">{{ item.title }}</RouterLink>
              </th>
              <td class="px-3 py-3 whitespace-nowrap">{{ item.department.name }}</td>
              <td class="px-3 py-3">
                <span class="rounded-md px-2 py-0.5 text-xs font-medium" :class="TONE_CLASSES[RISK_LEVELS[item.risk_level].tone ?? 'neutral']">
                  {{ RISK_LEVELS[item.risk_level].label }}
                </span>
              </td>
              <td class="px-3 py-3">
                <span class="rounded-md px-2 py-0.5 text-xs" :class="TONE_CLASSES[REMEDIATION_STATUS[item.remediation_status ?? 'none'].tone]">
                  {{ REMEDIATION_STATUS[item.remediation_status ?? 'none'].label }}
                </span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap tabular">
                {{ formatDate(item.due_date, 'short') }}
                <span v-if="item.days_overdue > 0" class="block text-xs font-medium text-critical-fg">
                  {{ t('workQueue.overdue', { days: formatNumber(item.days_overdue) }) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
