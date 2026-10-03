<script setup>
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { KRI_BY_KEY } from '../lib/kri.js'
import { disputeStatus, formatFigure } from '../lib/disputes.js'
import { TONE_CLASSES } from '../lib/workflow.js'
import { formatDate } from '../lib/format.js'

const { t } = useI18n()

/** KRI disputes as a table; used by the disputes page and the work queue. */
defineProps({
  disputes: { type: Array, required: true },
  label: { type: String, required: true },
  showStatus: { type: Boolean, default: true },
})
</script>

<template>
  <div class="relative overflow-x-auto border-y border-border bg-surface" tabindex="0" role="region" :aria-label="label">
    <table class="w-full min-w-3xl border-collapse text-sm">
      <thead>
        <tr class="border-b border-border text-left text-xs font-medium text-secondary">
          <th scope="col" class="px-4 py-3">{{ t('dispute.columns.indicator') }}</th>
          <th scope="col" class="px-3 py-3">{{ t('dispute.columns.department') }}</th>
          <th scope="col" class="px-3 py-3 text-right">{{ t('dispute.columns.figures') }}</th>
          <th v-if="showStatus" scope="col" class="px-3 py-3">{{ t('dispute.columns.status') }}</th>
          <th scope="col" class="px-4 py-3 text-right">{{ t('dispute.columns.submitted') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="d in disputes" :key="d.id" class="border-b border-hairline align-top">
          <th scope="row" class="px-4 py-3 text-left font-normal">
            <RouterLink :to="{ name: 'dispute-detail', params: { id: d.id } }" class="text-accent hover:underline">{{ KRI_BY_KEY[d.kri].label }}</RouterLink>
            <span class="block text-xs text-muted tabular">#{{ d.id }}</span>
          </th>
          <td class="px-3 py-3 whitespace-nowrap">{{ d.department.name }}</td>
          <td class="px-3 py-3 text-right whitespace-nowrap tabular">
            {{ formatFigure(d.unit, d.source_value) }} → <span class="font-medium">{{ formatFigure(d.unit, d.corrected_value ?? d.proposed_value) }}</span>
          </td>
          <td v-if="showStatus" class="px-3 py-3">
            <span class="rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap" :class="TONE_CLASSES[disputeStatus(d.status).tone]">
              {{ disputeStatus(d.status).label }}
            </span>
          </td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <span class="tabular">{{ formatDate(d.submitted_at.slice(0, 10), 'short') }}</span>
            <span class="block text-xs text-muted">{{ d.submitted_by?.name }}</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
