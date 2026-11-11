import * as bootstrap from 'bootstrap';
import { initSalesLineEditors } from './modules/line-items';

window.bootstrap = bootstrap;

/**
 * Forms marked with data-confirm ask the user in a dialog before submitting.
 * Falls back to the browser's confirm box if the dialog is missing (e.g. on sign-in pages).
 */
const confirmModalElement = document.getElementById('confirmModal');
let confirmedForm = null;

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form === confirmedForm) {
        confirmedForm = null;
        return;
    }

    event.preventDefault();

    if (!confirmModalElement) {
        if (window.confirm(form.dataset.confirm)) {
            confirmedForm = form;
            form.requestSubmit();
        }
        return;
    }

    confirmModalElement.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
    const accept = confirmModalElement.querySelector('[data-confirm-accept]');
    const modal = bootstrap.Modal.getOrCreateInstance(confirmModalElement);

    accept.onclick = () => {
        modal.hide();
        confirmedForm = form;
        form.requestSubmit();
    };

    modal.show();
});

/**
 * Desktop sidebar collapse, remembered between visits.
 */
document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    const root = document.documentElement;
    button.setAttribute('aria-expanded', String(!root.classList.contains('sidebar-collapsed')));

    button.addEventListener('click', () => {
        const collapsed = root.classList.toggle('sidebar-collapsed');
        button.setAttribute('aria-expanded', String(!collapsed));

        try {
            localStorage.setItem('ibms.sidebar', collapsed ? 'collapsed' : 'expanded');
        } catch (error) {
            // Storage may be unavailable (private mode); the toggle still works for this page.
        }
    });
});

/**
 * Keep a colour picker and its text field in step (data-color-sync names the other field's id).
 */
document.querySelectorAll('[data-color-sync]').forEach((input) => {
    const partner = document.getElementById(input.dataset.colorSync);

    input.addEventListener('input', () => {
        if (partner && /^#[0-9a-fA-F]{6}$/.test(input.value)) {
            partner.value = input.value;
        }
    });
});

/**
 * "Select all" checkboxes inside a permission group toggle every permission in that group.
 */
document.querySelectorAll('[data-permission-group]').forEach((group) => {
    const toggle = group.querySelector('[data-select-all]');
    const boxes = [...group.querySelectorAll('input[name="permissions[]"]')];

    if (!toggle || boxes.length === 0) {
        return;
    }

    const sync = () => {
        const checkedCount = boxes.filter((box) => box.checked).length;
        toggle.checked = checkedCount === boxes.length;
        toggle.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
    };

    toggle.addEventListener('change', () => {
        boxes.forEach((box) => {
            box.checked = toggle.checked;
        });
        sync();
    });
    boxes.forEach((box) => box.addEventListener('change', sync));
    sync();
});

/**
 * Line-item editors: [data-line-editor] holds rows ([data-line]) in [data-lines], a <template data-line-template>
 * whose field names contain __INDEX__, an [data-add-line] button and [data-remove-line] buttons.
 * Rows with quantity and cost fields show a line total and a grand total.
 */
document.querySelectorAll('[data-line-editor]').forEach((editor) => {
    const body = editor.querySelector('[data-lines]');
    const template = editor.querySelector('[data-line-template]');
    const grandTotal = editor.querySelector('[data-grand-total]');
    const formatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 });
    let nextIndex = body.querySelectorAll('[data-line]').length;

    const recalculate = () => {
        let total = 0;

        body.querySelectorAll('[data-line]').forEach((row) => {
            const quantity = parseFloat(row.querySelector('[data-line-quantity]')?.value) || 0;
            const cost = parseFloat(row.querySelector('[data-line-cost]')?.value) || 0;
            const lineTotal = row.querySelector('[data-line-total]');

            total += quantity * cost;

            if (lineTotal) {
                lineTotal.textContent = formatter.format(quantity * cost);
            }
        });

        if (grandTotal) {
            grandTotal.textContent = formatter.format(total);
        }
    };

    editor.querySelector('[data-add-line]')?.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        body.insertAdjacentHTML('beforeend', html);
        body.lastElementChild?.querySelector('select, input')?.focus();
    });

    body.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-line]');

        if (!button) {
            return;
        }

        const rows = body.querySelectorAll('[data-line]');
        const row = button.closest('[data-line]');

        if (rows.length > 1) {
            row.remove();
        } else {
            row.querySelectorAll('input').forEach((input) => { input.value = ''; });
            row.querySelectorAll('select').forEach((select) => { select.value = ''; });
        }

        recalculate();
    });

    body.addEventListener('input', recalculate);

    body.addEventListener('change', (event) => {
        const select = event.target.closest('[data-line-item]');

        if (!select) {
            return;
        }

        const cost = select.selectedOptions[0]?.dataset.cost;
        const costInput = select.closest('[data-line]').querySelector('[data-line-cost]');

        if (costInput && cost && !costInput.value) {
            costInput.value = String(parseFloat(cost));
        }

        recalculate();
    });

    recalculate();
});

/**
 * The stock movement "Reverse" dialog: each button carries the form action and a description of the movement.
 */
document.querySelectorAll('[data-reverse-url]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.bsTarget.slice(1));
        modal.querySelector('[data-reverse-form]').action = button.dataset.reverseUrl;
        modal.querySelector('[data-reverse-text]').textContent = `Reverse: ${button.dataset.reverseLabel}.`;
    });
});


initSalesLineEditors();
