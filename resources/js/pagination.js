export function getVisiblePages(current, last, delta = 2) {
    const pages = new Set([1, last]);

    for (let i = current - delta; i <= current + delta; i++) {
        if (i >= 1 && i <= last) {
            pages.add(i);
        }
    }

    const sorted = [...pages].sort((a, b) => a - b);
    const result = [];

    for (let i = 0; i < sorted.length; i++) {
        if (i > 0 && sorted[i] - sorted[i - 1] > 1) {
            result.push('…');
        }
        result.push(sorted[i]);
    }

    return result;
}

export function renderAjaxPagination(container, { page, lastPage, onGoToPage }) {
    if (!container) {
        return;
    }

    if (!lastPage || lastPage <= 1) {
        container.innerHTML = '';
        return;
    }

    const pages = getVisiblePages(page, lastPage);
    const pageButtons = pages.map((item) => {
        if (item === '…') {
            return '<span class="splis-pagination-ellipsis">…</span>';
        }

        const active = item === page ? ' splis-pagination-page--active' : '';

        return `<button type="button" data-page="${item}" class="splis-pagination-page${active}" ${item === page ? 'aria-current="page"' : ''}>${item}</button>`;
    }).join('');

    container.innerHTML = `
        <div class="splis-pagination">
            <div class="splis-pagination-nav">
                <button type="button" data-page="1" class="splis-btn-secondary splis-pagination-btn" ${page <= 1 ? 'disabled' : ''} title="First page">First</button>
                <button type="button" data-page="${page - 1}" class="splis-btn-secondary splis-pagination-btn" ${page <= 1 ? 'disabled' : ''} title="Previous page">Prev</button>
                <div class="splis-pagination-pages">${pageButtons}</div>
                <button type="button" data-page="${page + 1}" class="splis-btn-secondary splis-pagination-btn" ${page >= lastPage ? 'disabled' : ''} title="Next page">Next</button>
                <button type="button" data-page="${lastPage}" class="splis-btn-secondary splis-pagination-btn" ${page >= lastPage ? 'disabled' : ''} title="Last page">Last</button>
            </div>
            <form class="splis-pagination-goto" data-pagination-goto>
                <label class="sr-only" for="splis-pagination-input">Page number</label>
                <input id="splis-pagination-input" type="number" min="1" max="${lastPage}" value="${page}" class="splis-pagination-input" aria-label="Page number">
                <button type="submit" class="splis-btn-secondary splis-pagination-btn">Go</button>
            </form>
            <p class="splis-pagination-meta">Page ${page} of ${lastPage}</p>
        </div>
    `;

    container.querySelectorAll('[data-page]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = Number(button.dataset.page);
            if (button.disabled || target < 1 || target > lastPage || target === page) {
                return;
            }
            onGoToPage(target);
        });
    });

    const gotoForm = container.querySelector('[data-pagination-goto]');
    const input = container.querySelector('.splis-pagination-input');

    gotoForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        const target = Number(input?.value);
        if (!Number.isFinite(target) || target < 1 || target > lastPage || target === page) {
            return;
        }
        onGoToPage(target);
    });
}
