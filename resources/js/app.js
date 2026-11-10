import * as bootstrap from 'bootstrap';

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
