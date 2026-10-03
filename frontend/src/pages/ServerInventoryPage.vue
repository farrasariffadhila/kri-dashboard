<script setup>
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'
import { fetchServers } from '../lib/api.js'
import { riskStore } from '../lib/store.js'
import { useDebouncedQueryText, useListQuery } from '../lib/useListQuery.js'
import { formatNumber, formatPercent } from '../lib/format.js'
import PageHeader from '../components/PageHeader.vue'
import ListPagination from '../components/ListPagination.vue'
import StatusBadge from '../components/StatusBadge.vue'

const { t } = useI18n()

const { query, result, loading, error, update, reset, reload } = useListQuery(fetchServers)
const search = useDebouncedQueryText(query, update, 'q')

const departments = computed(() => [...(riskStore.summary?.data ?? [])].sort((a, b) => a.name.localeCompare(b.name)))
const bank = computed(() => riskStore.summary?.totals.current.unpatched_servers)
const hasFilters = computed(() => Object.keys(query.value).some((k) => k !== 'page'))
</script>

<template>
  <PageHeader :title="t('servers.title')" :breadcrumbs="[{ label: t('common.overview'), to: '/' }, { label: t('servers.title') }]">
    <template v-if="bank">
      {{ t('servers.intro', { total: formatNumber(bank.total_servers), nc: formatNumber(bank.non_compliant), pct: formatPercent(bank.value, 1) }) }}
    </template>
  </PageHeader>

  <form role="search" :aria-label="t('servers.filterAria')" class="flex flex-wrap items-end gap-3" @submit.prevent>
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
      <span class="text-secondary">{{ t('servers.compliance') }}</span>
      <select
        class="rounded-md border border-control bg-surface px-3 py-2"
        :value="query.compliance ?? ''"
        @change="update({ compliance: $event.target.value })"
      >
        <option value="">{{ t('servers.allServers') }}</option>
        <option value="non_compliant">{{ t('servers.nonCompliant') }}</option>
        <option value="compliant">{{ t('servers.compliant') }}</option>
      </select>
    </label>
    <label class="flex flex-col gap-1 text-sm">
      <span class="text-secondary">{{ t('servers.os') }}</span>
      <select
        class="max-w-72 rounded-md border border-control bg-surface px-3 py-2"
        :value="query.os_version ?? ''"
        @change="update({ os_version: $event.target.value })"
      >
        <option value="">{{ t('servers.allOs') }}</option>
        <option v-for="os in result?.facets.os_versions ?? []" :key="os" :value="os">{{ os }}</option>
      </select>
    </label>
    <label class="flex flex-col gap-1 text-sm">
      <span class="text-secondary">{{ t('servers.search') }}</span>
      <input v-model="search" type="search" class="w-60 rounded-md border border-control bg-surface px-3 py-2" autocomplete="off" />
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
    <template v-if="loading && !result">{{ t('servers.loading') }}</template>
    <template v-else-if="result && result.total > 0">
      {{ t('servers.showing', { from: formatNumber(result.from), to: formatNumber(result.to), total: formatNumber(result.total) }) }}
    </template>
  </p>

  <div v-if="error" role="alert" class="mt-3 max-w-2xl border-l-4 border-critical-mark bg-critical-bg px-5 py-4">
    <p class="font-semibold text-critical-fg">{{ t('servers.loadFailed') }}</p>
    <button type="button" class="mt-2 rounded-md border border-control bg-surface px-3 py-1.5 text-sm font-medium" @click="reload">
      {{ t('common.retry') }}
    </button>
  </div>

  <div v-else-if="!result" aria-busy="true" class="mt-3 h-96 animate-pulse rounded-md bg-sunken" />

  <div v-else-if="result.total === 0" class="mt-3 border border-border bg-surface px-6 py-10 text-center">
    <p class="font-medium">{{ t('servers.empty') }}</p>
    <button type="button" class="mt-2 text-sm font-medium text-accent underline underline-offset-4" @click="reset">{{ t('common.clearFilters') }}</button>
  </div>

  <template v-else>
    <div
      class="relative mt-3 overflow-x-auto border-y border-border bg-surface"
      :class="{ 'opacity-60': loading }"
      tabindex="0"
      role="region"
      :aria-label="t('servers.tableAria')"
    >
      <table class="w-full min-w-3xl border-collapse text-sm">
        <thead>
          <tr class="border-b border-border text-left text-xs font-medium text-secondary">
            <th scope="col" class="px-4 py-3">{{ t('servers.hostname') }}</th>
            <th scope="col" class="px-3 py-3">{{ t('servers.ip') }}</th>
            <th scope="col" class="px-3 py-3">{{ t('servers.os') }}</th>
            <th scope="col" class="px-3 py-3">{{ t('audits.department') }}</th>
            <th scope="col" class="px-4 py-3">{{ t('servers.compliance') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="server in result.data" :key="server.id" class="border-b border-hairline">
            <th scope="row" class="px-4 py-2.5 text-left font-medium tabular">{{ server.hostname }}</th>
            <td class="px-3 py-2.5 tabular text-secondary">{{ server.ip_address }}</td>
            <td class="px-3 py-2.5">{{ server.os_version }}</td>
            <td class="px-3 py-2.5 whitespace-nowrap">{{ server.department.name }}</td>
            <td class="px-4 py-2.5">
              <StatusBadge
                size="sm"
                :status="server.is_compliant ? 'safe' : 'critical'"
                :label="server.is_compliant ? t('servers.compliant') : t('servers.nonCompliant')"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <ListPagination :page="result" noun="server" @change="update({ page: String($event) }, { resetPage: false })" />
  </template>
</template>
