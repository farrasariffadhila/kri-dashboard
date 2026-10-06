<script setup>
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'
import { fetchAuditIssues } from '../lib/api.js'
import { riskStore } from '../lib/store.js'
import { useDebouncedQueryText, useListQuery } from '../lib/useListQuery.js'
import { RISK_LEVELS, auditStatusLabel } from '../lib/kri.js'
import { TONE_CLASSES, remediationInfo } from '../lib/workflow.js'
import { RouterLink } from 'vue-router'
import { formatDate, formatNumber } from '../lib/format.js'
import PageHeader from '../components/PageHeader.vue'
import ListPagination from '../components/ListPagination.vue'

const { t } = useI18n()

const { query, result, loading, error, update, reset, reload } = useListQuery(fetchAuditIssues, {
  status: 'Open',
  sort: 'due_date',
})
const search = useDebouncedQueryText(query, update, 'q')

const departments = computed(() => [...(riskStore.summary?.data ?? [])].sort((a, b) => a.name.localeCompare(b.name)))
const hasFilters = computed(() => Object.keys(query.value).some((k) => !['status', 'sort', 'page'].includes(k)) || query.value.status !== 'Open')

const SORTABLE = { due_date: 'audits.due', identified_date: 'audits.identified' }
function sortState(column) {
  if (query.value.sort === column) return 'ascending'
  if (query.value.sort === `-${column}`) return 'descending'
  return 'none'
}
function toggleSort(column) {
  update({ sort: query.value.sort === column ? `-${column}` : column })
}

function statusText(issue) {
  if (issue.status === 'Closed') return t('audits.closedOn', { date: formatDate(issue.closed_date, 'short') })
  return issue.days_overdue > 0 ? t('audits.openOverdue', { days: formatNumber(issue.days_overdue) }) : t('audits.openNotDue')
}
</script>

