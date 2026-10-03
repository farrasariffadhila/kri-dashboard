import { t } from '../i18n/index.js'
import { formatNumber } from './format.js'

/** Same limits as AttachmentStore on the server, checked early for a friendlier message. */
export const MAX_FILES = 3
const MAX_BYTES = 5 * 1024 * 1024
const ALLOWED = ['application/pdf', 'image/png', 'image/jpeg']
export const ACCEPT = '.pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg'

/** Problems with a picked file list, as translated messages (empty when fine). */
export function fileProblems(files) {
  const problems = []
  if (files.length > MAX_FILES) problems.push(t('form.errors.tooMany', { max: MAX_FILES }))
  if (files.some((f) => f.size > MAX_BYTES)) problems.push(t('form.errors.tooLarge'))
  if (files.some((f) => !ALLOWED.includes(f.type))) problems.push(t('form.errors.wrongType'))
  return problems
}

/** Fold Laravel's "files.0" style keys into "files". */
export function foldFileErrors(fieldErrors) {
  const out = {}
  for (const [key, messages] of Object.entries(fieldErrors)) {
    const k = key.startsWith('files') ? 'files' : key
    out[k] = [...(out[k] ?? []), ...messages]
  }
  return out
}

export function sizeText(bytes) {
  return bytes >= 1024 * 1024 ? `${formatNumber(bytes / 1024 / 1024, 1)} MB` : `${formatNumber(Math.ceil(bytes / 1024))} KB`
}
