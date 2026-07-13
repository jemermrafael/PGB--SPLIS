/**
 * Enable click-and-drag horizontal scrolling on overflow containers.
 *
 * @param {ParentNode} [root=document]
 */
export function initDragScroll(root = document) {
    root.querySelectorAll('[data-drag-scroll]').forEach((container) => {
        if (!(container instanceof HTMLElement) || container.dataset.dragScrollBound === '1') {
            return;
        }

        container.dataset.dragScrollBound = '1';
        container.classList.add('splis-drag-scroll');

        let pointerId = null;
        let startX = 0;
        let startScrollLeft = 0;
        let moved = false;

        const endDrag = (event) => {
            if (pointerId === null || event.pointerId !== pointerId) {
                return;
            }

            container.classList.remove('is-dragging');
            try {
                container.releasePointerCapture(pointerId);
            } catch {
                // Pointer may already be released.
            }

            if (moved) {
                container.dataset.dragScrollMoved = '1';
                window.setTimeout(() => {
                    delete container.dataset.dragScrollMoved;
                }, 0);
            }

            pointerId = null;
            moved = false;
        };

        container.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            if (event.target instanceof Element && event.target.closest('a, button, input, select, textarea, label')) {
                return;
            }

            if (container.scrollWidth <= container.clientWidth) {
                return;
            }

            pointerId = event.pointerId;
            startX = event.clientX;
            startScrollLeft = container.scrollLeft;
            moved = false;
            container.classList.add('is-dragging');
            container.setPointerCapture(pointerId);
        });

        container.addEventListener('pointermove', (event) => {
            if (pointerId === null || event.pointerId !== pointerId) {
                return;
            }

            const deltaX = event.clientX - startX;
            if (Math.abs(deltaX) > 4) {
                moved = true;
            }

            container.scrollLeft = startScrollLeft - deltaX;
            if (moved) {
                event.preventDefault();
            }
        });

        container.addEventListener('pointerup', endDrag);
        container.addEventListener('pointercancel', endDrag);
        container.addEventListener('lostpointercapture', endDrag);
    });
}
