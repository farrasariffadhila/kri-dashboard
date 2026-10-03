<script setup>
import { useI18n } from 'vue-i18n'
import { nextTick, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { statusFromRating } from '../lib/kri.js'
import { formatMonthLong, formatScore } from '../lib/format.js'
import AppIcon from './AppIcon.vue'
import DepartmentDetail from './DepartmentDetail.vue'
import StatusBadge from './StatusBadge.vue'

const { t } = useI18n()

const props = defineProps({
  department: { type: Object, default: null },
  methodology: { type: Object, required: true },
  reportingMonth: { type: String, required: true },
  asOf: { type: String, required: true },
  sources: { type: Object, default: null },
})

const emit = defineEmits(['close'])

const dialog = ref(null)
let returnFocusTo = null

watch(
  () => props.department,
  async (dept) => {
    if (dept) {
      returnFocusTo = document.activeElement
      await nextTick()
      if (!dialog.value.open) dialog.value.showModal()
    } else if (dialog.value?.open) {
      dialog.value.close()
    }
  },
)

function onClose() {
  emit('close')
  // Return focus to whatever opened the dialog, unless we are leaving the page through a link.
  if (returnFocusTo?.isConnected) returnFocusTo.focus()
}

function onBackdropClick(event) {
  if (event.target === dialog.value) dialog.value.close()
}
</script>

<template>
  <dialog
    ref="dialog"
    aria-labelledby="drilldown-title"
    class="m-auto max-h-[90vh] w-[min(64rem,calc(100vw-2rem))] overflow-y-auto rounded-md border border-border bg-surface p-0 text-ink backdrop:bg-[var(--ui-backdrop)]"
    @close="onClose"
    @click="onBackdropClick"
  >
    <template v-if="department">
      <header class="sticky top-0 z-10 flex flex-wrap items-start justify-between gap-4 border-b border-border bg-surface px-6 py-5">
        <div>
          <p class="text-sm text-muted">{{ t('dialog.eyebrow', { month: formatMonthLong(reportingMonth) }) }}</p>
          <h2 id="drilldown-title" class="font-serif text-2xl font-semibold">{{ department.name }}</h2>
          <p class="mt-2 flex flex-wrap items-center gap-3">
            <span class="text-3xl font-semibold">{{ formatScore(department.composite_score) }}</span>
            <span class="text-sm text-muted">{{ t('common.of100') }}</span>
            <StatusBadge :status="statusFromRating(department.rating)" />
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <RouterLink
            :to="{ name: 'department', params: { id: department.id } }"
            class="inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-sm font-medium text-accent hover:bg-accent-subtle"
          >
            {{ t('dialog.openPage') }} <AppIcon name="arrow" />
          </RouterLink>
          <button
            type="button"
            class="rounded-md border border-control px-3 py-1.5 text-sm font-medium hover:bg-sunken"
            @click="dialog.close()"
          >
            {{ t('common.close') }}
          </button>
        </div>
      </header>

      <div class="px-6 py-6">
        <DepartmentDetail :department="department" :methodology="methodology" :as-of="asOf" :heading-level="3" :sources="sources" />
      </div>
    </template>
  </dialog>
</template>
