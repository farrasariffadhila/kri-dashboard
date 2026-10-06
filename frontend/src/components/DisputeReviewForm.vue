<script setup>
import { useI18n } from 'vue-i18n'
import { computed, ref } from 'vue'
import { ApiError, reviewDispute } from '../lib/api.js'

const { t } = useI18n()

/** IT Risk decision on a pending dispute: accept with the figure to use, or reject with a reason. */
const props = defineProps({
  dispute: { type: Object, required: true },
  baseline: { type: Object, required: true }, // { max, integer } from the API
})
const emit = defineEmits(['reviewed'])

const decision = ref('')
const corrected = ref(String(props.dispute.proposed_value))
const note = ref('')
const errors = ref({})
const formError = ref('')
const submitting = ref(false)

const submitLabel = computed(() => {
  if (decision.value === 'accept') return t('dispute.review.submitAccept')
  if (decision.value === 'reject') return t('dispute.review.submitReject')
  return t('dispute.review.submit')
})

async function submit() {
  if (submitting.value) return
  formError.value = ''
  errors.value = {}
  if (!decision.value) {
    formError.value = t('dispute.review.errors.decision')
    return
  }
  if (decision.value === 'reject' && !note.value.trim()) {
    errors.value = { note: [t('dispute.review.errors.note')] }
    return
  }

  submitting.value = true
  try {
    await reviewDispute(props.dispute.id, {
      decision: decision.value,
      corrected_value: decision.value === 'accept' ? String(corrected.value).replace(',', '.') : null,
      note: note.value.trim() || null,
    })
    emit('reviewed')
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) errors.value = e.fieldErrors
    else formError.value = e instanceof ApiError && e.body?.message ? e.body.message : t('dispute.review.errors.sendFailed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <div v-if="formError" role="alert" class="border-l-4 border-critical-mark bg-critical-bg px-4 py-3 text-sm text-critical-fg">{{ formError }}</div>

    <fieldset>
      <legend class="text-sm font-medium">{{ t('dispute.review.decision') }}</legend>
      <div class="mt-2 flex flex-wrap gap-3">
        <label
          v-for="option in ['accept', 'reject']"
          :key="option"
          class="flex items-center gap-2 rounded-md border border-control px-3 py-2 text-sm has-checked:border-accent has-checked:bg-accent-subtle"
        >
          <input v-model="decision" type="radio" :value="option" class="accent-accent" />
          {{ t(`dispute.review.${option}`) }}
        </label>
      </div>
    </fieldset>

    <div v-if="decision === 'accept'">
      <label for="dispute-corrected" class="block text-sm font-medium">{{ t('dispute.review.corrected') }}</label>
      <input
        id="dispute-corrected"
        v-model="corrected"
        type="number"
        inputmode="decimal"
        min="0"
        :max="baseline.max"
        :step="baseline.integer ? 1 : 0.01"
        class="mt-1 w-40 rounded-md border bg-surface px-3 py-2 tabular"
        :class="errors.corrected_value ? 'border-critical-mark' : 'border-control'"
        :aria-invalid="!!errors.corrected_value"
        :aria-describedby="errors.corrected_value ? 'dispute-corrected-error' : undefined"
      />
      <p v-if="errors.corrected_value" id="dispute-corrected-error" class="mt-1 text-sm text-critical-fg">{{ errors.corrected_value[0] }}</p>
    </div>

    <div>
      <label for="dispute-note" class="block text-sm font-medium">
        {{ t('dispute.review.note') }} <span class="font-normal text-muted">{{ decision === 'reject' ? t('dispute.review.required') : t('dispute.review.optional') }}</span>
      </label>
      <textarea
        id="dispute-note"
        v-model="note"
        rows="3"
        maxlength="2000"
        class="mt-1 w-full rounded-md border bg-surface px-3 py-2"
        :class="errors.note ? 'border-critical-mark' : 'border-control'"
        :aria-invalid="!!errors.note"
        :aria-describedby="errors.note ? 'dispute-note-error' : undefined"
      />
      <p v-if="errors.note" id="dispute-note-error" class="mt-1 text-sm text-critical-fg">{{ errors.note[0] }}</p>
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
