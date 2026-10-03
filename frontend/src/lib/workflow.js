import { t } from '../i18n/index.js'

/** Remediation workflow vocabulary. API values in English, labels in the UI language. */

const STATUS_TONES = {
  none: 'neutral',
  plan_review: 'info',
  plan_returned: 'warning',
  in_progress: 'neutral',
  evidence_review: 'info',
  evidence_returned: 'warning',
  closed: 'safe',
}

export const REMEDIATION_STATUS = Object.fromEntries(
  Object.entries(STATUS_TONES).map(([key, tone]) => [key, { tone, get label() { return t(`workflow.status.${key}`) } }]),
)

export function remediationInfo(issue) {
  if (issue.status === 'Closed') return REMEDIATION_STATUS.closed
  return REMEDIATION_STATUS[issue.remediation_status ?? 'none']
}

/** The four steps shown in the stepper, and how far a finding has come. */
export function steps() {
  return ['s1', 's2', 's3', 's4'].map((k) => t(`workflow.steps.${k}`))
}

export function completedSteps(issue) {
  if (issue.status === 'Closed') return 4
  return { plan_review: 1, plan_returned: 0, in_progress: 2, evidence_review: 3, evidence_returned: 2 }[issue.remediation_status] ?? 0
}

export const submissionType = (type) => t(`workflow.type.${type}`)

const SUBMISSION_TONES = { pending: 'info', approved: 'safe', returned: 'warning' }
export function submissionStatus(status) {
  return { tone: SUBMISSION_TONES[status], label: t(`workflow.submissionStatus.${status}`) }
}

/** Audit trail verb for an action code like "remediation.plan_approved". */
export function activityText(action) {
  const key = `workflow.activity.${action}`
  const text = t(key)
  return text === key ? action : text
}

export const TONE_CLASSES = {
  neutral: 'bg-sunken text-secondary',
  info: 'bg-accent-subtle text-accent',
  warning: 'bg-warning-bg text-warning-fg',
  safe: 'bg-safe-bg text-safe-fg',
  critical: 'bg-critical-bg text-critical-fg',
}
