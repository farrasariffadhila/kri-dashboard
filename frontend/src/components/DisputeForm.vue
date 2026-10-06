<script setup>
import { useI18n } from 'vue-i18n'
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { ApiError, submitDispute } from '../lib/api.js'
import { ACCEPT, fileProblems, foldFileErrors } from '../lib/attachments.js'
import { DISPUTABLE_KRIS, UNITS, figureOf, formatFigure } from '../lib/disputes.js'
import { KRI_BY_KEY } from '../lib/kri.js'
import { formatNumber } from '../lib/format.js'

const { t } = useI18n()

/** Unit owner form: dispute one KRI figure of their own department for the reporting month. */
const props = defineProps({
  department: { type: Object, required: true }, // department row from the risk summary
  initialKri: { type: String, default: null },
})
const emit = defineEmits(['submitted'])

const kri = ref(DISPUTABLE_KRIS.includes(props.initialKri) ? props.initialKri : DISPUTABLE_KRIS[0])
const proposed = ref('')
const reason = ref('')
const files = ref([])
const fileInput = ref(null)
const errors = ref({})
const formError = ref('')
const submitting = ref(false)

const metric = computed(() => props.department.kris[kri.value])
const figure = computed(() => figureOf(kri.value, metric.value))
const unit = computed(() => UNITS[kri.value])
const maxText = computed(() => (figure.value.integer ? formatNumber(figure.value.max) : '100'))
const openDisputeId = computed(() => metric.value.open_dispute_id ?? null)

watch(kri, () => {
  proposed.value = ''
  errors.value = {}
})

function onFiles(event) {
  const picked = [...event.target.files]
  const problems = fileProblems(picked)
  errors.value = { ...errors.value, files: problems }
  files.value = problems.length ? [] : picked
}

function clientErrors() {
  const e = {}
  const value = Number(String(proposed.value).replace(',', '.'))
  if (proposed.value === '' || Number.isNaN(value)) e.proposed_value = [t('dispute.form.errors.value')]
  else if (value < 0 || value > figure.value.max) e.proposed_value = [t('dispute.form.errors.range', { max: maxText.value })]
  else if (figure.value.integer && !Number.isInteger(value)) e.proposed_value = [t('dispute.form.errors.integer')]
  else if (value === figure.value.value) e.proposed_value = [t('dispute.form.errors.same')]
  if (reason.value.trim().length < 20) e.reason = [t('dispute.form.errors.reason')]
  return e
}

async function submit() {
  if (submitting.value) return
  formError.value = ''
  errors.value = clientErrors()
  if (Object.keys(errors.value).length) return

  const data = new FormData()
  data.append('kri', kri.value)
  data.append('proposed_value', String(proposed.value).replace(',', '.'))
  data.append('reason', reason.value.trim())
  files.value.forEach((f) => data.append('files[]', f))

  submitting.value = true
  try {
    const { id } = await submitDispute(data)
    emit('submitted', id)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) errors.value = foldFileErrors(e.fieldErrors)
    else formError.value = e instanceof ApiError && e.body?.message ? e.body.message : t('dispute.form.errors.sendFailed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form class="max-w-2xl space-y-5" novalidate @submit.prevent="submit">
    <div v-if="formError" role="alert" class="border-l-4 border-critical-mark bg-critical-bg px-4 py-3 text-sm text-critical-fg">{{ formError }}</div>

    <div>
      <label for="dispute-kri" class="block text-sm font-medium">{{ t('dispute.form.indicator') }}</label>
      <select id="dispute-kri" v-model="kri" class="mt-1 w-full rounded-md border border-control bg-surface px-3 py-2 sm:w-auto">
        <option v-for="key in DISPUTABLE_KRIS" :key="key" :value="key">{{ KRI_BY_KEY[key].label }}</option>
      </select>
      <p class="mt-1 text-sm text-secondary tabular">
        {{ t('dispute.form.current', { value: formatFigure(unit, figure.value) }) }}{{ metric.correction ? ` · ${t('dispute.form.corrected')}` : '' }}
      </p>
    </div>

    <div v-if="openDisputeId" role="status" class="border-l-4 border-warning-mark bg-warning-bg px-4 py-3 text-sm text-warning-fg">
      {{ t('dispute.form.openExists') }}
      <RouterLink :to="{ name: 'dispute-detail', params: { id: openDisputeId } }" class="ml-1 font-medium underline underline-offset-4">
        {{ t('dispute.form.viewOpen') }}
      </RouterLink>
    </div>

    <template v-else>
      <div>
        <label for="dispute-value" class="block text-sm font-medium">{{ t('dispute.form.proposed') }} · {{ t(`dispute.figure.${kri}`) }}</label>
        <p id="dispute-value-hint" class="text-xs text-muted">{{ t('dispute.form.range', { max: maxText }) }}</p>
        <input
          id="dispute-value"
          v-model="proposed"
          type="number"
          inputmode="decimal"
          min="0"
          :max="figure.max"
          :step="figure.integer ? 1 : 0.01"
          class="mt-1 w-40 rounded-md border bg-surface px-3 py-2 tabular"
          :class="errors.proposed_value ? 'border-critical-mark' : 'border-control'"
          :aria-invalid="!!errors.proposed_value"
          :aria-describedby="`dispute-value-hint${errors.proposed_value ? ' dispute-value-error' : ''}`"
        />
        <p v-if="errors.proposed_value" id="dispute-value-error" class="mt-1 text-sm text-critical-fg">{{ errors.proposed_value[0] }}</p>
      </div>

      <div>
        <label for="dispute-reason" class="block text-sm font-medium">{{ t('dispute.form.reason') }}</label>
        <textarea
          id="dispute-reason"
          v-model="reason"
          rows="4"
          maxlength="2000"
          class="mt-1 w-full rounded-md border bg-surface px-3 py-2"
          :class="errors.reason ? 'border-critical-mark' : 'border-control'"
          :aria-invalid="!!errors.reason"
          :aria-describedby="errors.reason ? 'dispute-reason-error' : undefined"
        />
        <p v-if="errors.reason" id="dispute-reason-error" class="mt-1 text-sm text-critical-fg">{{ errors.reason[0] }}</p>
      </div>

      <div>
        <label for="dispute-files" class="block text-sm font-medium">{{ t('dispute.form.files') }}</label>
        <p id="dispute-files-hint" class="text-xs text-muted">{{ t('form.filesHint') }}</p>
        <input
          id="dispute-files"
          ref="fileInput"
          type="file"
          multiple
          :accept="ACCEPT"
          class="mt-1 block w-full text-sm file:mr-3 file:rounded-md file:border file:border-control file:bg-surface file:px-3 file:py-1.5 file:text-sm file:font-medium"
          :aria-invalid="!!errors.files?.length"
          :aria-describedby="`dispute-files-hint${errors.files?.length ? ' dispute-files-error' : ''}`"
          @change="onFiles"
        />
        <p v-if="errors.files?.length" id="dispute-files-error" class="mt-1 text-sm text-critical-fg">{{ errors.files.join(' ') }}</p>
      </div>

      <button
        type="submit"
        class="rounded-md bg-accent px-4 py-2.5 font-medium text-on-accent hover:bg-accent-hover disabled:opacity-60"
        :disabled="submitting"
      >
        {{ submitting ? t('common.sending') : t('dispute.form.submit') }}
      </button>
    </template>
  </form>
</template>
