<script setup>
import { useI18n } from 'vue-i18n'
import { computed, ref } from 'vue'
import { ApiError, submitEvidence, submitPlan } from '../lib/api.js'

const { t } = useI18n()

/** Unit owner form: an action plan (with target date) or remediation evidence (files required). */
const props = defineProps({
  issueId: { type: Number, required: true },
  type: { type: String, required: true }, // plan | evidence
})
const emit = defineEmits(['submitted'])

const MAX_FILES = 3
const MAX_BYTES = 5 * 1024 * 1024
const ALLOWED = ['application/pdf', 'image/png', 'image/jpeg']

const isPlan = computed(() => props.type === 'plan')
const description = ref('')
const targetDate = ref('')
const files = ref([])
const fileInput = ref(null)
const errors = ref({})
const formError = ref('')
const submitting = ref(false)

const today = new Date().toISOString().slice(0, 10)
const ids = computed(() => ({
  description: `${props.type}-description`,
  target: `${props.type}-target`,
  files: `${props.type}-files`,
}))

function onFiles(event) {
  const picked = [...event.target.files]
  const problems = []
  if (picked.length > MAX_FILES) problems.push(t('form.errors.tooMany', { max: MAX_FILES }))
  if (picked.some((f) => f.size > MAX_BYTES)) problems.push(t('form.errors.tooLarge'))
  if (picked.some((f) => !ALLOWED.includes(f.type))) problems.push(t('form.errors.wrongType'))
  errors.value = { ...errors.value, files: problems }
  files.value = problems.length ? [] : picked
}

function clientErrors() {
  const e = {}
  if (description.value.trim().length < 20) e.description = [t('form.errors.description')]
  if (isPlan.value && !targetDate.value) e.target_date = [t('form.errors.target')]
  if (!isPlan.value && files.value.length === 0) e.files = [t('form.errors.filesRequired')]
  return e
}

async function submit() {
  if (submitting.value) return
  formError.value = ''
  errors.value = clientErrors()
  if (Object.keys(errors.value).length) return

  const data = new FormData()
  data.append('description', description.value.trim())
  if (isPlan.value) data.append('target_date', targetDate.value)
  files.value.forEach((f) => data.append('files[]', f))

  submitting.value = true
  try {
    await (isPlan.value ? submitPlan(props.issueId, data) : submitEvidence(props.issueId, data))
    description.value = ''
    targetDate.value = ''
    files.value = []
    if (fileInput.value) fileInput.value.value = ''
    emit('submitted')
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) errors.value = normalise(e.fieldErrors)
    else formError.value = e instanceof ApiError && e.body?.message ? e.body.message : t('form.errors.sendFailed')
  } finally {
    submitting.value = false
  }
}

/** Fold "files.0" style keys into "files". */
function normalise(fieldErrors) {
  const out = {}
  for (const [key, messages] of Object.entries(fieldErrors)) {
    const k = key.startsWith('files') ? 'files' : key
    out[k] = [...(out[k] ?? []), ...messages]
  }
  return out
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <div v-if="formError" role="alert" class="border-l-4 border-critical-mark bg-critical-bg px-4 py-3 text-sm text-critical-fg">
      {{ formError }}
    </div>

    <div>
      <label :for="ids.description" class="block text-sm font-medium">
        {{ isPlan ? t('form.plan') : t('form.evidence') }}
      </label>
      <p :id="`${ids.description}-hint`" class="text-xs text-muted">
        {{ isPlan ? t('form.planHint') : t('form.evidenceHint') }}
      </p>
      <textarea
        :id="ids.description"
        v-model="description"
        rows="4"
        maxlength="2000"
        class="mt-1 w-full rounded-md border bg-surface px-3 py-2"
        :class="errors.description ? 'border-critical-mark' : 'border-control'"
        :aria-invalid="!!errors.description"
        :aria-describedby="`${ids.description}-hint${errors.description ? ` ${ids.description}-error` : ''}`"
      />
      <p v-if="errors.description" :id="`${ids.description}-error`" class="mt-1 text-sm text-critical-fg">{{ errors.description[0] }}</p>
    </div>

    <div v-if="isPlan">
      <label :for="ids.target" class="block text-sm font-medium">{{ t('form.target') }}</label>
      <input
        :id="ids.target"
        v-model="targetDate"
        type="date"
        :min="today"
        class="mt-1 rounded-md border bg-surface px-3 py-2"
        :class="errors.target_date ? 'border-critical-mark' : 'border-control'"
        :aria-invalid="!!errors.target_date"
        :aria-describedby="errors.target_date ? `${ids.target}-error` : undefined"
      />
      <p v-if="errors.target_date" :id="`${ids.target}-error`" class="mt-1 text-sm text-critical-fg">{{ errors.target_date[0] }}</p>
    </div>

    <div>
      <label :for="ids.files" class="block text-sm font-medium">
        {{ isPlan ? t('form.attachmentsOptional') : t('form.evidenceFiles') }}
      </label>
      <p :id="`${ids.files}-hint`" class="text-xs text-muted">{{ t('form.filesHint') }}</p>
      <input
        :id="ids.files"
        ref="fileInput"
        type="file"
        multiple
        accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
        class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border file:border-control file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium"
        :aria-invalid="!!errors.files?.length"
        :aria-describedby="`${ids.files}-hint${errors.files?.length ? ` ${ids.files}-error` : ''}`"
        @change="onFiles"
      />
      <p v-if="errors.files?.length" :id="`${ids.files}-error`" class="mt-1 text-sm text-critical-fg">{{ errors.files.join(' ') }}</p>
    </div>

    <button
      type="submit"
      class="rounded-md bg-accent px-4 py-2.5 font-medium text-on-accent hover:bg-accent-hover disabled:opacity-60"
      :disabled="submitting"
    >
      {{ submitting ? t('common.sending') : isPlan ? t('finding.submitPlan') : t('finding.submitEvidence') }}
    </button>
  </form>
</template>
