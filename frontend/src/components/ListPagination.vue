<script setup>
import { useI18n } from 'vue-i18n'
import { formatNumber } from '../lib/format.js'

const { t } = useI18n()

defineProps({
  page: { type: Object, required: true }, // Laravel paginator: current_page, last_page, from, to, total
  noun: { type: String, required: true },
})
const emit = defineEmits(['change'])
</script>

<template>
  <nav class="flex flex-wrap items-center justify-between gap-3 pt-4 text-sm" :aria-label="t('common.pageNav')">
    <p class="text-secondary tabular">
      {{ t('common.pageOf', { current: formatNumber(page.current_page), last: formatNumber(page.last_page) }) }}
    </p>
    <div class="flex gap-2">
      <button
        type="button"
        class="rounded-md border border-control px-3 py-1.5 font-medium hover:bg-sunken disabled:cursor-not-allowed disabled:border-border disabled:text-muted disabled:hover:bg-transparent"
        :disabled="page.current_page <= 1"
        @click="emit('change', page.current_page - 1)"
      >
        {{ t('common.previous') }}
      </button>
      <button
        type="button"
        class="rounded-md border border-control px-3 py-1.5 font-medium hover:bg-sunken disabled:cursor-not-allowed disabled:border-border disabled:text-muted disabled:hover:bg-transparent"
        :disabled="page.current_page >= page.last_page"
        @click="emit('change', page.current_page + 1)"
      >
        {{ t('common.next') }}
      </button>
    </div>
  </nav>
</template>
