import { formatNumber, formatPercent } from './format.js'
import { t } from '../i18n/index.js'

/**
 * Display metadata for each KRI, in the column order used across the dashboard.
 * Text fields are getters, so they always return the current UI language.
 */
function kri(key, extra) {
  return {
    key,
    get label() {
      return t(`kri.${key}.label`)
    },
    get description() {
      return t(`kri.${key}.description`)
    },
    get formula() {
      return t(`kri.${key}.formula`)
    },
    ...extra,
  }
}

export const KRIS = [
  kri('unpatched_servers', {
    higherIsWorse: true,
    series: 'var(--ui-series-1)',
    format: (v) => formatPercent(v, 1),
    cause: (v) => t('kri.unpatched_servers.cause', { value: formatPercent(v, 1) }),
    delta: (d) => t('common.points', { value: formatNumber(Math.abs(d), 1) }),
  }),
  kri('open_high_audits', {
    higherIsWorse: true,
    series: 'var(--ui-series-2)',
    format: (v) => formatNumber(v),
    cause: (v) => t('kri.open_high_audits.cause', { value: formatNumber(v) }),
    delta: (d) => t('common.findings', { value: formatNumber(Math.abs(d)) }),
  }),
  kri('uptime', {
    higherIsWorse: false,
    series: 'var(--ui-series-3)',
    format: (v) => formatPercent(v, 2),
    cause: (v) => t('kri.uptime.cause', { value: formatPercent(v, 2) }),
    delta: (d) => t('common.points', { value: formatNumber(Math.abs(d), 2) }),
  }),
  kri('phishing_failure', {
    higherIsWorse: true,
    series: 'var(--ui-series-4)',
    format: (v) => formatPercent(v, 1),
    cause: (v) => t('kri.phishing_failure.cause', { value: formatPercent(v, 1) }),
    delta: (d) => t('common.points', { value: formatNumber(Math.abs(d), 1) }),
  }),
]

export const KRI_BY_KEY = Object.fromEntries(KRIS.map((k) => [k.key, k]))

/** Status vocabulary. The API speaks English codes; labels come from the UI language. */
function status(key) {
  return {
    get label() {
      return t(`status.${key}.label`)
    },
    get range() {
      return t(`status.${key}.range`)
    },
  }
}
export const STATUS = { critical: status('critical'), warning: status('warning'), safe: status('safe') }

const RATING_TO_STATUS = { Critical: 'critical', Warning: 'warning', Safe: 'safe' }

export function statusFromRating(rating) {
  return RATING_TO_STATUS[rating] ?? null
}

/** Same bands as the composite score, applied to a single KRI score. */
export function statusFromScore(score, bands) {
  if (score == null) return null
  if (score > bands.critical) return 'critical'
  if (score >= bands.warning) return 'warning'
  return 'safe'
}

/**
 * Share of the composite score contributed by each KRI, largest first.
 * Mirrors the backend: KRIs without data are excluded and weights re-normalised.
 * Spreading the KRI keeps its getters live (label/description stay translatable).
 */
export function contributions(kris) {
  const available = KRIS.filter((k) => kris[k.key]?.score != null)
  const weightSum = available.reduce((sum, k) => sum + kris[k.key].weight, 0)
  return available
    .map((k) => Object.assign(Object.create(k), kris[k.key], { contribution: (kris[k.key].score * kris[k.key].weight) / weightSum }))
    .sort((a, b) => b.contribution - a.contribution)
}

/** Audit risk levels: API values in English, labels in the UI language. */
const TONES = { High: 'critical', Medium: 'warning', Low: null }
export const RISK_LEVELS = Object.fromEntries(
  Object.entries(TONES).map(([level, tone]) => [level, { tone, get label() { return t(`risk.${level}`) } }]),
)

export function auditStatusLabel(status) {
  return t(`auditStatus.${status}`)
}

export function roleLabel(role) {
  return role ? t(`roles.${role}`) : ''
}

/**
 * Month-on-month change of one KRI. Whether a change is worse depends on the KRI's direction
 * (falling uptime is a deterioration), not on the score, which saturates at 0 and 100.
 */
export function kriChange(kri, current, previous) {
  if (current?.value == null || previous?.value == null) return null
  const diff = current.value - previous.value
  if (Math.abs(diff) < 1e-9) return { direction: 'flat', trend: 'flat', text: t('common.unchanged') }
  const worse = kri.higherIsWorse ? diff > 0 : diff < 0
  return {
    direction: diff > 0 ? 'up' : 'down',
    trend: worse ? 'worse' : 'better',
    text: t(diff > 0 ? 'common.up' : 'common.down', { amount: kri.delta(diff) }),
  }
}
