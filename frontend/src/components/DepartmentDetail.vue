<script setup>
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { contributions, statusFromScore } from '../lib/kri.js'
import { formatDate, formatDuration, formatNumber, formatPercent, formatScore } from '../lib/format.js'
import AppIcon from './AppIcon.vue'
import StatusBadge from './StatusBadge.vue'
import { localized } from '../i18n/index.js'

const { t } = useI18n()

/** Why a department scores what it does. Used inside the drill-down dialog and on the department page. */
const props = defineProps({
  department: { type: Object, required: true },
  methodology: { type: Object, required: true },
  asOf: { type: String, required: true },
  headingLevel: { type: Number, default: 2 },
  sources: { type: Object, default: null }, // summary.data_sources
})

const heading = computed(() => `h${props.headingLevel}`)
const idPrefix = computed(() => `dept-${props.department.id}`)
const ranked = computed(() => contributions(props.department.kris))
const audits = computed(() => props.department.kris.open_high_audits)
const servers = computed(() => props.department.kris.unpatched_servers)
const uptime = computed(() => props.department.kris.uptime)
const phishing = computed(() => props.department.kris.phishing_failure)

/** "≥ 10,0%" for KRIs that worsen upwards, "≤ 99,90%" for uptime, which worsens downwards. */
function thresholdText(kri, anchor) {
  const t = props.methodology.thresholds[kri.key]
  const descending = t.max < t.appetite
  const value = kri.format(t[anchor])
  if (anchor === 'tolerance') return `${descending ? '≤' : '≥'} ${value}`
  return `${descending ? '<' : '>'} ${value}`
}

function overdueText(days) {
  return days > 0 ? t('detail.overdueDays', { days: formatNumber(days) }) : t('detail.notDue')
}
</script>

