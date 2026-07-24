/**
 * Shared list-row meta icons for document tables (author, committee, date, status).
 */

export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

const svgAttrs = 'class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"';

function icon(path) {
    return `<svg ${svgAttrs}><path stroke-linecap="round" stroke-linejoin="round" d="${path}"/></svg>`;
}

export const listIcons = {
    user: icon('M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'),
    scales: icon('M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0012 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 01-2.031.352 5.988 5.988 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0l2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 01-2.031.352 5.989 5.989 0 01-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971z'),
    calendar: icon('M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'),
    check: icon('M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'),
    document: icon('M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'),
    building: icon('M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'),
    home: icon('M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'),
    truck: icon('M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'),
    heart: icon('M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z'),
    book: icon('M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25'),
    leaf: icon('M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152.082A9.01 9.01 0 004.5 10.5c0 5.25 4.25 9.5 9.5 9.5a9.01 9.01 0 003.808-.83.75.75 0 00.082-1.152 7.547 7.547 0 00-1.717-1.705 9.742 9.742 0 006.177-3.539.75.75 0 00-.136-1.071A12.708 12.708 0 0012.963 2.286z'),
    map: icon('M9 6.75V15m6-6v8.25m.503-6.998l4.879-2.196a.75.75 0 01.998.75l-1.5 12a.75.75 0 01-.998.75l-4.879-2.196m-6.002 0l-4.879 2.196a.75.75 0 01-.998-.75l1.5-12a.75.75 0 01.998-.75l4.879 2.196m6.002 0V6.75'),
    banknotes: icon('M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z'),
    shield: icon('M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'),
    wrench: icon('M11.42 15.17l-5.197 5.197a1.125 1.125 0 01-1.59 0l-.791-.791a1.125 1.125 0 010-1.59l5.197-5.197m0 0a3.75 3.75 0 01.562-5.542L12.5 5.25a.75.75 0 01.75-.75h3.75a.75.75 0 01.75.75v3.75a.75.75 0 01-.75.75l-2.835 1.418a3.75 3.75 0 01-5.542.562z'),
    briefcase: icon('M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.162-.733-7.327-2.02-.253-.085-.479-.215-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0'),
    users: icon('M18 18.72a9.09 9.09 0 003.74-.72 4.5 4.5 0 00-7.86-2.72M18 18.72v0a5.25 5.25 0 00-.75-2.72m.75 2.72A11.95 11.95 0 0112 21c-2.17 0-4.21-.58-5.98-1.59M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM4.92 18.72A9 9 0 0112 15.75c.87 0 1.71.12 2.5.35'),
    trophy: icon('M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-4.5A3.75 3.75 0 0012 10.5h0A3.75 3.75 0 007.5 14.25v4.5m9-11.25h.008v.008H16.5V7.5zm-9 0h.008v.008H7.5V7.5zM12 3v4.5'),
    bolt: icon('M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z'),
    sparkles: icon('M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z'),
    megaphone: icon('M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 01-1.44-.23l-3.22-.96A1.5 1.5 0 012.25 12.75v-1.5c0-.662.434-1.24 1.06-1.43l3.22-.96A4.5 4.5 0 017.5 8.25h.75c.704 0 1.402-.03 2.09-.09m0 7.68v-7.68m0 7.68a48.667 48.667 0 008.41-1.17c.94-.25 1.75-.99 1.75-1.98V11.4c0-.99-.81-1.73-1.75-1.98A48.667 48.667 0 0010.34 8.25m0 7.68v-7.68'),
    globe: icon('M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418'),
    link: icon('M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244'),
};

// Keep alias used elsewhere.
listIcons.committee = listIcons.scales;

/**
 * Pick a contextual icon from committee name keywords.
 */
