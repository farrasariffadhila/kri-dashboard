<script setup>
import { useI18n } from 'vue-i18n'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { CategoryScale, Chart, LinearScale, LineController, LineElement, PointElement, Tooltip } from 'chart.js'
import { KRIS, STATUS, statusFromRating } from '../lib/kri.js'
import { formatMonthLong, formatMonthShort, formatScore } from '../lib/format.js'
import { cssVar } from '../lib/theme.js'
import StatusBadge from './StatusBadge.vue'

const { t, locale } = useI18n()

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip)

const props = defineProps({
  history: { type: Object, required: true }, // response of /historical-risk
  bands: { type: Object, required: true },
  themeVersion: { type: Number, default: 0 },
})

const canvas = ref(null)
const showTable = ref(false)
let chart = null

const fontFamily = () => cssVar('--font-sans') || '"IBM Plex Sans", Arial, sans-serif'

const points = computed(() => props.history.data)

const summaryText = computed(() => {
  const first = points.value[0]
  const last = points.value.at(-1)
  return t('trend.summary', {
    name: props.history.department.name,
    from: formatMonthLong(first.month),
    to: formatMonthLong(last.month),
    start: formatScore(first.composite_score),
    end: formatScore(last.composite_score),
    status: STATUS[statusFromRating(last.rating)]?.label ?? t('common.noData'),
  })
})

/** Background zones so every point is read against the Safe / Warning / Critical bands. */
const bandsPlugin = {
  id: 'riskBands',
  beforeDatasetsDraw(c) {
    const { ctx, chartArea: area, scales } = c
    const zones = [
      [0, props.bands.warning, 'safe'],
      [props.bands.warning, props.bands.critical, 'warning'],
      [props.bands.critical, 100, 'critical'],
    ]
    ctx.save()
    ctx.font = `500 12px ${fontFamily()}`
    // Zone labels sit on the left: risk trends usually start low there, so the line rarely crosses them.
    ctx.textAlign = 'left'
    ctx.textBaseline = 'top'
    for (const [from, to, status] of zones) {
      const top = scales.y.getPixelForValue(to)
      const bottom = scales.y.getPixelForValue(from)
      ctx.fillStyle = cssVar(`--ui-${status}-bg`)
      ctx.fillRect(area.left, top, area.right - area.left, bottom - top)
      ctx.fillStyle = cssVar(`--ui-${status}-fg`)
      ctx.fillText(STATUS[status].label, area.left + 8, top + 6)
    }
    ctx.restore()
  },
}

/** Value label on the last point only (selective labelling). */
const endLabelPlugin = {
  id: 'endLabel',
  afterDatasetsDraw(c) {
    const meta = c.getDatasetMeta(0)
    const last = meta.data.at(-1)
    if (!last) return
    const { ctx } = c
    ctx.save()
    ctx.font = `600 14px ${fontFamily()}`
    ctx.fillStyle = cssVar('--ui-text')
    ctx.textAlign = 'left'
    ctx.textBaseline = 'middle'
    ctx.fillText(formatScore(points.value.at(-1).composite_score), last.x + 10, last.y)
    ctx.restore()
  },
}

/** Vertical hairline at the hovered month. */
const crosshairPlugin = {
  id: 'crosshair',
  afterDatasetsDraw(c) {
    const active = c.tooltip?.getActiveElements?.()
    if (!active?.length) return
    const x = active[0].element.x
    const { ctx, chartArea: area } = c
    ctx.save()
    ctx.strokeStyle = cssVar('--ui-text-muted')
    ctx.lineWidth = 1
    ctx.beginPath()
    ctx.moveTo(x, area.top)
    ctx.lineTo(x, area.bottom)
    ctx.stroke()
    ctx.restore()
  },
}

