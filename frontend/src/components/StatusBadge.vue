<script setup>
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'
import { STATUS } from '../lib/kri.js'

const { t } = useI18n()

const props = defineProps({
  status: { type: String, default: null }, // critical | warning | safe
  size: { type: String, default: 'md' }, // md | sm
  iconOnly: { type: Boolean, default: false }, // label stays available to screen readers
  label: { type: String, default: null }, // overrides the default status word
})

const text = computed(() => props.label ?? STATUS[props.status]?.label ?? t('common.noData'))
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 whitespace-nowrap font-medium"
    :class="[
      size === 'sm' ? 'text-xs' : 'rounded-md px-2 py-0.5 text-sm',
      {
        'text-critical-fg': status === 'critical',
        'text-warning-fg': status === 'warning',
        'text-safe-fg': status === 'safe',
        'text-muted': !status,
        'bg-critical-bg': size !== 'sm' && status === 'critical',
        'bg-warning-bg': size !== 'sm' && status === 'warning',
        'bg-safe-bg': size !== 'sm' && status === 'safe',
      },
    ]"
  >
    <!-- Each status has its own shape so it never depends on colour alone. -->
    <svg :class="size === 'sm' ? 'size-3' : 'size-3.5'" viewBox="0 0 16 16" aria-hidden="true">
      <template v-if="status === 'critical'">
        <path d="M5 1h6l4 4v6l-4 4H5l-4-4V5z" fill="currentColor" />
        <path d="M8 4v5M8 11.2v.1" stroke="var(--ui-surface)" stroke-width="2" stroke-linecap="round" />
      </template>
      <template v-else-if="status === 'warning'">
        <path d="M8 1.2 15.2 14H.8z" fill="currentColor" />
        <path d="M8 5.6v3.8M8 11.6v.1" stroke="var(--ui-surface)" stroke-width="1.8" stroke-linecap="round" />
      </template>
      <template v-else-if="status === 'safe'">
        <circle cx="8" cy="8" r="7" fill="currentColor" />
        <path d="m4.8 8.2 2.2 2.2 4.2-4.6" fill="none" stroke="var(--ui-surface)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
      </template>
      <circle v-else cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.5" />
    </svg>
    <span :class="{ 'sr-only': iconOnly }">{{ text }}</span>
  </span>
</template>
