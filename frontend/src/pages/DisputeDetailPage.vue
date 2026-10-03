<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import { ApiError, attachmentUrl, fetchDispute } from '../lib/api.js'
import { refreshWorkCounts } from '../lib/auth.js'
import { loadSummary, resetRiskStore } from '../lib/store.js'
import { KRI_BY_KEY, roleLabel } from '../lib/kri.js'
import { disputeStatus, formatFigure } from '../lib/disputes.js'
import { TONE_CLASSES, activityText } from '../lib/workflow.js'
import { sizeText } from '../lib/attachments.js'
import { formatDateTime, formatMonthLong } from '../lib/format.js'
import PageHeader from '../components/PageHeader.vue'
import AppIcon from '../components/AppIcon.vue'
import DisputeReviewForm from '../components/DisputeReviewForm.vue'

const { t } = useI18n()
const route = useRoute()

const props = defineProps({
  id: { type: String, required: true },
})

const data = ref(null)
const error = ref(null)
const notice = ref('') // message key, so it follows a language switch

async function load() {
  error.value = null
  try {
    data.value = await fetchDispute(props.id)
  } catch (e) {
    error.value = e instanceof ApiError && (e.status === 404 || e.status === 403) ? 'not-found' : 'failed'
  }
}
watch(
  () => props.id,
  () => {
    notice.value = route.query.terkirim ? 'dispute.detail.sent' : ''
    load()
  },
  { immediate: true },
)

const dispute = computed(() => data.value?.dispute)
const status = computed(() => (dispute.value ? disputeStatus(dispute.value.status) : null))
const fmt = (value) => formatFigure(dispute.value.unit, value)
const sourceChanged = computed(() => dispute.value && data.value.baseline.source !== dispute.value.source_value)

async function afterReview() {
  notice.value = 'dispute.detail.decisionSaved'
  await load()
  // An accepted correction changes the score: drop cached KRIs and refresh the badge.
  resetRiskStore()
  loadSummary()
  refreshWorkCounts()
}

const crumbs = computed(() => [
  { label: t('common.overview'), to: '/' },
  { label: t('dispute.title'), to: '/sanggahan' },
  ...(dispute.value ? [{ label: t('dispute.detail.crumb', { id: dispute.value.id }) }] : []),
])
</script>

