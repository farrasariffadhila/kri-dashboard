<script setup>
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'
import { kriChange, statusFromRating } from '../lib/kri.js'
import { formatMonthLong } from '../lib/format.js'
import { localized } from '../i18n/index.js'
import StatusBadge from './StatusBadge.vue'

const { t } = useI18n()

const props = defineProps({
  kri: { type: Object, required: true }, // entry of KRIS
  current: { type: Object, required: true }, // totals.current[kri.key]
  previous: { type: Object, default: null }, // totals.previous[kri.key]
  previousMonth: { type: String, required: true },
  source: { type: Object, default: null }, // summary.data_sources[kri.key]
})

const change = computed(() => kriChange(props.kri, props.current, props.previous))
const previousLabel = computed(() => formatMonthLong(props.previousMonth).split(' ')[0])
</script>

<template>
  <article class="flex flex-col gap-3 border border-border bg-surface p-5">
    <div class="flex items-start justify-between gap-3">
      <h3 class="text-sm font-medium text-secondary">{{ kri.label }}</h3>
      <StatusBadge v-if="current.rating" size="sm" :status="statusFromRating(current.rating)" />
    </div>
    <p class="text-3xl font-semibold">{{ kri.format(current.value) }}</p>
    <div class="mt-auto space-y-1 text-sm">
      <p class="text-muted"><slot name="detail" /></p>
      <p
        v-if="change"
        class="font-medium"
        :class="{
          'text-critical-fg': change.trend === 'worse',
          'text-safe-fg': change.trend === 'better',
          'text-secondary': change.trend === 'flat',
        }"
      >
        <span aria-hidden="true">{{ change.direction === 'up' ? '▲' : change.direction === 'down' ? '▼' : '■' }}</span>
        {{ change.text }} {{ t('common.previousMonth', { month: previousLabel }) }}
        <span v-if="change.trend === 'worse'">· {{ t('common.worse') }}</span>
        <span v-else-if="change.trend === 'better'">· {{ t('common.better') }}</span>
      </p>
      <p v-if="source" class="border-t border-hairline pt-2 text-xs text-muted">
        {{ t('common.source', { system: localized(source.system) }) }}{{ source.simulated ? ` ${t('common.simulated')}` : '' }}
      </p>
    </div>
  </article>
</template>
