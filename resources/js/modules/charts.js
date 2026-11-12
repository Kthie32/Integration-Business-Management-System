import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler, Legend,
    LinearScale, LineController, LineElement, PointElement, Tooltip,
);

const palette = ['#2563eb', '#16a34a', '#dc2626', '#d97706', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#475569', '#ea580c'];
const number = new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 });
const compact = new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 });

/**
 * Draw every <canvas data-chart='{"type","labels","datasets":[{"label","data"}],"money"}'> on the page.
 * The JSON comes from the server (report results and dashboard widgets); colours are assigned here.
 */
export function initCharts(root = document) {
    root.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        let config;

        try {
            config = JSON.parse(canvas.dataset.chart);
        } catch (error) {
            return;
        }

        const circular = config.type === 'doughnut';
        const horizontal = config.type === 'hbar';
        const type = horizontal ? 'bar' : config.type;

        const datasets = config.datasets.map((dataset, index) => {
            const color = palette[index % palette.length];

            return {
                ...dataset,
                backgroundColor: circular ? config.labels.map((_, i) => palette[i % palette.length]) : (type === 'line' ? `${color}22` : color),
                borderColor: circular ? '#fff' : color,
                borderWidth: circular ? 2 : (type === 'line' ? 2 : 0),
                fill: type === 'line' && config.datasets.length === 1,
                tension: 0.25,
                pointRadius: type === 'line' ? 2 : undefined,
                borderRadius: type === 'bar' ? 4 : undefined,
            };
        });

        const format = (value) => number.format(value);

        new Chart(canvas, {
            type,
            data: { labels: config.labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: horizontal ? 'y' : 'x',
                plugins: {
                    legend: { display: circular || config.datasets.length > 1, position: circular ? 'right' : 'top' },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = circular ? context.parsed : (horizontal ? context.parsed.x : context.parsed.y);
                                return `${context.dataset.label || context.label}: ${format(value)}`;
                            },
                        },
                    },
                },
                scales: circular ? {} : {
                    [horizontal ? 'x' : 'y']: { beginAtZero: true, ticks: { callback: (value) => compact.format(value) } },
                    [horizontal ? 'y' : 'x']: { grid: { display: false } },
                },
            },
        });
    });
}
