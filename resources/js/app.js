import './bootstrap';

const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
};

ready(() => {
    // Mobile sidebar
    const sidebar = document.getElementById('app-sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            sidebar?.classList.toggle('-translate-x-full');
            sidebarOverlay?.classList.toggle('hidden');
        });
    });

    sidebarOverlay?.addEventListener('click', () => {
        sidebar?.classList.add('-translate-x-full');
        sidebarOverlay.classList.add('hidden');
    });

    // Auto-submit filters
    document.querySelectorAll('[data-auto-submit]').forEach((field) => {
        field.addEventListener('change', () => field.form?.requestSubmit());
    });

    // Confirm destructive actions
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    // Dismissible flash messages
    document.querySelectorAll('[data-flash-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('[data-flash]')?.remove());
    });

    // Export dialogs
    document.querySelectorAll('[data-export-dialog]').forEach((dialog) => {
        const form = dialog.querySelector('[data-export-form]');
        const errorBox = dialog.querySelector('[data-export-error]');
        const successBox = dialog.querySelector('[data-export-success]');
        const submitButton = dialog.querySelector('[data-export-submit]');
        const formatSelect = dialog.querySelector('[data-export-format]');
        const csvNote = dialog.querySelector('[data-csv-note]');

        const showError = (message) => {
            if (!errorBox) return;
            errorBox.textContent = message;
            errorBox.classList.remove('hidden');
            successBox?.classList.add('hidden');
        };

        const clearMessages = () => {
            errorBox?.classList.add('hidden');
            successBox?.classList.add('hidden');
        };

        document.querySelectorAll(`[data-export-open="${dialog.id}"]`).forEach((button) => {
            button.addEventListener('click', () => {
                clearMessages();
                dialog.showModal();
            });
        });

        dialog.querySelectorAll('[data-export-close]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        // Reporting period fields
        const modeSelect = dialog.querySelector('[data-export-mode]');

        const syncPeriodFields = () => {
            if (!modeSelect) return;

            dialog.querySelectorAll('[data-period-field]').forEach((field) => {
                field.classList.toggle('hidden', field.dataset.periodField !== modeSelect.value);
            });
        };

        modeSelect?.addEventListener('change', syncPeriodFields);
        syncPeriodFields();

        formatSelect?.addEventListener('change', () => {
            csvNote?.classList.toggle('hidden', formatSelect.value !== 'csv');
        });
        csvNote?.classList.toggle('hidden', formatSelect?.value !== 'csv');

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearMessages();

            if (!form.reportValidity()) {
                return;
            }

            const url = new URL(form.dataset.exportUrl, window.location.origin);
            const formData = new FormData(form);

            for (const [key, value] of formData.entries()) {
                if (value !== '' && value !== null) {
                    url.searchParams.append(key, value);
                }
            }

            // Append unchecked worksheet selection if the group exists.
            const worksheetInputs = form.querySelectorAll('input[name="worksheets[]"]');
            if (worksheetInputs.length > 0) {
                const checked = [...worksheetInputs].filter((input) => input.checked);
                if (checked.length === 0) {
                    showError('Select at least one worksheet to include in the workbook.');
                    return;
                }
            }

            submitButton.disabled = true;
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span> Generating…';

            try {
                const response = await fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    let message = 'The export could not be generated. Please try again.';
                    try {
                        const payload = await response.json();
                        if (payload.message) message = payload.message;
                        if (payload.errors) {
                            const first = Object.values(payload.errors).flat()[0];
                            if (first) message = first;
                        }
                    } catch (error) {
                        // Keep the default message.
                    }
                    throw new Error(message);
                }

                const blob = await response.blob();
                const disposition = response.headers.get('Content-Disposition') || '';
                const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
                const asciiMatch = disposition.match(/filename="?([^";]+)"?/i);
                const filename = utf8Match ? decodeURIComponent(utf8Match[1]) : (asciiMatch ? asciiMatch[1] : 'Axcess-export.xlsx');

                const objectUrl = URL.createObjectURL(blob);
                const anchor = document.createElement('a');
                anchor.href = objectUrl;
                anchor.download = filename;
                document.body.appendChild(anchor);
                anchor.click();
                anchor.remove();
                URL.revokeObjectURL(objectUrl);

                if (successBox) {
                    successBox.textContent = `Your export is ready: ${filename}`;
                    successBox.classList.remove('hidden');
                }
            } catch (error) {
                showError(error.message || 'The export could not be generated. Please try again.');
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        });
    });

    // Shift form: apply default shift times
    document.querySelectorAll('[data-shift-form]').forEach((form) => {
        const shiftSelect = form.querySelector('[data-shift-type]');
        const dateInput = form.querySelector('[data-schedule-date]');
        const startInput = form.querySelector('[data-start-at]');
        const endInput = form.querySelector('[data-end-at]');
        const applyButton = form.querySelector('[data-apply-shift-times]');
        const isEdit = form.dataset.isEdit === 'true';

        const applyTimes = () => {
            const option = shiftSelect?.selectedOptions?.[0];
            if (!option || !dateInput?.value || !startInput || !endInput) return;

            const startTime = option.dataset.start;
            const endTime = option.dataset.end;
            if (!startTime || !endTime) return;

            startInput.value = `${dateInput.value}T${startTime}`;
            const crossesMidnight = endTime <= startTime;
            const endDate = new Date(`${dateInput.value}T00:00:00`);
            if (crossesMidnight) {
                endDate.setDate(endDate.getDate() + 1);
            }
            const yyyy = endDate.getFullYear();
            const mm = String(endDate.getMonth() + 1).padStart(2, '0');
            const dd = String(endDate.getDate()).padStart(2, '0');
            endInput.value = `${yyyy}-${mm}-${dd}T${endTime}`;
        };

        shiftSelect?.addEventListener('change', () => {
            if (!isEdit) applyTimes();
        });
        dateInput?.addEventListener('change', () => {
            if (!isEdit) applyTimes();
        });
        applyButton?.addEventListener('click', applyTimes);
    });

    // Generic reporting period controls outside export dialogs.
    document.querySelectorAll('[data-period-scope]').forEach((scope) => {
        const select = scope.querySelector('[data-period-select]');
        if (!select) return;

        const sync = () => {
            scope.querySelectorAll('[data-period-option]').forEach((field) => {
                field.classList.toggle('hidden', field.dataset.periodOption !== select.value);
            });
        };

        select.addEventListener('change', sync);
        sync();
    });

    // Break repeater
    document.querySelectorAll('[data-breaks]').forEach((container) => {
        const form = container.closest('form');
        const template = form?.querySelector('#break-template');
        const addButton = form?.querySelector('[data-add-break]');

        addButton?.addEventListener('click', () => {
            if (!template) return;
            const index = Date.now();
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name]').forEach((input) => {
                input.name = input.name.replace('__INDEX__', index);
            });
            container.appendChild(fragment);
        });

        container.addEventListener('click', (event) => {
            if (event.target.closest('[data-remove-break]')) {
                event.target.closest('[data-break-row]')?.remove();
            }
        });
    });
});
