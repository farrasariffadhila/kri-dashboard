<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ApiError, attachmentUrl, fetchAuditIssue } from '../lib/api.js'
import { authStore, refreshWorkCounts } from '../lib/auth.js'
import { loadSummary, resetRiskStore } from '../lib/store.js'
import { RISK_LEVELS, roleLabel } from '../lib/kri.js'
import { TONE_CLASSES, activityText, completedSteps, remediationInfo, steps, submissionStatus, submissionType } from '../lib/workflow.js'
import { formatDate, formatDateTime, formatNumber } from '../lib/format.js'
import { sizeText } from '../lib/attachments.js'
import PageHeader from '../components/PageHeader.vue'
import AppIcon from '../components/AppIcon.vue'
import SubmissionForm from '../components/SubmissionForm.vue'
import ReviewForm from '../components/ReviewForm.vue'

const { t } = useI18n()

const props = defineProps({
  id: { type: String, required: true },
})

const data = ref(null)
const error = ref(null)
const notice = ref('') // message key, so it follows a language switch

async function load() {
  error.value = null
  try {
    data.value = await fetchAuditIssue(props.id)
  } catch (e) {
    error.value = e instanceof ApiError && e.status === 404 ? 'not-found' : 'failed'
  }
}
watch(
  () => props.id,
  () => {
    notice.value = ''
    load()
  },
  { immediate: true },
)

const issue = computed(() => data.value?.issue)
const permissions = computed(() => data.value?.permissions ?? {})
const status = computed(() => (issue.value ? remediationInfo(issue.value) : null))
const done = computed(() => (issue.value ? completedSteps(issue.value) : 0))
const pending = computed(() => data.value?.submissions.find((s) => s.id === permissions.value.pending_submission_id) ?? null)
const lastReturned = computed(() => data.value?.submissions.find((s) => s.status === 'returned') ?? null)
const showReturnedNote = computed(
  () => lastReturned.value && (permissions.value.can_submit_plan || permissions.value.can_submit_evidence) && data.value.submissions[0]?.status === 'returned',
)

/** Why there is nothing to do here, for users without an action. */
const idleMessage = computed(() => {
  const user = authStore.user
  if (!issue.value || issue.value.status === 'Closed') return t('finding.idle.closed')
  if (['plan_review', 'evidence_review'].includes(issue.value.remediation_status)) return t('finding.idle.inReview')
  if (user?.role === 'unit_owner') return t('finding.idle.otherUnit', { department: issue.value.department.name })
  if (user?.role === 'auditor') {
    return ['in_progress', 'evidence_returned'].includes(issue.value.remediation_status)
      ? t('finding.idle.awaitingEvidence')
      : t('finding.idle.awaitingPlan')
  }
  return t('finding.idle.viewer', { role: roleLabel(user?.role) })
})

async function afterAction(messageKey) {
  notice.value = messageKey
  await load()
  // The KRIs may have changed (a closed finding lowers the score): drop cached data and refresh.
  resetRiskStore()
  loadSummary()
  refreshWorkCounts()
}

const crumbs = computed(() => [
  { label: t('common.overview'), to: '/' },
  { label: t('audits.title'), to: '/temuan-audit' },
  ...(issue.value ? [{ label: t('finding.crumb', { id: issue.value.id }) }] : []),
])
</script>