<template>
  <div class="space-y-10">
    <section :aria-labelledby="`${idPrefix}-why`">
      <component :is="heading" :id="`${idPrefix}-why`" class="font-serif text-xl font-semibold">
        {{ t('detail.why', { score: formatScore(department.composite_score) }) }}
      </component>
      <p class="mt-1 max-w-prose text-secondary">{{ t('detail.whyText') }}</p>
      <div class="relative mt-4 overflow-x-auto" tabindex="0" role="region" :aria-label="t('detail.tableAria')">
        <table class="w-full min-w-[44rem] border-collapse text-sm">
          <thead>
            <tr class="border-b border-border text-left text-xs font-medium text-secondary">
              <th scope="col" class="py-2 pr-4">{{ t('detail.indicator') }}</th>
              <th scope="col" class="px-3 py-2 text-right">{{ t('detail.value') }}</th>
              <th scope="col" class="px-3 py-2 text-right">{{ t('detail.warningFrom') }}</th>
              <th scope="col" class="px-3 py-2 text-right">{{ t('detail.criticalFrom') }}</th>
              <th scope="col" class="px-3 py-2">{{ t('detail.status') }}</th>
              <th scope="col" class="px-3 py-2 text-right">{{ t('detail.score') }}</th>
              <th scope="col" class="px-3 py-2 text-right">{{ t('detail.weight') }}</th>
              <th scope="col" class="py-2 pl-3 text-right">{{ t('detail.contribution') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="kri in ranked" :key="kri.key" class="border-b border-hairline">
              <th scope="row" class="py-3 pr-4 text-left font-medium">
                {{ kri.label }}
                <span class="block text-xs font-normal text-muted">{{ kri.description }}</span>
                <span v-if="sources?.[kri.key]" class="block text-xs font-normal text-muted">
                  {{ t('common.source', { system: localized(sources[kri.key].system) }) }}{{ sources[kri.key].simulated ? ` ${t('common.simulated')}` : '' }}
                </span>
              </th>
              <td class="px-3 py-3 text-right font-semibold tabular">{{ kri.format(kri.value) }}</td>
              <td class="px-3 py-3 text-right whitespace-nowrap text-secondary tabular">{{ thresholdText(kri, 'tolerance') }}</td>
              <td class="px-3 py-3 text-right whitespace-nowrap text-secondary tabular">{{ thresholdText(kri, 'limit') }}</td>
              <td class="px-3 py-3"><StatusBadge size="sm" :status="statusFromScore(kri.score, methodology.bands)" /></td>
              <td class="px-3 py-3 text-right tabular">{{ formatScore(kri.score) }}</td>
              <td class="px-3 py-3 text-right tabular">{{ formatPercent(kri.weight * 100, 0) }}</td>
              <td class="py-3 pl-3 text-right font-semibold tabular">{{ formatScore(kri.contribution) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th scope="row" colspan="7" class="py-3 pr-3 text-right font-medium">{{ t('detail.composite') }}</th>
              <td class="py-3 pl-3 text-right font-semibold tabular">{{ formatScore(department.composite_score) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <section :aria-labelledby="`${idPrefix}-audit`">
      <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
        <component :is="heading" :id="`${idPrefix}-audit`" class="font-serif text-xl font-semibold">
          {{ t('detail.openAudits') }}
        </component>
        <RouterLink
          :to="{ name: 'audits', query: { department_id: department.id, risk_level: 'High', status: 'Open' } }"
          class="inline-flex items-center gap-1 text-sm font-medium text-accent hover:text-accent-hover"
        >
          {{ t('detail.openAuditsLink') }} <AppIcon name="arrow" />
        </RouterLink>
      </div>
      <p class="mt-1 text-secondary">
        <template v-if="audits.value > 0">
          {{ t('detail.openAuditsSummary', { count: formatNumber(audits.value), overdue: formatNumber(audits.overdue), date: formatDate(asOf) }) }}
        </template>
        <template v-else>{{ t('detail.noOpenAudits') }}</template>
      </p>
      <div v-if="audits.value > 0" class="relative mt-4 overflow-x-auto" tabindex="0" role="region" :aria-label="t('detail.openAuditsAria')">
        <table class="w-full min-w-[44rem] border-collapse text-sm">
          <thead>
            <tr class="border-b border-border text-left text-xs font-medium text-secondary">
              <th scope="col" class="py-2 pr-4">{{ t('detail.finding') }}</th>
              <th scope="col" class="px-3 py-2">{{ t('detail.auditor') }}</th>
              <th scope="col" class="px-3 py-2 text-right">{{ t('detail.identified') }}</th>
              <th scope="col" class="px-3 py-2 text-right">{{ t('detail.due') }}</th>
              <th scope="col" class="py-2 pl-3 text-right">{{ t('detail.lateness') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="finding in audits.findings" :key="finding.id" class="border-b border-hairline">
              <th scope="row" class="py-2.5 pr-4 text-left font-normal">
                <RouterLink :to="{ name: 'audit-detail', params: { id: finding.id } }" class="text-accent hover:underline">{{ finding.title }}</RouterLink>
              </th>
              <td class="px-3 py-2.5 whitespace-nowrap text-secondary">{{ finding.auditor }}</td>
              <td class="px-3 py-2.5 text-right whitespace-nowrap tabular">{{ formatDate(finding.identified_date, 'short') }}</td>
              <td class="px-3 py-2.5 text-right whitespace-nowrap tabular">{{ formatDate(finding.due_date, 'short') }}</td>
              <td
                class="py-2.5 pl-3 text-right whitespace-nowrap tabular"
                :class="finding.days_overdue > 0 ? 'font-medium text-critical-fg' : 'text-secondary'"
              >
                {{ overdueText(finding.days_overdue) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section :aria-labelledby="`${idPrefix}-server`">
      <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
        <component :is="heading" :id="`${idPrefix}-server`" class="font-serif text-xl font-semibold">{{ t('detail.servers') }}</component>
        <RouterLink
          :to="{ name: 'servers', query: { department_id: department.id, compliance: 'non_compliant' } }"
          class="inline-flex items-center gap-1 text-sm font-medium text-accent hover:text-accent-hover"
        >
          {{ t('detail.serversLink') }} <AppIcon name="arrow" />
        </RouterLink>
      </div>
      <p class="mt-1 text-secondary">
        {{ t('detail.serversSummary', { nc: formatNumber(servers.non_compliant), total: formatNumber(servers.total_servers), pct: formatPercent(servers.value, 1) }) }}
      </p>
      <table v-if="servers.top_non_compliant_os.length" class="mt-4 w-full max-w-xl border-collapse text-sm">
        <caption class="pb-2 text-left text-xs text-muted">{{ t('detail.topOs') }}</caption>
        <thead>
          <tr class="border-b border-border text-left text-xs font-medium text-secondary">
            <th scope="col" class="py-2 pr-4">{{ t('detail.os') }}</th>
            <th scope="col" class="py-2 pl-3 text-right">{{ t('detail.serverCount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="os in servers.top_non_compliant_os" :key="os.os_version" class="border-b border-hairline">
            <th scope="row" class="py-2.5 pr-4 text-left font-normal">{{ os.os_version }}</th>
            <td class="py-2.5 pl-3 text-right tabular">{{ formatNumber(os.servers) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <div class="grid gap-8 md:grid-cols-2">
      <section :aria-labelledby="`${idPrefix}-uptime`">
        <component :is="heading" :id="`${idPrefix}-uptime`" class="font-serif text-xl font-semibold">{{ t('detail.availability') }}</component>
        <p class="mt-1 text-secondary">
          {{ t('detail.availabilityText', { uptime: formatPercent(uptime.value, 2), apps: formatNumber(uptime.applications), minutes: formatNumber(uptime.total_downtime_minutes), duration: formatDuration(uptime.total_downtime_minutes) }) }}
        </p>
      </section>
      <section :aria-labelledby="`${idPrefix}-phishing`">
        <component :is="heading" :id="`${idPrefix}-phishing`" class="font-serif text-xl font-semibold">{{ t('detail.phishing') }}</component>
        <p class="mt-1 text-secondary">
          {{ t('detail.phishingText', { failed: formatNumber(phishing.total_failed), tested: formatNumber(phishing.total_tested), pct: formatPercent(phishing.value, 1) }) }}
        </p>
      </section>
    </div>
  </div>
</template>
