import TomSelect from 'tom-select';

/**
 * Sales document line editor (quotations, later invoices).
 *
 * Markup: [data-sales-line-editor] contains [data-lines] rows ([data-line]), a <template data-line-template>
 * whose field names contain __INDEX__, an [data-add-line] button, [data-remove-line] buttons, the overall
 * discount fields and [data-total="subtotal|discount|tax|total"] outputs.
 *
 * The preview follows the server's rules (line discount, pro-rata overall discount, then tax) but the server
 * recalculates everything when the form is saved; these figures are only an estimate.
 */

const cents = (value) => Math.round((Number.isFinite(value) ? value : 0) * 100);
const number = (input) => parseFloat(input?.value ?? '') || 0;

function enhanceItemSelect(select) {
    if (!select || select.tomselect) {
        return;
    }

    new TomSelect(select, {
        allowEmptyOption: true,
        maxOptions: 200,
        placeholder: 'Search items…',
    });
}

function initEditor(editor) {
    const body = editor.querySelector('[data-lines]');
    const template = editor.querySelector('[data-line-template]');
    const decimals = parseInt(editor.dataset.decimals ?? '0', 10);
    const defaultTax = editor.dataset.defaultTax ?? '';
    const formatter = new Intl.NumberFormat(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    const discountType = editor.querySelector('[data-document-discount-type]');
    const discountValue = editor.querySelector('[data-document-discount-value]');
    const show = (key, amountInCents) => {
        const output = editor.querySelector(`[data-total="${key}"]`);

        if (output) {
            output.textContent = formatter.format(amountInCents / 100);
        }
    };
    let nextIndex = body.querySelectorAll('[data-line]').length;

    const recalculate = () => {
        const rows = [...body.querySelectorAll('[data-line]')].map((row) => {
            const gross = cents(number(row.querySelector('[data-line-quantity]')) * number(row.querySelector('[data-line-price]')));
            const discount = Math.min(cents(number(row.querySelector('[data-line-discount]'))), gross);
            const taxOption = row.querySelector('[data-line-tax]')?.selectedOptions[0];

            return { row, gross, net: gross - discount, discount, rate: parseFloat(taxOption?.dataset.rate ?? '0') || 0 };
        });

        const netTotal = rows.reduce((sum, line) => sum + line.net, 0);
        let documentDiscount = 0;

        if (discountType?.value === 'percentage') {
            documentDiscount = Math.round(netTotal * Math.min(number(discountValue), 100) / 100);
        } else if (discountType?.value === 'amount') {
            documentDiscount = Math.min(cents(number(discountValue)), netTotal);
        }

        let allocated = 0;
        let largest = 0;
        rows.forEach((line, index) => {
            line.share = netTotal > 0 ? Math.round(documentDiscount * line.net / netTotal) : 0;
            allocated += line.share;
            if (line.net > rows[largest].net) {
                largest = index;
            }
        });
        if (rows.length > 0) {
            rows[largest].share += documentDiscount - allocated;
        }

        let subtotal = 0;
        let discounts = documentDiscount;
        let tax = 0;

        rows.forEach((line) => {
            const taxable = line.net - line.share;
            const lineTax = Math.round(taxable * line.rate / 100);
            subtotal += line.gross;
            discounts += line.discount;
            tax += lineTax;

            const output = line.row.querySelector('[data-line-total]');
            if (output) {
                output.textContent = formatter.format((taxable + lineTax) / 100);
            }
        });

        show('subtotal', subtotal);
        show('discount', discounts);
        show('tax', tax);
        show('total', subtotal - discounts + tax);
    };

    const applyItem = (select) => {
        const row = select.closest('[data-line]');
        const option = select.querySelector(`option[value="${CSS.escape(select.value)}"]`);

        if (!option || !select.value) {
            return;
        }

        const description = row.querySelector('[data-line-description]');
        const unit = row.querySelector('[data-line-unit]');
        const price = row.querySelector('[data-line-price]');
        const taxSelect = row.querySelector('[data-line-tax]');

        description.value = option.dataset.name ?? description.value;
        unit.value = option.dataset.unit ?? unit.value;
        price.value = option.dataset.price ? String(parseFloat(option.dataset.price)) : price.value;

        if (taxSelect) {
            taxSelect.value = option.dataset.tax || defaultTax;
        }
    };

    body.querySelectorAll('[data-line-item]').forEach(enhanceItemSelect);

    editor.querySelector('[data-add-line]')?.addEventListener('click', () => {
        body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        const row = body.lastElementChild;
        enhanceItemSelect(row.querySelector('[data-line-item]'));
        row.querySelector('[data-line-description]')?.focus();
        recalculate();
    });

    body.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-line]');

        if (!button) {
            return;
        }

        const row = button.closest('[data-line]');

        if (body.querySelectorAll('[data-line]').length > 1) {
            row.querySelector('[data-line-item]')?.tomselect?.destroy();
            row.remove();
        } else {
            row.querySelectorAll('input').forEach((input) => { input.value = ''; });
            row.querySelector('[data-line-item]')?.tomselect?.clear();
        }

        recalculate();
    });

    body.addEventListener('change', (event) => {
        const select = event.target.closest('[data-line-item]');

        if (select) {
            applyItem(select);
        }

        recalculate();
    });

    body.addEventListener('input', recalculate);
    discountType?.addEventListener('change', recalculate);
    discountValue?.addEventListener('input', recalculate);

    recalculate();
}

export function initSalesLineEditors() {
    document.querySelectorAll('[data-sales-line-editor]').forEach(initEditor);

    document.querySelectorAll('select[data-tom-select]').forEach((select) => {
        if (!select.tomselect) {
            new TomSelect(select, { allowEmptyOption: true, maxOptions: 500 });
        }
    });
}