function render() {
  chart?.destroy()
  const font = { family: fontFamily(), size: 12 }
  const ink = cssVar('--ui-chart-line')
  const surface = cssVar('--ui-surface')

  chart = new Chart(canvas.value, {
    type: 'line',
    data: {
      labels: points.value.map((p) => formatMonthShort(p.month)),
      datasets: [
        {
          data: points.value.map((p) => p.composite_score),
          borderColor: ink,
          borderWidth: 2,
          borderCapStyle: 'round',
          borderJoinStyle: 'round',
          pointRadius: 4,
          pointHoverRadius: 5,
          pointBackgroundColor: ink,
          pointBorderColor: surface,
          pointBorderWidth: 2,
          pointHitRadius: 16,
          tension: 0,
        },
      ],
    },
    plugins: [bandsPlugin, crosshairPlugin, endLabelPlugin],
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      layout: { padding: { top: 8, right: 44 } },
      interaction: { mode: 'index', intersect: false },
      scales: {
        y: {
          min: 0,
          max: 100,
          ticks: { stepSize: 25, color: cssVar('--ui-chart-axis'), font, padding: 8 },
          grid: { color: cssVar('--ui-chart-grid'), lineWidth: 1, drawTicks: false },
          border: { display: false },
          title: { display: true, text: t('trend.yAxis'), color: cssVar('--ui-chart-axis'), font },
        },
        x: {
          ticks: { color: cssVar('--ui-chart-axis'), font, padding: 8 },
          grid: { display: false },
          border: { color: cssVar('--ui-border') },
        },
      },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: surface,
          borderColor: cssVar('--ui-border'),
          borderWidth: 1,
          titleColor: cssVar('--ui-text-secondary'),
          bodyColor: cssVar('--ui-text'),
          titleFont: { ...font, weight: '500' },
          bodyFont: { ...font, size: 14, weight: '600' },
          padding: 10,
          cornerRadius: 4,
          displayColors: false,
          callbacks: {
            title: (items) => formatMonthLong(points.value[items[0].dataIndex].month),
            label: (item) => {
              const p = points.value[item.dataIndex]
              return t('trend.tooltip', { score: formatScore(p.composite_score), status: STATUS[statusFromRating(p.rating)]?.label ?? '' })
            },
          },
        },
      },
    },
  })
}

onMounted(() => {
  render()
  // Canvas text does not reflow when web fonts arrive, so draw again once they are ready.
  document.fonts?.ready.then(() => chart && render())
})
// Canvas text is drawn once, so redraw when the data, the theme or the language changes.
watch(() => [props.history, props.themeVersion, locale.value], render)
onBeforeUnmount(() => chart?.destroy())
</script>

<template>
  <div>
    <!-- Fixed height includes the x-axis band, so nothing is clipped and the layout never shifts. -->
    <div class="relative h-80">
      <canvas ref="canvas" role="img" :aria-label="summaryText" />
    </div>

    <button
      type="button"
      class="mt-3 py-1.5 text-sm font-medium text-accent underline underline-offset-4 hover:text-accent-hover"
      :aria-expanded="showTable"
      aria-controls="trend-table"
      @click="showTable = !showTable"
    >
      {{ showTable ? t('common.hideTable') : t('common.showTable') }}
    </button>

    <div v-show="showTable" id="trend-table" class="mt-4 relative overflow-x-auto" tabindex="0" role="region" :aria-label="t('trend.tableAria')">
      <table class="w-full min-w-176 border-collapse text-sm">
        <caption class="pb-2 text-left text-xs text-muted">
          {{ t('trend.tableCaption', { name: history.department.name }) }}
        </caption>
        <thead>
          <tr class="border-b border-border text-left text-xs font-medium text-secondary">
            <th scope="col" class="py-2 pr-4">{{ t('trend.month') }}</th>
            <th scope="col" class="px-3 py-2 text-right">{{ t('trend.score') }}</th>
            <th scope="col" class="px-3 py-2">{{ t('trend.status') }}</th>
            <th v-for="kri in KRIS" :key="kri.key" scope="col" class="px-3 py-2 text-right">{{ kri.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in points" :key="p.month" class="border-b border-hairline">
            <th scope="row" class="py-2.5 pr-4 text-left font-normal whitespace-nowrap">{{ formatMonthLong(p.month) }}</th>
            <td class="px-3 py-2.5 text-right font-semibold tabular">{{ formatScore(p.composite_score) }}</td>
            <td class="px-3 py-2.5"><StatusBadge size="sm" :status="statusFromRating(p.rating)" /></td>
            <td v-for="kri in KRIS" :key="kri.key" class="px-3 py-2.5 text-right tabular">{{ kri.format(p.kris[kri.key].value) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
