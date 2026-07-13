import {
    Chart,
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

const BLUE = '#3b82f6';
const CYAN = '#22d3ee';
const GREEN = '#10b981';
const YELLOW = '#f59e0b';
const ORANGE = '#f97316';
const RED = '#ef4444';
const PURPLE = '#8b5cf6';
const PINK = '#ec4899';
const SLATE = '#94a3b8';
const GRID = 'rgba(148, 163, 184, 0.25)';
const TICK = '#64748b';

const tooltip = {
    backgroundColor: '#ffffff',
    borderColor: '#e2e8f0',
    borderWidth: 1,
    titleColor: '#0f172a',
    bodyColor: '#334155',
};

function baseOptions(extra = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: TICK,
                    boxWidth: 12,
                    font: { size: 11 },
                },
            },
            tooltip,
        },
        scales: {
            x: {
                ticks: { color: TICK, font: { size: 11 } },
                grid: { color: GRID },
            },
            y: {
                ticks: { color: TICK, font: { size: 11 } },
                grid: { color: GRID },
                beginAtZero: true,
            },
        },
        ...extra,
    };
}

function horizontalBarOptions(stacked = false) {
    return baseOptions({
        indexAxis: 'y',
        scales: {
            x: {
                ticks: { color: TICK, font: { size: 11 } },
                grid: { color: GRID },
                stacked,
                beginAtZero: true,
            },
            y: {
                ticks: { color: TICK, font: { size: 11 } },
                grid: { display: false },
                stacked,
            },
        },
    });
}

function donutOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '62%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: TICK,
                    boxWidth: 10,
                    font: { size: 11 },
                    padding: 12,
                },
            },
            tooltip,
        },
    };
}

function labelsFromRows(rows, key = 'label') {
    return (rows ?? []).map((row) => row[key] ?? '');
}

function valuesFromRows(rows, key = 'value') {
    return (rows ?? []).map((row) => Number(row[key] ?? 0));
}

function monthsFromRows(rows) {
    return (rows ?? []).map((row) => row.month ?? '');
}

function withMinimum(values) {
    if (!values.length || values.some((value) => value > 0)) {
        return values;
    }

    return values.map(() => 0);
}

function createDonut(canvas, labels, values, colors) {
    return new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: withMinimum(values),
                backgroundColor: colors,
                borderColor: '#ffffff',
                borderWidth: 2,
                hoverOffset: 6,
            }],
        },
        options: donutOptions(),
    });
}

function createLine(canvas, labels, datasets) {
    return new Chart(canvas, {
        type: 'line',
        data: { labels, datasets },
        options: baseOptions(),
    });
}

function createBar(canvas, labels, datasets, horizontal = false, stacked = false) {
    return new Chart(canvas, {
        type: 'bar',
        data: { labels, datasets },
        options: horizontal ? horizontalBarOptions(stacked) : baseOptions({
            scales: {
                x: { stacked, ticks: { color: TICK }, grid: { color: GRID } },
                y: { stacked, ticks: { color: TICK }, grid: { color: GRID }, beginAtZero: true },
            },
        }),
    });
}

function lineDataset(label, data, color, fill = false, dashed = false) {
    return {
        label,
        data,
        borderColor: color,
        backgroundColor: fill ? `${color}22` : 'transparent',
        fill,
        tension: 0.35,
        pointRadius: 3,
        pointBackgroundColor: color,
        borderDash: dashed ? [6, 4] : [],
    };
}

function readChartPayload() {
    const script = document.getElementById('admin-analytics-data');

    if (script?.textContent) {
        return JSON.parse(script.textContent);
    }

    const root = document.getElementById('admin-analytics-dashboard');

    if (root?.dataset.charts) {
        return JSON.parse(root.dataset.charts);
    }

    return null;
}

