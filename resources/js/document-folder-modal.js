let bound = false;

function closeFolderModal(modal) {
    if (! modal) {
        return;
    }

    modal.hidden = true;
    document.body.classList.remove('splis-modal-open');
}

export function initDocumentFolderModal() {
    if (bound) {
        return;
    }

    bound = true;

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-folder-modal-open]');
        if (trigger) {
            event.preventDefault();

            const targetSelector = trigger.dataset.folderModalTarget || '#splis-document-folder-modal';
            const modal = document.querySelector(targetSelector);

            if (! modal) {
                return;
            }

            modal.hidden = false;
            document.body.classList.add('splis-modal-open');
            return;
        }

        const closeTrigger = event.target.closest('[data-folder-modal-close]');
        if (closeTrigger) {
            const modal = closeTrigger.closest('.splis-modal');
            closeFolderModal(modal);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        document.querySelectorAll('.splis-modal:not([hidden]) [data-folder-modal-close]').forEach((closeTrigger) => {
            const modal = closeTrigger.closest('.splis-modal');

            if (modal && modal.querySelector('[data-folder-modal-close]')) {
                closeFolderModal(modal);
            }
        });
    });
}