export function committeeIconFor(name) {
    const n = String(name ?? '').toLowerCase();

    if (/justice|legal|human rights|ethics|rules|privileges/.test(n)) {
        return listIcons.scales;
    }
    if (/transport|communication|traffic/.test(n)) {
        return listIcons.truck;
    }
    if (/housing|land use|land utilization|barangay|community/.test(n)) {
        return listIcons.home;
    }
    if (/health|sanitation|pwd|disability|senior/.test(n)) {
        return listIcons.heart;
    }
    if (/education|culture|family/.test(n)) {
        return listIcons.book;
    }
    if (/agriculture|food|fisher|environment|natural resource/.test(n)) {
        return listIcons.leaf;
    }
    if (/tourism/.test(n)) {
        return listIcons.map;
    }
    if (/finance|budget|appropriat|ways|means|trade|commerce|industry|cooperative/.test(n)) {
        return listIcons.banknotes;
    }
    if (/peace|order|public safety|shield/.test(n)) {
        return listIcons.shield;
    }
    if (/infrastructure|public works/.test(n)) {
        return listIcons.wrench;
    }
    if (/labor|employment|manpower|government services/.test(n)) {
        return listIcons.briefcase;
    }
    if (/women|children|gender|social welfare|indigenous|whole/.test(n)) {
        return listIcons.users;
    }
    if (/youth|sports/.test(n)) {
        return listIcons.trophy;
    }
    if (/games|amusement/.test(n)) {
        return listIcons.sparkles;
    }
    if (/energy|water|utilit|power/.test(n)) {
        return listIcons.bolt;
    }
    if (/public information|people.?s? power|participation/.test(n)) {
        return listIcons.megaphone;
    }

    return listIcons.building;
}

export function renderAuthorMeta(author) {
    const label = String(author ?? '').trim();
    if (label === '') {
        return '<span class="text-slate-400">—</span>';
    }

    return `<span class="splis-list-meta">
        <span class="splis-list-meta-avatar" aria-hidden="true">${listIcons.user}</span>
        <span class="splis-list-meta-text">${escapeHtml(label)}</span>
    </span>`;
}

export function renderCommitteeMeta(committee, icon = null) {
    const label = typeof committee === 'object' && committee !== null
        ? String(committee.name ?? committee.label ?? '').trim()
        : String(committee ?? '').trim();

    if (label === '') {
        return '<span class="text-slate-400">—</span>';
    }

    const iconKey = icon?.key
        ?? (typeof committee === 'object' && committee !== null ? committee.icon_key ?? committee.committee_icon_key : null)
        ?? null;
    const iconUrl = icon?.url
        ?? (typeof committee === 'object' && committee !== null ? committee.icon_url ?? committee.committee_icon_url : null)
        ?? null;

    let iconHtml;
    if (iconUrl) {
        iconHtml = `<span class="splis-list-committee-icon-glyph" style="--committee-icon:url('${escapeHtml(iconUrl)}')"></span>`;
    } else if (iconKey && listIcons[iconKey]) {
        iconHtml = listIcons[iconKey];
    } else {
        iconHtml = committeeIconFor(label);
    }

    return `<span class="splis-list-committee" title="${escapeHtml(label)}">
        <span class="splis-list-committee-icon" aria-hidden="true">${iconHtml}</span>
        <span class="splis-list-committee-text">${escapeHtml(label)}</span>
    </span>`;
}

export function renderDateMeta(label) {
    const text = String(label ?? '').trim();
    if (text === '' || text === '—') {
        return '<span class="text-slate-400">—</span>';
    }

    return `<span class="splis-list-meta">
        <span class="splis-list-meta-icon" aria-hidden="true">${listIcons.calendar}</span>
        <span class="splis-list-meta-text">${escapeHtml(text)}</span>
    </span>`;
}

export function renderStatusBadge(status) {
    const label = String(status ?? '').trim();
    if (label === '') {
        return '<span class="text-slate-400">—</span>';
    }

    const normalized = label.toLowerCase();
    let badgeClass = 'splis-badge-approved';
    let iconSvg = listIcons.check;

    if (normalized.includes('draft')) {
        badgeClass = 'splis-badge-legacy';
        iconSvg = listIcons.document;
    } else if (normalized.includes('archiv')) {
        badgeClass = 'splis-badge-legacy';
        iconSvg = listIcons.document;
    } else if (normalized.includes('link') && !normalized.includes('unlink')) {
        badgeClass = 'splis-badge-linked';
        iconSvg = listIcons.link;
    } else if (normalized.includes('unlink')) {
        badgeClass = 'splis-badge-unlinked';
        iconSvg = listIcons.link;
    }

    return `<span class="${badgeClass} splis-badge-with-icon capitalize">
        <span aria-hidden="true">${iconSvg}</span>
        <span>${escapeHtml(label)}</span>
    </span>`;
}

export function renderMunicipalityMeta(municipality) {
    const label = String(municipality ?? '').trim();
    if (label === '') {
        return '<span class="text-slate-400">—</span>';
    }

    return `<span class="splis-list-meta">
        <span class="splis-list-meta-avatar splis-list-meta-avatar--muted" aria-hidden="true">${listIcons.building}</span>
        <span class="splis-list-meta-text">${escapeHtml(label)}</span>
    </span>`;
}
