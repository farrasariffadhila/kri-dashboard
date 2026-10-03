import { t } from '../i18n/index.js'
import { formatNumber, formatPercent } from './format.js'

/** KRI data dispute vocabulary. Figures are in each KRI's raw unit, as the API stores them. */

export const DISPUTABLE_KRIS = ['unpatched_servers', 'uptime', 'phishing_failure']
export const UNITS = { unpatched_servers: 'servers', uptime: 'percent', phishing_failure: 'employees' }

export function formatFigure(unit, value) {
  if (value == null) return '–'
  if (unit === 'percent') return formatPercent(value, 2)
  return t(`dispute.units.${unit}`, { count: formatNumber(value) })
}

/**
 * The disputable figure of a department KRI from the risk summary, with the valid range.
 * Mirrors DepartmentRiskService::disputeBaseline on the server, which re-validates.
 */
export function figureOf(kriKey, kri) {
  if (kriKey === 'unpatched_servers') return { value: kri.non_compliant, max: kri.total_servers, integer: true }
  if (kriKey === 'phishing_failure') return { value: kri.total_failed, max: kri.total_tested, integer: true }
  return { value: kri.value, max: 100, integer: false }
}

const TONES = { pending: 'info', accepted: 'safe', rejected: 'warning' }
export function disputeStatus(status) {
  return { tone: TONES[status], label: t(`dispute.status.${status}`) }
}
