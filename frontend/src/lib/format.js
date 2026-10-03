// Number and date formatting in the UI language. Always go through Intl, never format by hand.
import { intlLocale, t } from '../i18n/index.js'

const formatters = new Map()
function formatter(key, options) {
  const locale = intlLocale() // read on every call, so templates re-render when the language changes
  const cacheKey = `${locale}:${key}`
  if (!formatters.has(cacheKey)) formatters.set(cacheKey, new Intl.NumberFormat(locale, options))
  return formatters.get(cacheKey)
}

export function formatNumber(value, digits = 0) {
  if (value == null) return '–'
  return formatter(`n${digits}`, { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(value)
}

/** 34.87 -> "34,9%" (id) / "34.9%" (en) */
export function formatPercent(value, digits = 1) {
  if (value == null) return '–'
  return formatter(`p${digits}`, {
    style: 'percent',
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(value / 100)
}

export function formatScore(value) {
  return formatNumber(value, 1)
}

/** "2026-09" -> Date for the first day of that month (local time, no UTC shift). */
function monthToDate(month) {
  const [year, m] = month.split('-').map(Number)
  return new Date(year, m - 1, 1)
}

/** "2026-09" -> "September 2026" */
export function formatMonthLong(month) {
  return new Intl.DateTimeFormat(intlLocale(), { month: 'long', year: 'numeric' }).format(monthToDate(month))
}

/** "2026-09" -> "Sep" */
export function formatMonthShort(month) {
  return new Intl.DateTimeFormat(intlLocale(), { month: 'short' }).format(monthToDate(month))
}

/** "2026-09-30" -> "30 September 2026" (parsed as a local date). */
export function formatDate(isoDate, month = 'long') {
  if (!isoDate) return '–'
  const [y, m, d] = isoDate.split('-').map(Number)
  return new Intl.DateTimeFormat(intlLocale(), { day: 'numeric', month, year: 'numeric' }).format(new Date(y, m - 1, d))
}

/** 2786 -> "46 jam 26 menit" / "46 h 26 min" */
export function formatDuration(minutes) {
  if (minutes == null) return '–'
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  if (h === 0) return t('units.minutes', { m })
  return m === 0 ? t('units.hours', { h: formatNumber(h) }) : t('units.hoursMinutes', { h: formatNumber(h), m })
}

/** ISO timestamp -> "3 Okt 2026 19.03" (local time). */
export function formatDateTime(iso) {
  if (!iso) return '–'
  return new Intl.DateTimeFormat(intlLocale(), { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(iso))
}
