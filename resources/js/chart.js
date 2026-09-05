/**
 * Chart.js wrapper behind <x-chart> (context.md §7.10). Charts fetch their own
 * data with a loading state; data is never embedded in the HTML.
 *
 * Only the controllers actually used are registered — the whole `chart.js/auto`
 * bundle would pull in every chart type the CMS does not draw.
 */
import {
    Chart,
    LineController,
    BarController,
    DoughnutController,
    LineElement,
    PointElement,
    BarElement,
    ArcElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js'

Chart.register(
    LineController, BarController, DoughnutController,
    LineElement, PointElement, BarElement, ArcElement,
    CategoryScale, LinearScale, Tooltip, Legend, Filler
)

// Teal-led palette matching the design tokens (context.md §7).
const PALETTE = ['#0d9488', '#f59e0b', '#64748b', '#059669', '#dc2626', '#14b8a6']

Chart.defaults.font.family =
    'Figtree, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif'
Chart.defaults.color = '#64748b'
Chart.defaults.borderColor = '#e2e8f0'

const withAlpha = (hex, alpha) => {
    const a = Math.round(alpha * 255).toString(16).padStart(2, '0')
    return `${hex}${a}`
}

const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: { usePointStyle: true, boxWidth: 8, padding: 16 },
        },
        tooltip: {
            backgroundColor: '#0f172a',
            padding: 10,
            cornerRadius: 6,
            displayColors: true,
            boxPadding: 4,
        },
    },
}

const scaleOptions = {
    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
    y: { beginAtZero: true, border: { display: false }, ticks: { precision: 0 } },
}

/** Applies the palette so callers only send labels and raw numbers. */
const paint = (type, datasets) =>
    datasets.map((set, i) => {
        const color = set.color ?? PALETTE[i % PALETTE.length]

        if (type === 'doughnut') {
            return {
                ...set,
                backgroundColor: set.data.map((_, j) => PALETTE[j % PALETTE.length]),
                borderColor: '#ffffff',
                borderWidth: 2,
            }
        }

        if (type === 'bar') {
            return { ...set, backgroundColor: withAlpha(color, 0.85), borderRadius: 4, borderSkipped: false }
        }

        return {
            ...set,
            borderColor: color,
            backgroundColor: withAlpha(color, 0.12),
            fill: true,
            tension: 0.35,
            pointRadius: 0,
            pointHoverRadius: 4,
            borderWidth: 2,
        }
    })

export function createChart(canvas, type, payload) {
    return new Chart(canvas, {
        type,
        data: {
            labels: payload.labels ?? [],
            datasets: paint(type, payload.datasets ?? []),
        },
        options: {
            ...baseOptions,
            ...(type === 'doughnut'
                ? { cutout: '65%' }
                : { scales: scaleOptions }),
            plugins: {
                ...baseOptions.plugins,
                legend: {
                    ...baseOptions.plugins.legend,
                    display: type === 'doughnut' || (payload.datasets ?? []).length > 1,
                },
            },
        },
    })
}

/**
 * Alpine component behind <x-chart>. Same four states as every other fetch
 * (context.md §2.3) — a chart that silently stays blank is a bug.
 */
export default (endpoint, type = 'line') => ({
    endpoint,
    type,
    chart: null,
    loading: false,
    error: null,
    isEmpty: false,

    init() {
        this.load()
        this.$watch('endpoint', () => this.load())
    },

    async load() {
        this.loading = true
        this.error = null

        try {
            const res = await window.api.get(this.endpoint)
            const payload = res.data ?? res

            this.isEmpty = !(payload.datasets ?? []).some((d) => (d.data ?? []).length > 0)

            this.chart?.destroy()
            this.chart = this.isEmpty ? null : createChart(this.$refs.canvas, this.type, payload)
        } catch (e) {
            this.error = e.message
            this.chart?.destroy()
            this.chart = null
        } finally {
            this.loading = false
        }
    },

    destroy() {
        this.chart?.destroy()
        this.chart = null
    },
})