<template>
  <div v-if="error === 'not-found'">
    <PageHeader :title="t('finding.notFound')" :breadcrumbs="crumbs" />
    <RouterLink to="/temuan-audit" class="font-medium text-accent underline underline-offset-4">{{ t('finding.backToList') }}</RouterLink>
  </div>

  <div v-else-if="error" role="alert" class="max-w-2xl border-l-4 border-critical-mark bg-critical-bg px-5 py-4">
    <p class="font-semibold text-critical-fg">{{ t('finding.loadFailed') }}</p>
    <button type="button" class="mt-2 rounded-md border border-control bg-surface px-3 py-1.5 text-sm font-medium" @click="load">
      {{ t('common.retry') }}
    </button>
  </div>

  <div v-else-if="!data" aria-busy="true" class="space-y-6">
    <p class="sr-only" role="status">{{ t('finding.loading') }}</p>
    <div class="h-28 max-w-3xl animate-pulse rounded-md bg-sunken" />
    <div class="h-72 animate-pulse rounded-md bg-sunken" />
  </div>

  <template v-else>
    <PageHeader :title="issue.title" :breadcrumbs="crumbs">
      <span class="inline-flex flex-wrap items-center gap-2">
        <span class="rounded-md px-2 py-0.5 text-sm font-medium" :class="TONE_CLASSES[status.tone]">{{ status.label }}</span>
        <span v-if="issue.days_overdue > 0" class="font-medium text-critical-fg">
          {{ t('finding.overdue', { days: formatNumber(issue.days_overdue) }) }}
        </span>
      </span>
    </PageHeader>

    <dl class="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-3 xl:grid-cols-6">
      <div>
        <dt class="text-muted">{{ t('finding.department') }}</dt>
        <dd class="font-medium">
          <RouterLink :to="{ name: 'department', params: { id: issue.department.id } }" class="text-accent hover:underline">{{ issue.department.name }}</RouterLink>
        </dd>
      </div>
      <div>
        <dt class="text-muted">{{ t('finding.riskLevel') }}</dt>
        <dd>
          <span class="rounded-md px-2 py-0.5 text-xs font-medium" :class="TONE_CLASSES[RISK_LEVELS[issue.risk_level].tone ?? 'neutral']">
            {{ RISK_LEVELS[issue.risk_level].label }}
          </span>
        </dd>
      </div>
      <div><dt class="text-muted">{{ t('finding.auditor') }}</dt><dd class="font-medium">{{ issue.auditor }}</dd></div>
      <div><dt class="text-muted">{{ t('finding.identified') }}</dt><dd class="font-medium tabular">{{ formatDate(issue.identified_date) }}</dd></div>
      <div><dt class="text-muted">{{ t('finding.due') }}</dt><dd class="font-medium tabular">{{ formatDate(issue.due_date) }}</dd></div>
      <div v-if="issue.closed_date"><dt class="text-muted">{{ t('finding.closed') }}</dt><dd class="font-medium tabular">{{ formatDate(issue.closed_date) }}</dd></div>
    </dl>

    <!-- Workflow progress -->
    <section aria-labelledby="langkah-title" class="mt-8">
      <h2 id="langkah-title" class="sr-only">{{ t('workflow.steps.aria') }}</h2>
      <ol class="grid gap-2 sm:grid-cols-4">
        <li
          v-for="(step, i) in steps()"
          :key="i"
          class="border-t-4 pt-2 text-sm"
          :class="i < done ? 'border-accent font-medium' : i === done ? 'border-accent/40' : 'border-border text-muted'"
          :aria-current="i === done && issue.status === 'Open' ? 'step' : undefined"
        >
          <span class="tabular">{{ i + 1 }}.</span> {{ step }}
          <span class="sr-only">
            {{ i < done ? t('workflow.steps.done') : i === done ? t('workflow.steps.current') : t('workflow.steps.todo') }}
          </span>
        </li>
      </ol>
    </section>

    <div v-if="notice" role="status" class="mt-6 border-l-4 border-safe-mark bg-safe-bg px-4 py-3 text-sm font-medium text-safe-fg">{{ t(notice) }}</div>

    <div class="mt-8 grid gap-8 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
      <div class="min-w-0 space-y-8">
        <!-- What the signed-in user can do now -->
        <section aria-labelledby="aksi-title" class="border border-border bg-surface p-5 sm:p-6">
          <h2 id="aksi-title" class="font-serif text-xl font-semibold">
            <template v-if="permissions.can_submit_plan">{{ t('finding.submitPlan') }}</template>
            <template v-else-if="permissions.can_submit_evidence">{{ t('finding.submitEvidence') }}</template>
            <template v-else-if="permissions.can_review">{{ t('finding.review', { type: submissionType(pending.type).toLowerCase() }) }}</template>
            <template v-else>{{ t('finding.actions') }}</template>
          </h2>

          <div v-if="showReturnedNote" class="mt-3 border-l-4 border-warning-mark bg-warning-bg px-4 py-3 text-sm text-warning-fg">
            <p class="font-medium">{{ t('finding.returnedBy', { type: submissionType(lastReturned.type), name: lastReturned.reviewed_by?.name }) }}</p>
            <p class="mt-1">{{ lastReturned.review_note }}</p>
          </div>

          <div class="mt-4">
            <SubmissionForm v-if="permissions.can_submit_plan" :issue-id="issue.id" type="plan" @submitted="afterAction('finding.planSent')" />
            <SubmissionForm
              v-else-if="permissions.can_submit_evidence"
              :issue-id="issue.id"
              type="evidence"
              @submitted="afterAction('finding.evidenceSent')"
            />
            <template v-else-if="permissions.can_review">
              <div class="mb-4 border border-border bg-sunken px-4 py-3 text-sm">
                <p class="text-muted">{{ t('finding.submittedBy', { name: pending.submitted_by?.name, date: formatDateTime(pending.submitted_at) }) }}</p>
                <p class="mt-1 whitespace-pre-line">{{ pending.description }}</p>
                <p v-if="pending.target_date" class="mt-1 text-secondary">{{ t('finding.target', { date: formatDate(pending.target_date) }) }}</p>
              </div>
              <ReviewForm :submission="pending" @reviewed="afterAction('finding.decisionSaved')" />
            </template>
            <p v-else class="text-secondary">{{ idleMessage }}</p>
          </div>
        </section>

        <!-- Submission history -->
        <section aria-labelledby="riwayat-title">
          <h2 id="riwayat-title" class="font-serif text-xl font-semibold">{{ t('finding.history') }}</h2>
          <p v-if="!data.submissions.length" class="mt-2 text-secondary">{{ t('finding.noHistory') }}</p>
          <ol v-else class="mt-4 space-y-4">
            <li v-for="s in data.submissions" :key="s.id" class="border border-border bg-surface p-5">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-medium">{{ submissionType(s.type) }}</h3>
                <span class="rounded-md px-2 py-0.5 text-xs font-medium" :class="TONE_CLASSES[submissionStatus(s.status).tone]">
                  {{ submissionStatus(s.status).label }}
                </span>
              </div>
              <p class="mt-1 text-xs text-muted">{{ s.submitted_by?.name }} ({{ roleLabel(s.submitted_by?.role) }}) · {{ formatDateTime(s.submitted_at) }}</p>
              <p class="mt-3 whitespace-pre-line text-sm">{{ s.description }}</p>
              <p v-if="s.target_date" class="mt-2 text-sm text-secondary">{{ t('finding.target', { date: formatDate(s.target_date) }) }}</p>

              <ul v-if="s.attachments.length" class="mt-3 space-y-1 text-sm" :aria-label="t('finding.attachments')">
                <li v-for="a in s.attachments" :key="a.id">
                  <a :href="attachmentUrl(a.id)" class="inline-flex items-center gap-1.5 font-medium text-accent hover:underline" download>
                    <AppIcon name="paperclip" /> {{ a.original_name }}
                  </a>
                  <span class="ml-1 text-xs text-muted tabular">{{ sizeText(a.size) }} · SHA-256 {{ a.checksum.slice(0, 12) }}…</span>
                </li>
              </ul>

              <div v-if="s.reviewed_at" class="mt-3 border-t border-hairline pt-3 text-sm">
                <p class="text-xs text-muted">{{ t('finding.reviewedBy', { name: s.reviewed_by?.name, date: formatDateTime(s.reviewed_at) }) }}</p>
                <p v-if="s.review_note" class="mt-1 whitespace-pre-line">{{ s.review_note }}</p>
              </div>
            </li>
          </ol>
        </section>
      </div>

      <!-- Audit trail -->
      <section aria-labelledby="jejak-title" class="min-w-0">
        <h2 id="jejak-title" class="font-serif text-xl font-semibold">{{ t('finding.trail') }}</h2>
        <p class="mt-1 text-sm text-secondary">{{ t('finding.trailText') }}</p>
        <p v-if="!data.activity.length" class="mt-3 text-sm text-secondary">{{ t('finding.noTrail') }}</p>
        <ol v-else class="mt-4 space-y-3 border-l-2 border-border pl-4">
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
