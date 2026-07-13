function formatMapValue(value) {
    return Number(value ?? 0).toLocaleString();
}

function metricForMeasure(measure) {
    if (measure === 'agendas' || measure === 'resolutions') {
        return measure;
    }

    return 'total';
}

function applyPoliticalMap(mapWrap, measure) {
    const metric = metricForMeasure(measure);
    const regions = Array.from(mapWrap.querySelectorAll('[data-geo-region]'));
    const values = regions.map((region) => Number(region.dataset[metric] ?? region.dataset.total ?? 0));
    const max = Math.max(1, ...values);

    regions.forEach((region, index) => {
        const value = values[index] ?? 0;
        const intensity = Math.max(0.12, value / max);
        const agendas = Number(region.dataset.agendas ?? 0);
        const resolutions = Number(region.dataset.resolutions ?? 0);

        region.style.setProperty('--geo-intensity', intensity.toFixed(3));
        region.title = `${region.dataset.name ?? ''}: ${agendas} agenda(s), ${resolutions} resolution(s)`;

        const valueEl = region.nextElementSibling?.querySelector?.('[data-geo-region-value]');
        if (valueEl) {
            valueEl.textContent = formatMapValue(value);
        }
    });
}

function updateMapFromPayload(mapWrap, payload) {
    const bySlug = Object.fromEntries((payload.municipalities ?? []).map((row) => [row.slug, row]));

    mapWrap.querySelectorAll('[data-geo-region]').forEach((region) => {
        const municipality = bySlug[region.dataset.slug] ?? {};

        region.dataset.agendas = String(municipality.agendas ?? 0);
        region.dataset.resolutions = String(municipality.resolutions ?? 0);
        region.dataset.total = String(municipality.total ?? 0);
    });

    applyPoliticalMap(mapWrap, payload.measure ?? 'both');
}

function readMapFilters(root) {
    const committeeId = root.querySelector('[data-map-filter="committee_id"]')?.value ?? '';
    const year = root.querySelector('[data-map-filter="year"]')?.value ?? '';
    const month = root.querySelector('[data-map-filter="month"]')?.value ?? '';
    const measure = root.querySelector('[data-map-filter="measure"]')?.value ?? 'both';

    return { committeeId, year, month, measure };
}

function updateMapSubtitle(root, payload) {
    const subtitle = root.querySelector('[data-map-subtitle]');

    if (!subtitle) {
        return;
    }

    const committee = payload?.committee || 'All committees';
    const measureLabel = {
        both: 'agendas and resolutions',
        agendas: 'agendas',
        resolutions: 'resolutions',
    }[payload?.measure ?? 'both'] ?? 'items';

    subtitle.textContent = `${committee} · ${payload?.period_label ?? ''} · ${formatMapValue(payload?.total ?? 0)} ${measureLabel}`;
}

export function initCommitteeMunicipalityMap() {
    const root = document.getElementById('committee-municipality-map');

    if (!root) {
        return;
    }

    const mapWrap = root.querySelector('.splis-bataan-map-wrap');
    const mapUrl = root.dataset.mapUrl;
    let debounceTimer;

    if (!mapWrap || !mapUrl) {
        return;
    }

    const renderFromFilters = async () => {
        const { committeeId, year, month, measure } = readMapFilters(root);

        const params = new URLSearchParams({
            year: year || String(new Date().getFullYear()),
            measure,
        });

        if (committeeId !== '') {
            params.set('committee_id', committeeId);
        }

        if (month !== '') {
            params.set('month', month);
        }

        root.classList.add('is-loading');

        try {
            const response = await fetch(`${mapUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Map request failed (${response.status})`);
            }

            const payload = await response.json();
            updateMapFromPayload(mapWrap, payload);
            updateMapSubtitle(root, payload);
        } catch (error) {
            console.error('Failed to refresh municipality map', error);
        } finally {
            root.classList.remove('is-loading');
        }
    };

    const scheduleRefresh = () => {
        window.clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            renderFromFilters();
        }, 250);
    };

    root.querySelectorAll('[data-map-filter]').forEach((input) => {
        input.addEventListener('change', scheduleRefresh);
        input.addEventListener('input', scheduleRefresh);
    });

    applyPoliticalMap(mapWrap, readMapFilters(root).measure);
    scheduleRefresh();
}