export function initAdminAnalytics() {
    if (window.__splisAdminAnalyticsInitialized) {
        return;
    }

    const data = readChartPayload();

    if (!data) {
        return;
    }

    window.__splisAdminAnalyticsInitialized = true;

    const instances = [];

    const mount = (id, factory) => {
        const canvas = document.getElementById(id);

        if (!canvas) {
            return;
        }

        try {
            const chart = factory(canvas);

            if (chart) {
                instances.push(chart);
            }
        } catch (error) {
            console.error(`Failed to render chart ${id}`, error);
        }
    };

    const agenda = data.agenda ?? {};
    const resolutions = data.resolutions ?? {};
    const sources = data.sources ?? {};
    const legislativeOutput = data.legislative_output ?? {};

    mount('chart-legislative-output-year', (c) => createBar(
        c,
        (legislativeOutput.by_year ?? []).map((row) => String(row.year)),
        [
            {
                label: 'Resolutions',
                data: (legislativeOutput.by_year ?? []).map((row) => Number(row.resolutions ?? 0)),
                backgroundColor: CYAN,
                borderRadius: 4,
            },
            {
                label: 'Ordinances (incl. Appropriation)',
                data: (legislativeOutput.by_year ?? []).map((row) => Number(row.ordinances ?? 0)),
                backgroundColor: PURPLE,
                borderRadius: 4,
            },
        ],
        false,
        false,
    ));

    const monthlyOutput = legislativeOutput.by_month ?? [];
    mount('chart-legislative-output-month', (c) => createLine(c, monthlyOutput.map((row) => row.month), [
        lineDataset('Resolutions', monthlyOutput.map((row) => Number(row.resolutions ?? 0)), CYAN, true),
        lineDataset('Ordinances (incl. Appropriation)', monthlyOutput.map((row) => Number(row.ordinances ?? 0)), PURPLE, true),
        lineDataset('Total output', monthlyOutput.map((row) => Number(row.total ?? 0)), PINK, false, true),
    ]));

    mount('chart-agenda-status', (c) => createDonut(
        c,
        labelsFromRows(agenda.status_distribution),
        valuesFromRows(agenda.status_distribution),
        (agenda.status_distribution ?? []).map((row) => row.color ?? SLATE),
    ));

    const intake = agenda.monthly_intake_comparison ?? {};
    mount('chart-agenda-intake', (c) => createLine(c, intake.labels ?? monthsFromRows(agenda.monthly_intake), [
        lineDataset(String(intake.previous_year ?? 'Previous'), intake.previous ?? [], SLATE),
        lineDataset(String(intake.current_year ?? 'Current'), intake.current ?? valuesFromRows(agenda.monthly_intake), BLUE, true),
    ]));

    const dueHealth = agenda.due_date_health ?? {};
    mount('chart-due-date-health', (c) => createBar(c,
        ['Safe (>15 days)', 'Near Due (7-15)', 'Critical (1-7)', 'Overdue'],
        [{
            label: 'Pending items',
            data: [dueHealth.safe ?? 0, dueHealth.near_due ?? 0, dueHealth.critical ?? 0, dueHealth.overdue ?? 0],
            backgroundColor: [GREEN, YELLOW, ORANGE, RED],
            borderRadius: 6,
        }],
    ));

    mount('chart-agenda-aging', (c) => createBar(
        c,
        labelsFromRows(agenda.aging),
        [{ label: 'Pending', data: valuesFromRows(agenda.aging), backgroundColor: BLUE, borderRadius: 6 }],
        true,
    ));

    const grouped = sources.grouped ?? { labels: sources.labels ?? [], values: sources.values ?? [] };
    mount('chart-source-senders', (c) => createBar(
        c,
        grouped.labels ?? [],
        [{ label: 'Requests', data: grouped.values ?? [], backgroundColor: BLUE, borderRadius: 6 }],
        true,
    ));

    mount('chart-resolution-trend', (c) => createLine(c, monthsFromRows(resolutions.monthly_approved), [
        lineDataset('Resolutions approved', valuesFromRows(resolutions.monthly_approved), GREEN, true),
    ]));

    mount('chart-resolution-categories', (c) => createDonut(
        c,
        labelsFromRows(resolutions.categories),
        valuesFromRows(resolutions.categories),
        [BLUE, GREEN, YELLOW, ORANGE, PURPLE, SLATE],
    ));

    mount('chart-committee-workload', (c) => createBar(
        c,
        labelsFromRows(resolutions.committees),
        [{ label: 'Resolutions', data: valuesFromRows(resolutions.committees), backgroundColor: PURPLE, borderRadius: 6 }],
        true,
    ));

    window.addEventListener('beforeunload', () => {
        instances.forEach((chart) => chart.destroy());
        window.__splisAdminAnalyticsInitialized = false;
    });
}