<template>
  <PageHeader :title="t('audits.title')" :breadcrumbs="[{ label: t('common.overview'), to: '/' }, { label: t('audits.title') }]" />

  <form role="search" :aria-label="t('audits.filterAria')" class="flex flex-wrap items-end gap-3" @submit.prevent>
    <label class="flex flex-col gap-1 text-sm">
      <span class="text-secondary">{{ t('audits.department') }}</span>
      <select
        class="rounded-md border border-control bg-surface px-3 py-2"
        :value="query.department_id ?? ''"
        @change="update({ department_id: $event.target.value })"
      >
        <option value="">{{ t('audits.allDepartments') }}</option>
        <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
      </select>
    </label>
    <label class="flex flex-col gap-1 text-sm">
      <span class="text-secondary">{{ t('audits.riskLevel') }}</span>
      <select
        class="rounded-md border border-control bg-surface px-3 py-2"
        :value="query.risk_level ?? ''"
        @change="update({ risk_level: $event.target.value })"
      >
        <option value="">{{ t('audits.allLevels') }}</option>
        <option v-for="(level, key) in RISK_LEVELS" :key="key" :value="key">{{ level.label }}</option>
      </select>
    </label>
    <label class="flex flex-col gap-1 text-sm">
      <span class="text-secondary">{{ t('audits.status') }}</span>
      <select class="rounded-md border border-control bg-surface px-3 py-2" :value="query.status" @change="update({ status: $event.target.value })">
        <option value="all">{{ t('audits.allStatuses') }}</option>
        <option v-for="key in ['Open', 'Closed']" :key="key" :value="key">{{ auditStatusLabel(key) }}</option>
      </select>
    </label>
    <label class="flex flex-col gap-1 text-sm">
      <span class="text-secondary">{{ t('audits.search') }}</span>
      <input v-model="search" type="search" class="w-64 rounded-md border border-control bg-surface px-3 py-2" autocomplete="off" />
    </label>
    <label class="flex items-center gap-2 py-2 text-sm">
      <input
        type="checkbox"
        class="size-4 accent-(--ui-accent)"
        :checked="query.overdue === '1'"
        @change="update({ overdue: $event.target.checked ? '1' : '' })"
      />
      {{ t('audits.overdueOnly') }}
    </label>
    <button
      v-if="hasFilters"
      type="button"
      class="rounded-md px-3 py-2 text-sm font-medium text-accent underline underline-offset-4 hover:text-accent-hover"
      @click="reset"
    >
      {{ t('common.clearFilters') }}
    </button>
  </form>

  <p class="mt-6 text-sm text-secondary" aria-live="polite">
    <template v-if="loading && !result">{{ t('audits.loading') }}</template>
    <template v-else-if="result && result.total > 0">
      {{ t('audits.showing', { from: formatNumber(result.from), to: formatNumber(result.to), total: formatNumber(result.total) }) }}
    </template>
  </p>

  <div v-if="error" role="alert" class="mt-3 max-w-2xl border-l-4 border-critical-mark bg-critical-bg px-5 py-4">
    <p class="font-semibold text-critical-fg">{{ t('audits.loadFailed') }}</p>
    <button type="button" class="mt-2 rounded-md border border-control bg-surface px-3 py-1.5 text-sm font-medium" @click="reload">
      {{ t('common.retry') }}
    </button>
  </div>

  <div v-else-if="!result" aria-busy="true" class="mt-3 h-96 animate-pulse rounded-md bg-sunken" />

  <div v-else-if="result.total === 0" class="mt-3 border border-border bg-surface px-6 py-10 text-center">
    <p class="font-medium">{{ t('audits.empty') }}</p>
    <button type="button" class="mt-2 text-sm font-medium text-accent underline underline-offset-4" @click="reset">{{ t('common.clearFilters') }}</button>
  </div>

  <template v-else>
    <div
      class="relative mt-3 overflow-x-auto border-y border-border bg-surface"
      :class="{ 'opacity-60': loading }"
      tabindex="0"
      role="region"
      :aria-label="t('audits.tableAria')"
    >
      <table class="w-full min-w-240 border-collapse text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs font-medium text-secondary">
            <th scope="col" class="px-4 py-3">{{ t('audits.finding') }}</th>
            <th scope="col" class="px-3 py-3">{{ t('audits.department') }}</th>
            <th scope="col" class="px-3 py-3">{{ t('audits.risk') }}</th>
            <th scope="col" class="px-3 py-3">{{ t('audits.auditor') }}</th>
            <th v-for="(label, column) in SORTABLE" :key="column" scope="col" class="px-3 py-3 text-right" :aria-sort="sortState(column)">
              <button type="button" class="inline-flex items-center gap-1 font-medium hover:text-ink" @click="toggleSort(column)">
                {{ t(label) }}
                <span aria-hidden="true">{{ sortState(column) === 'ascending' ? '↑' : sortState(column) === 'descending' ? '↓' : '↕' }}</span>
              </button>
            </th>
            <th scope="col" class="px-4 py-3">{{ t('audits.status') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="issue in result.data" :key="issue.id" class="border-b border-hairline align-top">
            <th scope="row" class="px-4 py-3 text-left font-normal">
              <RouterLink :to="{ name: 'audit-detail', params: { id: issue.id } }" class="text-accent hover:underline">{{ issue.title }}</RouterLink>
              <span class="mt-1 block">
                <span class="rounded-md px-1.5 py-0.5 text-xs" :class="TONE_CLASSES[remediationInfo(issue).tone]">{{ remediationInfo(issue).label }}</span>
              </span>
            </th>
            <td class="px-3 py-3 whitespace-nowrap">{{ issue.department.name }}</td>
            <td class="px-3 py-3">
              <span
                class="inline-block rounded-md px-2 py-0.5 text-xs font-medium"
                :class="{
                  'bg-critical-bg text-critical-fg': RISK_LEVELS[issue.risk_level].tone === 'critical',
                  'bg-warning-bg text-warning-fg': RISK_LEVELS[issue.risk_level].tone === 'warning',
                  'bg-sunken text-secondary': !RISK_LEVELS[issue.risk_level].tone,
                }"
              >
                {{ RISK_LEVELS[issue.risk_level].label }}
              </span>
            </td>
            <td class="px-3 py-3 whitespace-nowrap text-secondary">{{ issue.auditor }}</td>
            <td class="px-3 py-3 text-right whitespace-nowrap tabular">{{ formatDate(issue.due_date, 'short') }}</td>
            <td class="px-3 py-3 text-right whitespace-nowrap tabular">{{ formatDate(issue.identified_date, 'short') }}</td>
            <td
              class="px-4 py-3 whitespace-nowrap"
              :class="issue.days_overdue > 0 ? 'font-medium text-critical-fg' : 'text-secondary'"
            >
              {{ statusText(issue) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <ListPagination :page="result" noun="temuan" @change="update({ page: String($event) }, { resetPage: false })" />
  </template>
</template>