<template>
  <div v-if="error === 'not-found'">
    <PageHeader :title="t('dispute.detail.notFound')" :breadcrumbs="crumbs" />
    <RouterLink to="/sanggahan" class="font-medium text-accent underline underline-offset-4">{{ t('dispute.detail.backToList') }}</RouterLink>
  </div>

  <div v-else-if="error" role="alert" class="max-w-2xl border-l-4 border-critical-mark bg-critical-bg px-5 py-4">
    <p class="font-semibold text-critical-fg">{{ t('dispute.detail.loadFailed') }}</p>
    <button type="button" class="mt-2 rounded-md border border-control bg-surface px-3 py-1.5 text-sm font-medium" @click="load">
      {{ t('common.retry') }}
    </button>
  </div>

  <div v-else-if="!data" aria-busy="true" class="space-y-6">
    <p class="sr-only" role="status">{{ t('dispute.detail.loading') }}</p>
    <div class="h-28 max-w-3xl animate-pulse rounded-md bg-sunken" />
    <div class="h-72 animate-pulse rounded-md bg-sunken" />
  </div>

  <template v-else>
    <PageHeader :title="`${KRI_BY_KEY[dispute.kri].label} · ${dispute.department.name}`" :breadcrumbs="crumbs">
      <span class="rounded-md px-2 py-0.5 text-sm font-medium" :class="TONE_CLASSES[status.tone]">{{ status.label }}</span>
    </PageHeader>

    <dl class="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-3 xl:grid-cols-6">
      <div>
        <dt class="text-muted">{{ t('dispute.detail.department') }}</dt>
        <dd class="font-medium">
          <RouterLink :to="{ name: 'department', params: { id: dispute.department.id } }" class="text-accent hover:underline">{{ dispute.department.name }}</RouterLink>
        </dd>
      </div>
      <div><dt class="text-muted">{{ t('dispute.detail.period') }}</dt><dd class="font-medium">{{ formatMonthLong(dispute.month) }}</dd></div>
      <div><dt class="text-muted">{{ t('dispute.detail.source') }}</dt><dd class="font-medium tabular">{{ fmt(dispute.source_value) }}</dd></div>
      <div><dt class="text-muted">{{ t('dispute.detail.proposed') }}</dt><dd class="font-medium tabular">{{ fmt(dispute.proposed_value) }}</dd></div>
      <div v-if="dispute.corrected_value != null">
        <dt class="text-muted">{{ t('dispute.detail.corrected') }}</dt><dd class="font-semibold tabular">{{ fmt(dispute.corrected_value) }}</dd>
      </div>
      <div v-if="sourceChanged">
        <dt class="text-muted">{{ t('dispute.detail.sourceNow') }}</dt><dd class="font-medium tabular">{{ fmt(data.baseline.source) }}</dd>
      </div>
    </dl>

    <div v-if="notice" role="status" class="mt-6 border-l-4 border-safe-mark bg-safe-bg px-4 py-3 text-sm font-medium text-safe-fg">{{ t(notice) }}</div>

    <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
      <div class="min-w-0 space-y-8">
        <section aria-labelledby="alasan-title" class="border border-border bg-surface p-5 sm:p-6">
          <h2 id="alasan-title" class="font-serif text-xl font-semibold">{{ t('dispute.detail.reason') }}</h2>
          <p class="mt-1 text-xs text-muted">
            {{ dispute.submitted_by?.name }} ({{ roleLabel(dispute.submitted_by?.role) }}) · {{ formatDateTime(dispute.submitted_at) }}
          </p>
          <p class="mt-3 whitespace-pre-line">{{ dispute.reason }}</p>
          <ul v-if="dispute.attachments.length" class="mt-4 space-y-1 text-sm" :aria-label="t('dispute.detail.attachments')">
            <li v-for="a in dispute.attachments" :key="a.id">
              <a :href="attachmentUrl(a.id)" class="inline-flex items-center gap-1.5 font-medium text-accent hover:underline" download>
                <AppIcon name="paperclip" /> {{ a.original_name }}
              </a>
              <span class="ml-1 text-xs text-muted tabular">{{ sizeText(a.size) }} · SHA-256 {{ a.checksum.slice(0, 12) }}…</span>
            </li>
          </ul>
        </section>

        <section aria-labelledby="keputusan-title" class="border border-border bg-surface p-5 sm:p-6">
          <h2 id="keputusan-title" class="font-serif text-xl font-semibold">
            {{ data.permissions.can_review ? t('dispute.detail.review') : t('dispute.detail.decision') }}
          </h2>
          <div class="mt-4">
            <DisputeReviewForm v-if="data.permissions.can_review" :dispute="dispute" :baseline="data.baseline" @reviewed="afterReview" />
            <p v-else-if="dispute.status === 'pending'" class="text-secondary">{{ t('dispute.detail.waiting') }}</p>
            <template v-else>
              <p class="text-xs text-muted">{{ t('dispute.detail.reviewedBy', { name: dispute.reviewed_by?.name, date: formatDateTime(dispute.reviewed_at) }) }}</p>
              <p v-if="dispute.status === 'accepted'" class="mt-2 font-medium">{{ t('dispute.detail.acceptedText', { value: fmt(dispute.corrected_value) }) }}</p>
              <p v-if="dispute.review_note" class="mt-2 whitespace-pre-line">{{ dispute.review_note }}</p>
            </template>
          </div>
        </section>
      </div>

      <section aria-labelledby="jejak-title" class="min-w-0">
        <h2 id="jejak-title" class="font-serif text-xl font-semibold">{{ t('finding.trail') }}</h2>
        <ol class="mt-4 space-y-3 border-l-2 border-border pl-4">
          <li v-for="entry in data.activity" :key="entry.id" class="text-sm">
            <p class="text-xs text-muted tabular">{{ formatDateTime(entry.created_at) }}</p>
            <p>
              <span class="font-medium">{{ entry.user?.name ?? t('finding.system') }}</span>
              <span v-if="entry.user" class="text-muted"> ({{ roleLabel(entry.user.role) }})</span>
              {{ activityText(entry.action) }}
            </p>
            <p v-if="entry.note" class="mt-0.5 text-secondary">“{{ entry.note }}”</p>
          </li>
        </ol>
      </section>
    </div>
  </template>
</template>
