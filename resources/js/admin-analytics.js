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

const CYAN = '#22d3ee';
const PURPLE = '#a78bfa';
const BLUE = '#38bdf8';
const PINK = '#f472b6';
const GREEN = '#34d399';
const AMBER = '#fbbf24';

const gridColor = 'rgba(148, 163, 184, 0.12)';
const tickColor = 'rgba(226, 232, 240, 0.65)';

function baseOptions(extra = {}) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: tickColor,
                    boxWidth: 12,
                    font: { size: 11 },
                },
            },
            tooltip: {
                backgroundColor: 'rgba(15, 23, 42, 0.95)',
                borderColor: CYAN,
                borderWidth: 1,
                titleColor: '#f8fafc',
                bodyColor: '#e2e8f0',
            },
        },
        scales: {
            x: {
                ticks: { color: tickColor, font: { size: 10 } },
                grid: { color: gridColor },
                border: { color: 'rgba(56, 189, 248, 0.25)' },
            },
            y: {
                ticks: { color: tickColor, font: { size: 10 } },
                grid: { color: gridColor },
                border: { color: 'rgba(56, 189, 248, 0.25)' },
                beginAtZero: true,
            },
        },
        ...extra,
    };
}

function createOutputByYearChart(canvas, data) {
    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Resolutions',
                    data: data.resolutions,
                    backgroundColor: 'rgba(34, 211, 238, 0.75)',
                    borderColor: CYAN,
                    borderWidth: 1,
                    borderRadius: 4,
                },
                {
                    label: 'Ordinances',
                    data: data.ordinances,
                    backgroundColor: 'rgba(167, 139, 250, 0.75)',
                    borderColor: PURPLE,
                    borderWidth: 1,
                    borderRadius: 4,
                },
            ],
        },
        options: baseOptions(),
    });
}

function createOutputByMonthChart(canvas, data) {
    return new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Resolutions',
                    data: data.resolutions,
                    borderColor: CYAN,
                    backgroundColor: 'rgba(34, 211, 238, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: CYAN,
                },
                {
                    label: 'Ordinances',
                    data: data.ordinances,
                    borderColor: PURPLE,
                    backgroundColor: 'rgba(167, 139, 250, 0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: PURPLE,
                },
                {
                    label: 'Total output',
                    data: data.totals,
                    borderColor: PINK,
                    borderDash: [6, 4],
                    fill: false,
                    tension: 0.35,
                    pointRadius: 2,
                    yAxisID: 'y',
                },
            ],
        },
        options: baseOptions(),
    });
}

function createComboChart(canvas, data) {
    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Total output',
                    data: data.totals,
                    backgroundColor: 'rgba(56, 189, 248, 0.55)',
                    borderColor: BLUE,
                    borderWidth: 1,
                    borderRadius: 4,
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Trend',
                    data: data.totals,
                    borderColor: '#fb7185',
                    backgroundColor: 'transparent',
                    tension: 0.35,
                    pointRadius: 4,
                    pointBackgroundColor: '#fb7185',
                    order: 1,
                },
            ],
        },
        options: baseOptions(),
    });
}

function createStatusDonut(canvas, data) {
    const fallbackColors = [CYAN, PURPLE, GREEN, AMBER, PINK, BLUE];

    return new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: data.colors?.length ? data.colors : fallbackColors,
                borderColor: 'rgba(15, 23, 42, 0.9)',
                borderWidth: 2,
                hoverOffset: 8,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: tickColor,
                        boxWidth: 10,
                        font: { size: 10 },
                        padding: 12,
                    },
                },
                tooltip: baseOptions().plugins.tooltip,
            },
        },
    });
}

function createCommitteeBars(canvas, data) {
    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Pending',
                    data: data.pending,
                    backgroundColor: 'rgba(251, 191, 36, 0.8)',
                    borderColor: AMBER,
                    borderWidth: 1,
                    borderRadius: 3,
                },
                {
                    label: 'Completed',
                    data: data.completed,
                    backgroundColor: 'rgba(52, 211, 153, 0.8)',
                    borderColor: GREEN,
                    borderWidth: 1,
                    borderRadius: 3,
                },
            ],
        },
        options: baseOptions({
            indexAxis: 'y',
            scales: {
                x: {
                    ticks: { color: tickColor, font: { size: 10 } },
                    grid: { color: gridColor },
                    border: { color: 'rgba(56, 189, 248, 0.25)' },
                    stacked: true,
                    beginAtZero: true,
                },
                y: {
                    ticks: { color: tickColor, font: { size: 10 } },
                    grid: { display: false },
                    border: { color: 'rgba(56, 189, 248, 0.25)' },
                    stacked: true,
                },
            },
        }),
    });
}

function createPipelineSparkline(canvas, data) {
    return new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: [
                    'rgba(251, 191, 36, 0.85)',
                    'rgba(249, 115, 22, 0.85)',
                    'rgba(52, 211, 153, 0.85)',
                    'rgba(56, 189, 248, 0.85)',
                ],
                borderRadius: 4,
            }],
        },
        options: baseOptions({
            plugins: { legend: { display: false } },
        }),
    });
}

export function initAdminAnalytics() {
    const root = document.getElementById('admin-analytics-dashboard');

    if (!root?.dataset.charts) {
        return;
    }

    const charts = JSON.parse(root.dataset.charts);
    const instances = [];

    const mount = (id, factory) => {
        const canvas = document.getElementById(id);

        if (!canvas) {
            return;
        }

        instances.push(factory(canvas));
    };

    mount('chart-output-year', (c) => createOutputByYearChart(c, charts.outputByYear));
    mount('chart-output-month', (c) => createOutputByMonthChart(c, charts.outputByMonth));
    mount('chart-output-combo', (c) => createComboChart(c, charts.outputByYear));
    mount('chart-status-donut', (c) => createStatusDonut(c, charts.statusDistribution));
    mount('chart-committee-bars', (c) => createCommitteeBars(c, charts.committeeRanking));
    mount('chart-pipeline-spark', (c) => createPipelineSparkline(c, charts.pipelineTrend));

    window.addEventListener('beforeunload', () => {
        instances.forEach((chart) => chart.destroy());
    });
}

document.addEventListener('DOMContentLoaded', initAdminAnalytics);
