<script setup>
import { useI18n } from 'vue-i18n'
import { computed, ref } from 'vue'
import { ApiError, reviewSubmission } from '../lib/api.js'

const { t } = useI18n()

/** Auditor decision on a pending plan or evidence package. */
const props = defineProps({
  submission: { type: Object, required: true },
})
const emit = defineEmits(['reviewed'])

const decision = ref('')
const note = ref('')
const error = ref('')
const noteError = ref('')
const submitting = ref(false)

const isEvidence = computed(() => props.submission.type === 'evidence')
const submitLabel = computed(() => {
  if (decision.value === 'return') return t('review.submitReturn')
  if (decision.value === 'approve') return isEvidence.value ? t('review.submitClose') : t('review.submitApprovePlan')
  return t('review.submit')
})

async function submit() {
  if (submitting.value) return
  error.value = ''
  noteError.value = ''
  if (!decision.value) {
    error.value = t('review.errors.decision')
    return
  }
  if (decision.value === 'return' && !note.value.trim()) {
    noteError.value = t('review.errors.note')
    return
  }

  submitting.value = true
  try {
    await reviewSubmission(props.submission.id, decision.value, note.value.trim())
    emit('reviewed')
  } catch (e) {
    if (e instanceof ApiError && e.fieldErrors.note) noteError.value = e.fieldErrors.note[0]
    else error.value = e instanceof ApiError && e.body?.message ? e.body.message : t('review.errors.sendFailed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <div v-if="error" role="alert" class="border-l-4 border-critical-mark bg-critical-bg px-4 py-3 text-sm text-critical-fg">{{ error }}</div>

    <fieldset>
      <legend class="text-sm font-medium">{{ t('review.decision') }}</legend>
      <div class="mt-2 flex flex-wrap gap-3">
        <label class="flex items-center gap-2 rounded-md border border-control px-3 py-2 text-sm has-[:checked]:border-accent has-[:checked]:bg-accent-subtle">
          <input v-model="decision" type="radio" value="approve" class="accent-[var(--ui-accent)]" />
          {{ isEvidence ? t('review.approveEvidence') : t('review.approvePlan') }}
        </label>
        <label class="flex items-center gap-2 rounded-md border border-control px-3 py-2 text-sm has-[:checked]:border-accent has-[:checked]:bg-accent-subtle">
          <input v-model="decision" type="radio" value="return" class="accent-[var(--ui-accent)]" />
          {{ t('review.returnOption') }}
        </label>
      </div>
    </fieldset>

    <p v-if="decision === 'approve' && isEvidence" class="border-l-4 border-warning-mark bg-warning-bg px-4 py-3 text-sm text-warning-fg">
      {{ t('review.closeWarning') }}
    </p>

    <div>
      <label for="review-note" class="block text-sm font-medium">
        {{ t('review.note') }} <span class="font-normal text-muted">{{ decision === 'return' ? t('review.required') : t('review.optional') }}</span>
      </label>
      <textarea
        id="review-note"
        v-model="note"
        rows="3"
        maxlength="2000"
        class="mt-1 w-full rounded-md border bg-surface px-3 py-2"
        :class="noteError ? 'border-critical-mark' : 'border-control'"
        :aria-invalid="!!noteError"
        :aria-describedby="noteError ? 'review-note-error' : undefined"
      />
      <p v-if="noteError" id="review-note-error" class="mt-1 text-sm text-critical-fg">{{ noteError }}</p>
    </div>

    <button
      type="submit"
      class="rounded-md bg-accent px-4 py-2.5 font-medium text-on-accent hover:bg-accent-hover disabled:opacity-60"
      :disabled="submitting"
    >
      {{ submitting ? t('common.sending') : submitLabel }}
    </button>
  </form>
</template>
