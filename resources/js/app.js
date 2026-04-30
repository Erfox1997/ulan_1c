import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/** Остаток из API (строка с запятой/пробелами). Не число → null. */
function parseGoodsStockQuantityRaw(v) {
    if (v == null || v === '') return null;
    const n = parseFloat(String(v).replace(/\s/g, '').replace(',', '.'));
    return Number.isFinite(n) ? n : null;
}

/** Нулевой или отрицательный остаток — визуально «нет в наличии». */
function goodsStockQtyIsSoldOut(raw) {
    const n = parseGoodsStockQuantityRaw(raw);
    return n !== null && n <= 0;
}

document.addEventListener('alpine:init', () => {
    /** Поле категории в модалке «Новый товар»: select + новая категория (x-modelable → newGoodForm.category). */
    Alpine.data('quickGoodCategoryPicker', (categoriesUrl) => ({
        categoriesUrl: typeof categoriesUrl === 'string' ? categoriesUrl : '',
        items: [],
        loading: false,
        category: '',
        pickValue: '',
        newName: '',
        async loadItems() {
            if (!this.categoriesUrl) {
                this.items = [];
                return;
            }
            this.loading = true;
            try {
                const url = new URL(this.categoriesUrl, window.location.origin);
                const res = await fetch(url.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.items = Array.isArray(data) ? data.map((x) => String(x)) : [];
                this.syncPickFromCategory();
            } catch {
                this.items = [];
            } finally {
                this.loading = false;
            }
        },
        syncPickFromCategory() {
            const c = (this.category || '').trim();
            if (c === '') {
                this.pickValue = '';
                this.newName = '';
                return;
            }
            if (this.items.some((x) => x === c)) {
                this.pickValue = c;
                this.newName = '';
            } else {
                this.pickValue = '__new__';
                this.newName = c;
            }
        },
        onPickChange() {
            if (this.pickValue === '' || this.pickValue == null) {
                this.category = '';
                this.newName = '';
                return;
            }
            if (this.pickValue === '__new__') {
                this.category = (this.newName || '').trim();
            } else {
                this.category = this.pickValue;
                this.newName = '';
            }
        },
        onNewNameInput() {
            if (this.pickValue === '__new__') {
                this.category = (this.newName || '').trim();
            }
        },
        init() {
            this.loadItems();
            this.$watch('category', () => {
                this.syncPickFromCategory();
            });
        },
    }));

    Alpine.data('organizationBankRows', (initialAccounts, defaultIndex) => ({
        accounts:
            Array.isArray(initialAccounts) && initialAccounts.length > 0
                ? initialAccounts.map((a) => ({
                      id: a.id ?? null,
                      account_type: a.account_type === 'cash' ? 'cash' : 'bank',
                      account_number: a.account_number ?? '',
                      bank_name: a.bank_name ?? '',
                      bik: a.bik ?? '',
                      currency: (a.currency ?? 'KGS').toString().toUpperCase().slice(0, 3) || 'KGS',
                      opening_balance: a.opening_balance != null && String(a.opening_balance) !== '' ? String(a.opening_balance) : '',
                  }))
                : [
                      {
                          id: null,
                          account_type: 'bank',
                          account_number: '',
                          bank_name: '',
                          bik: '',
                          currency: 'KGS',
                          opening_balance: '',
                      },
                  ],
        defaultIdx: typeof defaultIndex === 'number' ? defaultIndex : 0,
        addBankRow() {
            this.accounts.push({
                id: null,
                account_type: 'bank',
                account_number: '',
                bank_name: '',
                bik: '',
                currency: 'KGS',
                opening_balance: '',
            });
        },
        addCashRow() {
            this.accounts.push({
                id: null,
                account_type: 'cash',
                account_number: '',
                bank_name: '',
                bik: '',
                currency: 'KGS',
                opening_balance: '',
            });
        },
        removeRow(i) {
            if (this.accounts.length <= 1) {
                return;
            }
            this.accounts.splice(i, 1);
            if (this.defaultIdx >= this.accounts.length) {
                this.defaultIdx = this.accounts.length - 1;
            }
        },
    }));

    Alpine.data('purchaseReceiptForm', () => {
        const emptyLine = () => ({
            article_code: '',
            name: '',
            barcode: '',
            good_id: '',
            markup_percent: '',
            unit: 'шт.',
            quantity: '',
            unit_price: '',
            sale_price: '',
        });

        const init =
            typeof window !== 'undefined' &&
            window.__purchaseReceiptInit &&
            typeof window.__purchaseReceiptInit === 'object'
                ? window.__purchaseReceiptInit
                : {};
        const initialLines = init.lines;
        const urls = init.urls;
        const initialSupplierName = init.supplierName;
        const warehouseId =
            typeof init.warehouseId === 'number' && !Number.isNaN(init.warehouseId)
                ? init.warehouseId
                : parseInt(String(init.warehouseId ?? '0'), 10) || 0;

        let lines = Array.isArray(initialLines) ? initialLines.map((r) => ({ ...r })) : [];
        if (lines.length === 0) {
            lines = [emptyLine()];
        }
        lines = lines.map((r) => ({
            article_code: r.article_code ?? '',
            name: r.name ?? '',
            barcode: r.barcode ?? '',
            good_id: r.good_id ?? '',
            markup_percent: r.markup_percent ?? '',
            unit: r.unit ?? 'шт.',
            quantity: r.quantity ?? '',
            unit_price: r.unit_price ?? '',
            sale_price: r.sale_price ?? '',
        }));

        const u = urls && typeof urls === 'object' ? urls : {};
        const goodsSearchUrl = typeof u.goodsSearch === 'string' ? u.goodsSearch : '';
        const goodsQuickStoreUrl = typeof u.goodsQuickStore === 'string' ? u.goodsQuickStore : '';
        const counterpartySearchUrl = typeof u.counterpartySearch === 'string' ? u.counterpartySearch : '';
        const counterpartyQuickUrl = typeof u.counterpartyQuick === 'string' ? u.counterpartyQuick : '';
        const branchName = typeof init.branchName === 'string' ? init.branchName : '';
        const warehouseName = typeof init.warehouseName === 'string' ? init.warehouseName : '';
        const openFinalizeOnLoad = init.openFinalizeOnLoad === true;
        const purchaseReceiptSwitchWarehouseUrl =
            typeof init.purchaseReceiptSwitchWarehouseUrl === 'string'
                ? init.purchaseReceiptSwitchWarehouseUrl
                : '';

        return {
        lines,
        selectedRow: 0,
        warehouseId,
        openFinalizeOnLoad,
        supplierName: typeof initialSupplierName === 'string' ? initialSupplierName : '',
        goodsSearchUrl,
        goodsQuickStoreUrl,
        counterpartySearchUrl,
        counterpartyQuickUrl,
        branchName,
        warehouseName,
        newGoodModalOpen: false,
        newGoodSaving: false,
        newGoodError: '',
        newGoodForm: {
            name: '',
            barcode: '',
            category: '',
            unit: 'шт.',
            quantity: '1',
            unit_price: '',
            wholesale_price: '',
            sale_price: '',
            oem: '',
            factory_number: '',
            min_stock: '',
        },
        copyFeedbackGoodId: null,
        copyFeedbackTimer: null,
        nameSuggestRow: null,
        nameSuggestItems: [],
        nameSuggestLoading: false,
        nameSuggestNoHits: false,
        nameSuggestTimer: null,
        nameSuggestBlurTimer: null,
        suggestPos: { top: 0, left: 0, width: 280 },
        cpSuggestItems: [],
        cpSuggestLoading: false,
        cpSuggestNoHits: false,
        cpSuggestTimer: null,
        cpSuggestBlurTimer: null,
        cpSuggestPos: { top: 0, left: 0, width: 320 },
        cpQuickOpen: false,
        cpQuickLegalForm: 'osoo',
        cpQuickSaving: false,
        cpQuickError: '',
        finalizeModalOpen: false,
        resetNewGoodForm() {
            this.newGoodForm = {
                name: '',
                barcode: '',
                category: '',
                unit: 'шт.',
                quantity: '1',
                unit_price: '',
                wholesale_price: '',
                sale_price: '',
                oem: '',
                factory_number: '',
                min_stock: '',
            };
        },
        openNewGoodModal(presetName) {
            this.closeAllSuggests();
            this.resetNewGoodForm();
            const n = presetName != null ? String(presetName).trim() : '';
            if (n !== '') {
                this.newGoodForm.name = n;
            }
            this.newGoodError = '';
            this.newGoodModalOpen = true;
            this.$nextTick(() => {
                document.getElementById('pr_new_good_name')?.focus();
            });
        },
        closeNewGoodModal() {
            this.newGoodModalOpen = false;
            this.newGoodError = '';
            this.newGoodSaving = false;
        },
        async submitNewGoodQuickStore() {
            const name = String(this.newGoodForm.name ?? '').trim();
            if (name === '') {
                this.newGoodError = 'Укажите наименование.';
                return;
            }
            const qtyRaw = String(this.newGoodForm.quantity ?? '').trim();
            if (qtyRaw === '') {
                this.newGoodError = 'Укажите количество.';
                return;
            }
            const qNum = this.parsePurchaseNum(qtyRaw);
            if (!Number.isFinite(qNum) || qNum <= 0) {
                this.newGoodError = 'Количество должно быть числом больше нуля.';
                return;
            }
            if (!this.goodsQuickStoreUrl) {
                this.newGoodError = 'Создание товара недоступно.';
                return;
            }
            const token =
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            this.newGoodSaving = true;
            this.newGoodError = '';
            /** @type {Record<string, string>} */
            const body = { name };
            const addStr = (k, v) => {
                const t = String(v ?? '').trim();
                if (t !== '') {
                    body[k] = t;
                }
            };
            addStr('barcode', this.newGoodForm.barcode);
            addStr('category', this.newGoodForm.category);
            addStr('unit', this.newGoodForm.unit);
            addStr('sale_price', this.newGoodForm.sale_price);
            addStr('wholesale_price', this.newGoodForm.wholesale_price);
            addStr('oem', this.newGoodForm.oem);
            addStr('factory_number', this.newGoodForm.factory_number);
            addStr('min_stock', this.newGoodForm.min_stock);
            if (!body.unit) {
                body.unit = 'шт.';
            }
            let url = this.goodsQuickStoreUrl;
            if (this.warehouseId > 0) {
                url += (url.includes('?') ? '&' : '?') + 'warehouse_id=' + encodeURIComponent(String(this.warehouseId));
            }
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(body),
                });
                /** @type {Record<string, unknown>} */
                let data = {};
                try {
                    data = await res.json();
                } catch (_) {
                    data = {};
                }
                if (!res.ok) {
                    let msg =
                        typeof data.message === 'string'
                            ? data.message
                            : 'Не удалось сохранить товар.';
                    if (data.errors && typeof data.errors === 'object') {
                        const first = Object.values(data.errors).flat()[0];
                        if (typeof first === 'string' && first.trim() !== '') {
                            msg = first;
                        }
                    }
                    this.newGoodError = msg;
                    return;
                }
                const item = data;
                const row = emptyLine();
                this.fillLineFromPurchaseGood(row, item);
                row.quantity = qtyRaw.replace(/\s/g, '').replace(',', '.');
                const up = String(this.newGoodForm.unit_price ?? '').trim();
                if (up !== '') {
                    row.unit_price = up.replace(/\s/g, '').replace(',', '.');
                }
                const sp = String(this.newGoodForm.sale_price ?? '').trim();
                if (sp !== '') {
                    row.sale_price = sp.replace(/\s/g, '').replace(',', '.');
                }
                const cost = this.parsePurchaseNum(row.unit_price);
                const sale = this.parsePurchaseNum(row.sale_price);
                if (Number.isFinite(cost) && cost > 0 && Number.isFinite(sale)) {
                    const m = (sale / cost - 1) * 100;
                    if (Number.isFinite(m)) {
                        row.markup_percent = (Math.round(m * 100) / 100).toString();
                    }
                }
                this.applyNewPurchaseLineOrPush(row);
                this.closeNewGoodModal();
                this.headerGoodClose();
                this.$nextTick(() => {
                    document.querySelector('[data-pr-header-good-input]')?.focus?.();
                });
            } catch (_) {
                this.newGoodError = 'Ошибка сети. Повторите попытку.';
            } finally {
                this.newGoodSaving = false;
            }
        },
        purchaseReceiptEscape() {
            if (this.newGoodModalOpen) {
                this.closeNewGoodModal();
                return;
            }
            if (this.finalizeModalOpen) {
                this.closeFinalizeModal();
                return;
            }
            this.closeAllSuggests();
        },
        openFinalizeModal() {
            this.closeAllSuggests();
            this.finalizeModalOpen = true;
            this.$nextTick(() => {
                document.getElementById('pr_finalize_supplier')?.focus();
            });
        },
        openFinalizeIfNeeded() {
            if (!this.openFinalizeOnLoad) {
                return;
            }
            this.$nextTick(() => {
                this.finalizeModalOpen = true;
                this.$nextTick(() => {
                    document.getElementById('pr_finalize_supplier')?.focus();
                });
            });
        },
        onFinalizeWarehouseChange(event) {
            const v = parseInt(String(event?.target?.value ?? ''), 10);
            if (!Number.isFinite(v) || v <= 0 || v === this.warehouseId) {
                return;
            }
            if (!purchaseReceiptSwitchWarehouseUrl) {
                return;
            }
            const u = new URL(purchaseReceiptSwitchWarehouseUrl, window.location.href);
            u.searchParams.set('warehouse_id', String(v));
            window.location.assign(u.href);
        },
        closeFinalizeModal() {
            this.finalizeModalOpen = false;
            this.counterpartySuggestClose();
        },
        submitPurchaseFromModal() {
            const form = document.getElementById('pr-purchase-receipt-form');
            if (!form || !form.reportValidity()) return;
            form.requestSubmit();
        },
        parsePurchaseNum(v) {
            const n = parseFloat(String(v ?? '').replace(/\s/g, '').replace(',', '.'));
            return Number.isFinite(n) ? n : NaN;
        },
        applySaleFromMarkup(index) {
            const row = this.lines[index];
            if (!row) return;
            const purchase = this.parsePurchaseNum(row.unit_price);
            const markup = this.parsePurchaseNum(row.markup_percent);
            if (!Number.isFinite(purchase) || purchase < 0) return;
            if (!Number.isFinite(markup)) return;
            row.sale_price = (purchase * (1 + markup / 100)).toFixed(2);
        },
        applyMarkupFromSale(index) {
            const row = this.lines[index];
            if (!row) return;
            const saleRaw = String(row.sale_price ?? '').trim();
            if (saleRaw === '') {
                row.markup_percent = '';
                return;
            }
            const purchase = this.parsePurchaseNum(row.unit_price);
            const sale = this.parsePurchaseNum(row.sale_price);
            if (!Number.isFinite(purchase) || purchase <= 0) return;
            if (!Number.isFinite(sale)) return;
            const m = (sale / purchase - 1) * 100;
            row.markup_percent = (Math.round(m * 100) / 100).toString();
        },
        bulkMarkupPercent: '',
        applyBulkMarkupToAllLines() {
            const raw = String(this.bulkMarkupPercent ?? '').trim();
            if (raw === '') {
                window.alert('Введите процент наценки (например 30).');
                return;
            }
            const m = this.parsePurchaseNum(raw);
            if (!Number.isFinite(m)) {
                window.alert('Укажите число, например 30 или 12,5.');
                return;
            }
            const normalized = raw.replace(/\s/g, '');
            this.lines.forEach((row, index) => {
                row.markup_percent = normalized;
                this.applySaleFromMarkup(index);
            });
        },
        lineSum(row) {
            const q = parseFloat(String(row.quantity ?? '').replace(/\s/g, '').replace(',', '.'));
            const p = parseFloat(String(row.unit_price ?? '').replace(/\s/g, '').replace(',', '.'));
            if (!Number.isFinite(q) || !Number.isFinite(p)) return '';
            return (q * p).toFixed(2);
        },
        formatGoodsStockQty(v) {
            if (v == null || v === '') return '';
            const n = Number(v);
            if (Number.isNaN(n)) return String(v);
            return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 4 }).format(n);
        },
        formatGoodsUnitCost(v) {
            if (v == null || v === '') return '';
            const n = Number(v);
            if (Number.isNaN(n)) return String(v);
            return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
        },
        goodsSuggestHasWarehouseHint(item) {
            if (!item || typeof item !== 'object') return false;
            const q = item.stock_quantity;
            const c = item.opening_unit_cost;
            return (q != null && q !== '') || (c != null && c !== '');
        },
        goodsStockQtySoldOut(v) {
            return goodsStockQtyIsSoldOut(v);
        },
        refreshSuggestPosition(el) {
            if (!el || !el.getBoundingClientRect) return;
            const r = el.getBoundingClientRect();
            const w = Math.max(r.width, 300);
            let left = r.left;
            if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8);
            this.suggestPos = { top: r.bottom + 4, left, width: w };
        },
        refreshCpSuggestPosition(el) {
            if (!el || !el.getBoundingClientRect) return;
            const r = el.getBoundingClientRect();
            const w = Math.max(r.width, 280);
            let left = r.left;
            if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8);
            this.cpSuggestPos = { top: r.bottom + 4, left, width: w };
        },
        nameSuggestClose() {
            clearTimeout(this.nameSuggestTimer);
            this.clearCopyFeedback();
            this.nameSuggestItems = [];
            this.nameSuggestRow = null;
            this.nameSuggestLoading = false;
            this.nameSuggestNoHits = false;
        },
        counterpartySuggestClose() {
            clearTimeout(this.cpSuggestTimer);
            this.cpSuggestItems = [];
            this.cpSuggestLoading = false;
            this.cpSuggestNoHits = false;
            this.cpQuickOpen = false;
            this.cpQuickError = '';
        },
        closeAllSuggests() {
            if (this.newGoodModalOpen) {
                this.closeNewGoodModal();
                return;
            }
            this.nameSuggestClose();
            this.counterpartySuggestClose();
            this.headerGoodClose();
        },
        showCpDropdown() {
            if (this.cpQuickOpen) {
                return true;
            }
            const q = (this.supplierName || '').trim();
            if (q.length < 2) {
                return false;
            }
            return this.cpSuggestLoading || this.cpSuggestItems.length > 0 || this.cpSuggestNoHits;
        },
        onNameFocus(index, event) {
            clearTimeout(this.nameSuggestBlurTimer);
            this.headerGoodClose();
            this.counterpartySuggestClose();
            this.selectedRow = index;
            this.refreshSuggestPosition(event.target);
        },
        onNameBlur() {
            this.nameSuggestBlurTimer = setTimeout(() => this.nameSuggestClose(), 180);
        },
        onNameInput(index, event) {
            const el = event.target;
            this.headerGoodClose();
            this.refreshSuggestPosition(el);
            const q = (el.value || '').trim();
            clearTimeout(this.nameSuggestTimer);
            if (q.length < 2) {
                this.nameSuggestClose();
                return;
            }
            this.counterpartySuggestClose();
            this.nameSuggestRow = index;
            this.nameSuggestLoading = true;
            this.nameSuggestNoHits = false;
            this.nameSuggestItems = [];
            this.nameSuggestTimer = setTimeout(async () => {
                try {
                    let url = this.goodsSearchUrl + '?q=' + encodeURIComponent(q);
                    if (this.warehouseId > 0) {
                        url += '&warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                    }
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('search');
                    const data = await res.json();
                    if (this.nameSuggestRow !== index) return;
                    this.nameSuggestItems = Array.isArray(data) ? data : [];
                    this.nameSuggestNoHits = this.nameSuggestItems.length === 0;
                } catch (e) {
                    this.nameSuggestItems = [];
                    this.nameSuggestNoHits = false;
                } finally {
                    this.nameSuggestLoading = false;
                    this.$nextTick(() => this.refreshSuggestPosition(el));
                }
            }, 280);
        },
        fillLineFromPurchaseGood(row, item) {
            if (!row || !item) return;
            row.good_id = item.id != null && item.id !== '' ? String(item.id) : '';
            row.name = item.name || '';
            row.article_code = item.article_code || '';
            row.barcode = item.barcode != null && item.barcode !== '' ? String(item.barcode) : '';
            row.markup_percent = '';
            row.unit = item.unit && String(item.unit).trim() ? String(item.unit).trim() : 'шт.';
            if (item.opening_unit_cost != null && item.opening_unit_cost !== '') {
                row.unit_price = String(item.opening_unit_cost);
            }
            if (item.sale_price != null && item.sale_price !== '') {
                row.sale_price = String(item.sale_price);
            }
            const cost = this.parsePurchaseNum(row.unit_price);
            const sale = this.parsePurchaseNum(row.sale_price);
            if (Number.isFinite(cost) && cost > 0 && Number.isFinite(sale)) {
                const m = (sale / cost - 1) * 100;
                if (Number.isFinite(m)) {
                    row.markup_percent = (Math.round(m * 100) / 100).toString();
                }
            }
        },
        pickGoodFromSuggest(item) {
            clearTimeout(this.nameSuggestBlurTimer);
            const index = this.nameSuggestRow;
            if (index === null || !item) return;
            const row = this.lines[index];
            if (!row) return;
            this.fillLineFromPurchaseGood(row, item);
            this.nameSuggestClose();
        },
        headerGoodQuery: '',
        headerGoodOpen: false,
        headerGoodLoading: false,
        headerGoodItems: [],
        headerGoodNoHits: false,
        headerGoodTimer: null,
        headerGoodBlurTimer: null,
        repositionOpenSuggests() {
            const ni = this.nameSuggestRow;
            if (
                ni !== null &&
                (this.nameSuggestLoading || this.nameSuggestItems.length > 0 || this.nameSuggestNoHits)
            ) {
                const inp = document.querySelector(`input[name="lines[${ni}][name]"]`);
                if (inp) this.refreshSuggestPosition(inp);
            }
            const supEl = document.getElementById('pr_finalize_supplier');
            if (
                supEl &&
                (this.cpQuickOpen ||
                    ((this.supplierName || '').trim().length >= 2 &&
                        (this.cpSuggestLoading || this.cpSuggestItems.length > 0 || this.cpSuggestNoHits)))
            ) {
                this.refreshCpSuggestPosition(supEl);
            }
        },
        clearCopyFeedback() {
            clearTimeout(this.copyFeedbackTimer);
            this.copyFeedbackTimer = null;
            this.copyFeedbackGoodId = null;
        },
        headerGoodClose(clearQuery = true) {
            clearTimeout(this.headerGoodTimer);
            clearTimeout(this.headerGoodBlurTimer);
            this.clearCopyFeedback();
            if (clearQuery) {
                this.headerGoodQuery = '';
            }
            this.headerGoodItems = [];
            this.headerGoodLoading = false;
            this.headerGoodNoHits = false;
            this.headerGoodOpen = false;
        },
        onHeaderGoodFocus(event) {
            clearTimeout(this.headerGoodBlurTimer);
            this.nameSuggestClose();
            const el = event.target;
            this.headerGoodOpen = true;
            const q = (el.value || '').trim();
            if (q.length >= 2) {
                this.$nextTick(() => this.runHeaderGoodFetch(el));
            }
        },
        onHeaderGoodBlur() {
            this.headerGoodBlurTimer = setTimeout(() => {
                this.headerGoodOpen = false;
            }, 180);
        },
        onHeaderGoodInput(event) {
            const el = event.target;
            const q = (el.value || '').trim();
            clearTimeout(this.headerGoodTimer);
            this.nameSuggestClose();
            if (q.length < 2) {
                this.headerGoodItems = [];
                this.headerGoodLoading = false;
                this.headerGoodNoHits = false;
                return;
            }
            this.headerGoodOpen = true;
            this.headerGoodLoading = true;
            this.headerGoodNoHits = false;
            this.headerGoodItems = [];
            this.headerGoodTimer = setTimeout(() => this.runHeaderGoodFetch(el), 280);
        },
        async runHeaderGoodFetch(el) {
            const q = (this.headerGoodQuery || '').trim();
            if (q.length < 2) return;
            try {
                let url = this.goodsSearchUrl + '?q=' + encodeURIComponent(q);
                if (this.warehouseId > 0) {
                    url += '&warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                }
                const res = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('search');
                const data = await res.json();
                this.headerGoodItems = Array.isArray(data) ? data : [];
                this.headerGoodNoHits = this.headerGoodItems.length === 0;
            } catch (e) {
                this.headerGoodItems = [];
                this.headerGoodNoHits = false;
            } finally {
                this.headerGoodLoading = false;
            }
        },
        purchaseLineLooksBlank(row) {
            if (!row) return false;
            const t = (v) => String(v ?? '').trim();
            if (t(row.name)) return false;
            if (t(row.article_code)) return false;
            if (t(row.barcode)) return false;
            if (t(row.markup_percent)) return false;
            if (t(row.quantity)) return false;
            if (t(row.unit_price)) return false;
            if (t(row.sale_price)) return false;
            const uNorm = t(row.unit)
                .toLowerCase()
                .replace(/\./g, '')
                .replace(/\s/g, '');
            if (uNorm !== '' && uNorm !== 'шт') return false;
            return true;
        },
        applyNewPurchaseLineOrPush(row) {
            if (
                this.lines.length === 1 &&
                this.purchaseLineLooksBlank(this.lines[0])
            ) {
                Object.assign(this.lines[0], row);
                this.selectedRow = 0;
                return;
            }
            this.lines.push(row);
            this.selectedRow = this.lines.length - 1;
        },
        appendLineFromCatalogItem(item) {
            clearTimeout(this.headerGoodBlurTimer);
            if (!item) return;
            const gid = item.id != null && item.id !== '' ? String(item.id) : '';
            const idx = gid !== '' ? this.lines.findIndex((r) => String(r.good_id || '') === gid) : -1;
            if (idx >= 0) {
                const row = this.lines[idx];
                let cur = this.parsePurchaseNum(row.quantity);
                if (!Number.isFinite(cur) || cur < 0) {
                    cur = 0;
                }
                row.quantity = String(cur + 1);
                this.selectedRow = idx;
            } else {
                const row = emptyLine();
                this.fillLineFromPurchaseGood(row, item);
                row.quantity = '1';
                this.applyNewPurchaseLineOrPush(row);
            }
            this.headerGoodOpen = true;
            this.nameSuggestClose();
            this.$nextTick(() => {
                clearTimeout(this.headerGoodBlurTimer);
                const anchor = document.querySelector('[data-pr-header-good-input]');
                if (anchor && anchor.focus) {
                    anchor.focus();
                }
                clearTimeout(this.headerGoodBlurTimer);
            });
        },
        appendLineFromHeaderFreeText() {
            clearTimeout(this.headerGoodBlurTimer);
            const q = (this.headerGoodQuery || '').trim();
            if (q.length < 2) return;
            const row = emptyLine();
            row.name = q;
            row.quantity = '1';
            this.applyNewPurchaseLineOrPush(row);
            this.headerGoodOpen = true;
            this.nameSuggestClose();
            this.$nextTick(() => {
                clearTimeout(this.headerGoodBlurTimer);
                const anchor = document.querySelector('[data-pr-header-good-input]');
                if (anchor && anchor.focus) {
                    anchor.focus();
                }
                clearTimeout(this.headerGoodBlurTimer);
            });
        },
        closeNameSuggestKeepingTypedName() {
            const i = this.nameSuggestRow;
            if (i !== null && this.lines[i]) {
                const n = String(this.lines[i].name ?? '').trim();
                this.lines[i].name = n;
            }
            this.nameSuggestClose();
        },
        onHeaderGoodEnter(e) {
            e.preventDefault();
            if (this.headerGoodLoading) {
                return;
            }
            if (this.headerGoodItems.length === 1) {
                this.appendLineFromCatalogItem(this.headerGoodItems[0]);
                return;
            }
            const q = (this.headerGoodQuery || '').trim();
            if (q.length >= 2 && this.headerGoodItems.length === 0) {
                this.openNewGoodModal(q);
            }
        },
        copyGoodName(item, event) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }
            if (!item || item.name == null) return;
            const t = String(item.name);
            if (!t.trim()) return;
            const afterCopy = () => {
                clearTimeout(this.copyFeedbackTimer);
                if (item.id != null && item.id !== '') {
                    this.copyFeedbackGoodId = item.id;
                    this.copyFeedbackTimer = setTimeout(() => {
                        this.copyFeedbackGoodId = null;
                        this.copyFeedbackTimer = null;
                    }, 2200);
                }
            };
            const fallbackExec = () => {
                try {
                    const ta = document.createElement('textarea');
                    ta.value = t;
                    ta.setAttribute('readonly', '');
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    const ok = document.execCommand('copy');
                    document.body.removeChild(ta);

                    return ok;
                } catch (_) {
                    return false;
                }
            };
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(t).then(afterCopy).catch(() => {
                    if (fallbackExec()) {
                        afterCopy();
                    }
                });
            } else if (fallbackExec()) {
                afterCopy();
            }
        },
        onSupplierFocus(event) {
            clearTimeout(this.cpSuggestBlurTimer);
            this.headerGoodClose();
            this.nameSuggestClose();
            this.refreshCpSuggestPosition(event.target);
        },
        onSupplierBlur() {
            this.cpSuggestBlurTimer = setTimeout(() => {
                const root = this.$refs.supplierRoot;
                if (root && typeof root.contains === 'function' && root.contains(document.activeElement)) {
                    return;
                }
                this.counterpartySuggestClose();
            }, 250);
        },
        onSupplierInput(event) {
            const el = event.target;
            this.supplierName = el.value || '';
            this.refreshCpSuggestPosition(el);
            this.cpQuickOpen = false;
            this.cpQuickError = '';
            const q = (el.value || '').trim();
            clearTimeout(this.cpSuggestTimer);
            if (q.length < 2) {
                this.counterpartySuggestClose();
                return;
            }
            this.headerGoodClose();
            this.nameSuggestClose();
            this.cpSuggestLoading = true;
            this.cpSuggestNoHits = false;
            this.cpSuggestItems = [];
            this.cpSuggestTimer = setTimeout(async () => {
                try {
                    if (!this.counterpartySearchUrl) {
                        this.cpSuggestItems = [];
                        this.cpSuggestNoHits = true;
                        return;
                    }
                    const sep = this.counterpartySearchUrl.includes('?') ? '&' : '?';
                    const url = this.counterpartySearchUrl + sep + 'q=' + encodeURIComponent(q);
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('search');
                    const data = await res.json();
                    this.cpSuggestItems = Array.isArray(data) ? data : [];
                    this.cpSuggestNoHits = this.cpSuggestItems.length === 0;
                } catch (e) {
                    this.cpSuggestItems = [];
                    this.cpSuggestNoHits = true;
                } finally {
                    this.cpSuggestLoading = false;
                    this.$nextTick(() => this.refreshCpSuggestPosition(el));
                }
            }, 280);
        },
        pickCounterparty(item) {
            clearTimeout(this.cpSuggestBlurTimer);
            if (!item) return;
            const label = (item.full_name && String(item.full_name).trim()) || (item.name && String(item.name).trim()) || '';
            this.supplierName = label;
            this.counterpartySuggestClose();
        },
        openCpQuickAdd(event) {
            if (event) event.preventDefault();
            clearTimeout(this.cpSuggestBlurTimer);
            const el = document.getElementById('pr_finalize_supplier');
            const q = (el && el.value ? el.value : this.supplierName || '').trim();
            if (q.length >= 2) {
                this.supplierName = q;
            }
            this.cpQuickLegalForm = 'osoo';
            this.cpQuickError = '';
            this.cpQuickOpen = true;
            this.cpSuggestItems = [];
            this.$nextTick(() => {
                if (el && el.getBoundingClientRect) this.refreshCpSuggestPosition(el);
            });
        },
        async submitCpQuickAdd() {
            const name = (this.supplierName || '').trim();
            if (name.length < 1) {
                this.cpQuickError = 'Введите наименование.';
                return;
            }
            if (!this.counterpartyQuickUrl) {
                this.cpQuickError = 'Сохранение недоступно.';
                return;
            }
            const token =
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            this.cpQuickSaving = true;
            this.cpQuickError = '';
            try {
                const res = await fetch(this.counterpartyQuickUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({
                        name,
                        legal_form: this.cpQuickLegalForm,
                        kind: 'supplier',
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    let msg = 'Не удалось сохранить.';
                    if (data && typeof data === 'object') {
                        if (data.message && typeof data.message === 'string') {
                            msg = data.message;
                        } else if (data.errors && typeof data.errors === 'object') {
                            const first = Object.values(data.errors).flat().find((e) => typeof e === 'string');
                            if (first) {
                                msg = first;
                            }
                        }
                    }
                    this.cpQuickError = msg;
                    return;
                }
                const label =
                    (data.full_name && String(data.full_name).trim()) ||
                    (data.name && String(data.name).trim()) ||
                    name;
                this.supplierName = label;
                this.cpQuickOpen = false;
                this.counterpartySuggestClose();
            } catch (e) {
                this.cpQuickError = 'Ошибка сети.';
            } finally {
                this.cpQuickSaving = false;
            }
        },
        genBarcodesEmptyOnly() {
            if (typeof window.obGenEan13 !== 'function') {
                window.alert('Генератор штрихкодов не загружен. Обновите страницу.');
                return;
            }
            const used = new Set(this.lines.map((r) => (r.barcode || '').trim()).filter(Boolean));
            let n = 0;
            this.lines.forEach((row) => {
                if ((row.barcode || '').trim() !== '') return;
                let code = '';
                for (let k = 0; k < 60; k++) {
                    code = window.obGenEan13();
                    if (!used.has(code)) break;
                }
                row.barcode = code;
                used.add(code);
                n++;
            });
            if (n === 0) window.alert('Нет строк с пустым штрихкодом.');
        },
        addRow() {
            this.closeAllSuggests();
            this.lines.push(emptyLine());
            this.selectedRow = this.lines.length - 1;
        },
        removeSelectedRow() {
            if (this.lines.length <= 1) return;
            this.lines.splice(this.selectedRow, 1);
            this.selectedRow = Math.min(this.selectedRow, this.lines.length - 1);
            this.closeAllSuggests();
        },
        removeLineAt(index) {
            const i = typeof index === 'number' ? index : parseInt(String(index), 10);
            if (!Number.isFinite(i) || i < 0 || i >= this.lines.length) {
                return;
            }
            if (this.lines.length <= 1) {
                return;
            }
            this.lines.splice(i, 1);
            if (this.selectedRow === i) {
                this.selectedRow = Math.min(i, this.lines.length - 1);
            } else if (this.selectedRow > i) {
                this.selectedRow--;
            }
            this.closeAllSuggests();
        },
        clearSelectedRow() {
            this.closeAllSuggests();
            const row = this.lines[this.selectedRow];
            if (!row) return;
            const fresh = emptyLine();
            Object.keys(fresh).forEach((k) => {
                row[k] = fresh[k];
            });
        },
        moveUp() {
            const i = this.selectedRow;
            if (i <= 0) return;
            const next = this.lines.slice();
            [next[i - 1], next[i]] = [next[i], next[i - 1]];
            this.lines = next;
            this.selectedRow = i - 1;
            this.closeAllSuggests();
        },
        moveDown() {
            const i = this.selectedRow;
            if (i >= this.lines.length - 1) return;
            const next = this.lines.slice();
            [next[i], next[i + 1]] = [next[i + 1], next[i]];
            this.lines = next;
            this.selectedRow = i + 1;
            this.closeAllSuggests();
        },
        openDraftPrint() {
            const dateEl =
                document.getElementById('pr_finalize_document_date') || document.getElementById('document_date');
            const rawDate = dateEl && dateEl.value ? dateEl.value : '';
            const months = [
                'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
            ];
            let titleDate = '';
            if (/^\d{4}-\d{2}-\d{2}$/.test(rawDate)) {
                const [y, m, d] = rawDate.split('-').map((x) => parseInt(x, 10));
                titleDate = `${d} ${months[m - 1] || ''} ${y} г.`;
            } else {
                titleDate = rawDate || '—';
            }
            const rows = this.lines.filter((r) => String(r.name || '').trim() !== '');
            if (rows.length === 0) {
                window.alert('Добавьте хотя бы одну строку с наименованием.');
                return;
            }
            const esc = (s) =>
                String(s ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            const fmtMoney = (n) => {
                if (!Number.isFinite(n)) return '—';
                return n
                    .toFixed(2)
                    .replace('.', ',')
                    .replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
            };
            const fmtQty = (qRaw, unit) => {
                const u = (unit || 'шт.').trim() || 'шт.';
                const n = parseFloat(String(qRaw ?? '').replace(/\s/g, '').replace(',', '.'));
                if (!Number.isFinite(n)) return `— ${u}`;
                if (Math.abs(n - Math.round(n)) < 1e-5) {
                    return `${Math.round(n).toLocaleString('en-US')} ${u}`;
                }
                return `${String(qRaw ?? '').replace(/\s/g, '').replace(',', '.')} ${u}`;
            };
            let body = '';
            let totalSum = 0;
            rows.forEach((row, i) => {
                const q = parseFloat(String(row.quantity ?? '').replace(/\s/g, '').replace(',', '.'));
                const p = parseFloat(String(row.unit_price ?? '').replace(/\s/g, '').replace(',', '.'));
                let sumVal = null;
                if (Number.isFinite(q) && Number.isFinite(p)) {
                    sumVal = q * p;
                    totalSum += sumVal;
                }
                const sumStr = sumVal !== null ? fmtMoney(sumVal) : '—';
                const priceStr = Number.isFinite(p) ? fmtMoney(p) : '—';
                body += `<tr>
                    <td style="border:1px solid #000;padding:5px 6px;text-align:center">${i + 1}</td>
                    <td style="border:1px solid #000;padding:5px 6px">${esc(row.name)}</td>
                    <td style="border:1px solid #000;padding:5px 6px">${esc(fmtQty(row.quantity, row.unit))}</td>
                    <td style="border:1px solid #000;padding:5px 6px;text-align:right">${priceStr}</td>
                    <td style="border:1px solid #000;padding:5px 6px;text-align:right">${sumStr}</td>
                </tr>`;
            });
            const orgHeader = esc(this.branchName || '—');
            const wh = esc(this.warehouseName || '');
            const sup = esc(this.supplierName || '');
            const totalStr = fmtMoney(totalSum);
            const html = `<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Черновик накладной</title>
                <style>
                    body{font-family:Tahoma,Arial,sans-serif;font-size:10pt;color:#000;padding:16px;line-height:1.4;}
                    .doc-title{font-size:12pt;font-weight:bold;text-align:center;margin:0 0 8px 0;padding-bottom:6px;border-bottom:1px solid #000;}
                    .meta-block{margin:18px 0 22px 0;}
                    .meta-row{margin-bottom:6px;}
                    .lbl{display:inline-block;min-width:8.5em;}
                    table.grid{width:100%;border-collapse:collapse;font-size:9pt;margin:0 0 12px 0;}
                    table.grid th,table.grid td{border:1px solid #000;padding:5px 6px;vertical-align:middle;}
                    table.grid th{font-weight:bold;text-align:left;background:#fff;}
                    table.grid th.c,table.grid td.c{text-align:center;}
                    table.grid th.num,table.grid td.num{text-align:right;}
                    .totals-wrap{display:table;width:100%;margin-top:4px;}
                    .totals-left{display:table-cell;width:55%;}
                    .totals-right{display:table-cell;width:45%;text-align:right;font-size:10pt;vertical-align:top;}
                    .totals-right .row{margin-bottom:4px;}
                    .footer-line{margin-top:20px;font-size:10pt;}
                    .amount-words{margin-top:10px;font-weight:bold;font-size:10pt;}
                    .signatures{margin-top:36px;width:100%;display:table;font-size:10pt;}
                    .sign-left,.sign-right{display:table-cell;width:50%;}
                    .sign-right{text-align:right;}
                    .no-print{margin:16px 0;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;}
                    @media print{.no-print{display:none!important;}body{padding:0;}}
                </style></head><body>
                <div class="no-print">
                    <button type="button" onclick="window.print()" style="padding:8px 16px;margin-right:8px;cursor:pointer;border-radius:6px;border:1px solid #059669;background:#059669;color:#fff;font-size:11pt;">Печать</button>
                    <span style="font-size:10pt;color:#64748b;">PDF: в окне печати выберите «Сохранить как PDF».</span>
                </div>
                <h1 class="doc-title">Накладная на поступление (черновик) от ${esc(titleDate)}</h1>
                <div class="meta-block">
                    <div class="meta-row"><span class="lbl">Организация:</span> ${orgHeader}</div>
                    <div class="meta-row"><span class="lbl">Склад:</span> ${wh || '—'}</div>
                    <div class="meta-row"><span class="lbl">Поставщик:</span> ${sup || '—'}</div>
                </div>
                <table class="grid">
                    <thead><tr>
                        <th style="width:2.2rem;" class="c">№</th>
                        <th>Товар</th>
                        <th style="width:8rem;">Количество</th>
                        <th style="width:5rem;" class="num">Цена</th>
                        <th style="width:5.5rem;" class="num">Сумма</th>
                    </tr></thead>
                    <tbody>${body}</tbody>
                </table>
                <div class="totals-wrap">
                    <div class="totals-left"></div>
                    <div class="totals-right">
                        <div class="row"><span>Итого:</span> <strong>${totalStr}</strong></div>
                        <div class="row">в том числе НДС: ________________</div>
                        <div class="row">в том числе НСП: ________________</div>
                    </div>
                </div>
                <div class="footer-line">Всего наименований ${rows.length}, на сумму ${totalStr}</div>
                <div class="amount-words">Сумма прописью уточняется после сохранения документа.</div>
                <div class="signatures">
                    <div class="sign-left">Отпустил _______________________</div>
                    <div class="sign-right">Получил _______________________</div>
                </div>
                <p style="margin-top:16px;font-size:9pt;color:#64748b;">Черновик по полям формы. После проведения документа используйте печать из журнала — там будет полная сумма прописью.</p>
                </body></html>`;
            const w = window.open('', '_blank');
            if (!w) {
                window.alert('Разрешите всплывающие окна для печати.');
                return;
            }
            w.document.open();
            w.document.write(html);
            w.document.close();
        },
        };
    });

    Alpine.data('legalEntitySaleForm', () => {
        const emptyLine = () => ({
            good_id: '',
            article_code: '',
            name: '',
            barcode: '',
            category: '',
            unit: 'шт.',
            quantity: '',
            unit_price: '',
            wholesale_price: '',
        });

        const crInit =
            typeof window !== 'undefined' &&
            window.__customerReturnInit &&
            typeof window.__customerReturnInit === 'object'
                ? window.__customerReturnInit
                : null;
        const lesInit =
            typeof window !== 'undefined' &&
            window.__legalEntitySaleInit &&
            typeof window.__legalEntitySaleInit === 'object'
                ? window.__legalEntitySaleInit
                : null;
        const init = crInit ?? lesInit ?? {};
        const isCustomerReturn = crInit !== null;
        const initialLines = init.lines;
        const urls = init.urls;
        const initialBuyerName = init.buyerName;
        const initialBuyerPin = init.buyerPin;
        const rawCpId = init.counterpartyId;
        const initialCounterpartyId =
            rawCpId === null || rawCpId === undefined || rawCpId === ''
                ? null
                : (() => {
                      const n = parseInt(String(rawCpId), 10);
                      return Number.isFinite(n) && n > 0 ? n : null;
                  })();
        const warehouseId =
            typeof init.warehouseId === 'number' && !Number.isNaN(init.warehouseId)
                ? init.warehouseId
                : parseInt(String(init.warehouseId ?? '0'), 10) || 0;

        let lines = Array.isArray(initialLines) ? initialLines.map((r) => ({ ...r })) : [];
        if (lines.length === 0) {
            lines = [emptyLine()];
        }
        lines = lines.map((r) => ({
            article_code: r.article_code ?? '',
            name: r.name ?? '',
            barcode: r.barcode ?? '',
            category: r.category ?? '',
            unit: r.unit ?? 'шт.',
            quantity: r.quantity ?? '',
            unit_price: r.unit_price ?? '',
            wholesale_price: r.wholesale_price ?? '',
        }));

        const u = urls && typeof urls === 'object' ? urls : {};
        const goodsSearchUrl = typeof u.goodsSearch === 'string' ? u.goodsSearch : '';
        const goodsQuickStoreUrl = typeof u.goodsQuickStore === 'string' ? u.goodsQuickStore : '';
        const counterpartySearchUrl = typeof u.counterpartySearch === 'string' ? u.counterpartySearch : '';
        const counterpartyQuickUrl = typeof u.counterpartyQuick === 'string' ? u.counterpartyQuick : '';
        const branchName = typeof init.branchName === 'string' ? init.branchName : '';
        const warehouseName = typeof init.warehouseName === 'string' ? init.warehouseName : '';

        return {
            lines,
            selectedRow: 0,
            moreOpen: false,
            warehouseId,
            isCustomerReturn,
            buyerName: typeof initialBuyerName === 'string' ? initialBuyerName : '',
            buyerPin: typeof initialBuyerPin === 'string' ? initialBuyerPin : '',
            counterpartyId: initialCounterpartyId,
            goodsSearchUrl,
            goodsQuickStoreUrl,
            counterpartySearchUrl,
            counterpartyQuickUrl,
            branchName,
            warehouseName,
            lesHeaderQuery: '',
            lesHeaderOpen: false,
            lesHeaderLoading: false,
            lesHeaderItems: [],
            lesHeaderNoHits: false,
            lesHeaderTimer: null,
            lesHeaderBlurTimer: null,
            lesHeaderCopyFeedbackGoodId: null,
            lesHeaderCopyFeedbackTimer: null,
            nameSuggestRow: null,
            nameSuggestItems: [],
            nameSuggestLoading: false,
            nameSuggestNoHits: false,
            nameSuggestTimer: null,
            nameSuggestBlurTimer: null,
            suggestPos: { top: 0, left: 0, width: 280 },
            cpSuggestItems: [],
            cpSuggestLoading: false,
            cpSuggestNoHits: false,
            cpSuggestTimer: null,
            cpSuggestBlurTimer: null,
            cpSuggestPos: { top: 0, left: 0, width: 320 },
            cpQuickOpen: false,
            cpQuickLegalForm: 'osoo',
            cpQuickSaving: false,
            cpQuickError: '',
            newGoodModalOpen: false,
            newGoodSaving: false,
            newGoodError: '',
            newGoodForm: {
                name: '',
                barcode: '',
                category: '',
                unit: 'шт.',
                quantity: '1',
                unit_price: '',
                wholesale_price: '',
                sale_price: '',
                oem: '',
                factory_number: '',
                min_stock: '',
            },
            resetLesNewGoodForm() {
                this.newGoodForm = {
                    name: '',
                    barcode: '',
                    category: '',
                    unit: 'шт.',
                    quantity: '1',
                    unit_price: '',
                    wholesale_price: '',
                    sale_price: '',
                    oem: '',
                    factory_number: '',
                    min_stock: '',
                };
            },
            openNewGoodModal(presetName) {
                this.closeAllSuggests();
                this.resetLesNewGoodForm();
                const n = presetName != null ? String(presetName).trim() : '';
                if (n !== '') {
                    this.newGoodForm.name = n;
                }
                this.newGoodError = '';
                this.newGoodModalOpen = true;
                this.$nextTick(() => {
                    document.getElementById('les_new_good_name')?.focus();
                });
            },
            closeNewGoodModal() {
                this.newGoodModalOpen = false;
                this.newGoodError = '';
                this.newGoodSaving = false;
            },
            async submitNewGoodQuickStore() {
                const name = String(this.newGoodForm.name ?? '').trim();
                if (name === '') {
                    this.newGoodError = 'Укажите наименование.';
                    return;
                }
                const qtyRaw = String(this.newGoodForm.quantity ?? '').trim();
                if (qtyRaw === '') {
                    this.newGoodError = 'Укажите количество.';
                    return;
                }
                const qNum = this.parsePurchaseNum(qtyRaw);
                if (!Number.isFinite(qNum) || qNum <= 0) {
                    this.newGoodError = 'Количество должно быть числом больше нуля.';
                    return;
                }
                if (!this.goodsQuickStoreUrl) {
                    this.newGoodError = 'Создание товара недоступно.';
                    return;
                }
                const token =
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                this.newGoodSaving = true;
                this.newGoodError = '';
                const body = { name };
                const addStr = (k, v) => {
                    const t = String(v ?? '').trim();
                    if (t !== '') {
                        body[k] = t;
                    }
                };
                addStr('barcode', this.newGoodForm.barcode);
                addStr('category', this.newGoodForm.category);
                addStr('unit', this.newGoodForm.unit);
                addStr('sale_price', this.newGoodForm.sale_price);
                addStr('wholesale_price', this.newGoodForm.wholesale_price);
                addStr('oem', this.newGoodForm.oem);
                addStr('factory_number', this.newGoodForm.factory_number);
                addStr('min_stock', this.newGoodForm.min_stock);
                if (!body.unit) {
                    body.unit = 'шт.';
                }
                let url = this.goodsQuickStoreUrl;
                if (this.warehouseId > 0) {
                    url += (url.includes('?') ? '&' : '?') + 'warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                }
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify(body),
                    });
                    let data = {};
                    try {
                        data = await res.json();
                    } catch (_) {
                        data = {};
                    }
                    if (!res.ok) {
                        let msg =
                            typeof data.message === 'string'
                                ? data.message
                                : 'Не удалось сохранить товар.';
                        if (data.errors && typeof data.errors === 'object') {
                            const first = Object.values(data.errors).flat()[0];
                            if (typeof first === 'string' && first.trim() !== '') {
                                msg = first;
                            }
                        }
                        this.newGoodError = msg;
                        return;
                    }
                    const item = data;
                    const row = emptyLine();
                    this.fillLesLineFromCatalogItem(row, item);
                    row.quantity = qtyRaw.replace(/\s/g, '').replace(',', '.');
                    const sp = String(this.newGoodForm.sale_price ?? '').trim();
                    if (sp !== '') {
                        row.unit_price = sp.replace(/\s/g, '').replace(',', '.');
                    }
                    const wp = String(this.newGoodForm.wholesale_price ?? '').trim();
                    if (wp !== '') {
                        row.wholesale_price = wp.replace(/\s/g, '').replace(',', '.');
                    }
                    this.applyNewLesLineOrPush(row);
                    this.closeNewGoodModal();
                    this.lesHeaderGoodsClose(false);
                    this.$nextTick(() => {
                        document.querySelector('[data-les-header-good-input]')?.focus?.();
                    });
                } catch (_) {
                    this.newGoodError = 'Ошибка сети. Повторите попытку.';
                } finally {
                    this.newGoodSaving = false;
                }
            },
            lineSum(row) {
                const q = parseFloat(String(row.quantity ?? '').replace(/\s/g, '').replace(',', '.'));
                const p = parseFloat(String(row.unit_price ?? '').replace(/\s/g, '').replace(',', '.'));
                if (!Number.isFinite(q) || !Number.isFinite(p)) return '';
                return (q * p).toFixed(2);
            },
            parsePurchaseNum(v) {
                const n = parseFloat(String(v ?? '').replace(/\s/g, '').replace(',', '.'));
                return Number.isFinite(n) ? n : NaN;
            },
            /** Одна строка под наименованием в подсказке поиска товара (юрлицо / возвраты). */
            goodsSuggestCompactMeta(item) {
                const p = this.goodsSuggestMetaParts(item);
                return p.map((x) => x.label + ' ' + x.value).join(', ');
            },
            /** Подпись + значение для оформления отдельными span (жирные подписи «ост / цена / опт»). */
            goodsSuggestMetaParts(item) {
                if (!item) return [];
                const out = [];
                const sq = item.stock_quantity;
                if (sq != null && String(sq).trim() !== '') {
                    const n = parseFloat(String(sq).replace(/\s/g, '').replace(',', '.'));
                    const st = Number.isFinite(n) ? String(n) : String(sq).trim();
                    out.push({ label: 'ост', value: st, danger: goodsStockQtyIsSoldOut(sq) });
                }
                const sp = item.sale_price;
                if (sp != null && String(sp).trim() !== '') {
                    out.push({ label: 'цена', value: String(sp).trim() });
                }
                const wp = item.wholesale_price;
                if (wp != null && String(wp).trim() !== '') {
                    out.push({ label: 'опт', value: String(wp).trim() });
                }
                return out;
            },
            goodsStockQtySoldOut(v) {
                return goodsStockQtyIsSoldOut(v);
            },
            refreshSuggestPosition(el) {
                if (!el || !el.getBoundingClientRect) return;
                const r = el.getBoundingClientRect();
                const w = Math.max(r.width, 260);
                let left = r.left;
                if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8);
                this.suggestPos = { top: r.bottom + 4, left, width: w };
            },
            refreshCpSuggestPosition(el) {
                if (!el || !el.getBoundingClientRect) return;
                const r = el.getBoundingClientRect();
                const w = Math.max(r.width, 280);
                let left = r.left;
                if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8);
                this.cpSuggestPos = { top: r.bottom + 4, left, width: w };
            },
            nameSuggestClose() {
                clearTimeout(this.nameSuggestTimer);
                this.nameSuggestItems = [];
                this.nameSuggestRow = null;
                this.nameSuggestLoading = false;
                this.nameSuggestNoHits = false;
            },
            counterpartySuggestClose() {
                clearTimeout(this.cpSuggestTimer);
                this.cpSuggestItems = [];
                this.cpSuggestLoading = false;
                this.cpSuggestNoHits = false;
                this.cpQuickOpen = false;
                this.cpQuickError = '';
            },
            lesHeaderGoodsClose(clearQuery = true) {
                clearTimeout(this.lesHeaderTimer);
                clearTimeout(this.lesHeaderBlurTimer);
                clearTimeout(this.lesHeaderCopyFeedbackTimer);
                this.lesHeaderCopyFeedbackTimer = null;
                this.lesHeaderCopyFeedbackGoodId = null;
                if (clearQuery) {
                    this.lesHeaderQuery = '';
                }
                this.lesHeaderItems = [];
                this.lesHeaderLoading = false;
                this.lesHeaderNoHits = false;
                this.lesHeaderOpen = false;
            },
            copyLesHeaderGoodName(item, event) {
                if (event) {
                    event.stopPropagation();
                    event.preventDefault();
                }
                if (!item || item.name == null) return;
                const t = String(item.name);
                if (!t.trim()) return;
                const afterCopy = () => {
                    clearTimeout(this.lesHeaderCopyFeedbackTimer);
                    if (item.id != null && item.id !== '') {
                        this.lesHeaderCopyFeedbackGoodId = item.id;
                        this.lesHeaderCopyFeedbackTimer = setTimeout(() => {
                            this.lesHeaderCopyFeedbackGoodId = null;
                            this.lesHeaderCopyFeedbackTimer = null;
                        }, 2200);
                    }
                };
                const fallbackExec = () => {
                    try {
                        const ta = document.createElement('textarea');
                        ta.value = t;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'fixed';
                        ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.select();
                        const ok = document.execCommand('copy');
                        document.body.removeChild(ta);

                        return ok;
                    } catch (_) {
                        return false;
                    }
                };
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(t).then(afterCopy).catch(() => {
                        if (fallbackExec()) {
                            afterCopy();
                        }
                    });
                } else if (fallbackExec()) {
                    afterCopy();
                }
            },
            lesLineLooksBlank(row) {
                if (!row) return false;
                const t = (v) => String(v ?? '').trim();
                if (t(row.name)) return false;
                if (t(row.article_code)) return false;
                if (t(row.barcode)) return false;
                if (t(row.category)) return false;
                if (t(row.quantity)) return false;
                if (t(row.unit_price)) return false;
                if (t(row.wholesale_price)) return false;
                const uNorm = t(row.unit)
                    .toLowerCase()
                    .replace(/\./g, '')
                    .replace(/\s/g, '');
                if (uNorm !== '' && uNorm !== 'шт') return false;
                return true;
            },
            applyNewLesLineOrPush(row) {
                if (this.lines.length === 1 && this.lesLineLooksBlank(this.lines[0])) {
                    Object.assign(this.lines[0], row);
                    this.selectedRow = 0;
                    return;
                }
                this.lines.push(row);
                this.selectedRow = this.lines.length - 1;
            },
            fillLesLineFromCatalogItem(row, item) {
                if (!row || !item) return;
                row.good_id =
                    item.id !== undefined && item.id !== null && item.id !== ''
                        ? String(item.id)
                        : '';
                row.name = item.name || '';
                row.article_code = item.article_code || '';
                row.barcode = item.barcode != null && item.barcode !== '' ? String(item.barcode) : '';
                row.category = item.category != null && item.category !== '' ? String(item.category) : '';
                row.unit = item.unit && String(item.unit).trim() ? String(item.unit).trim() : 'шт.';
                row.wholesale_price =
                    item.wholesale_price != null && item.wholesale_price !== ''
                        ? String(item.wholesale_price)
                        : '';
                if (item.sale_price != null && item.sale_price !== '') {
                    row.unit_price = String(item.sale_price);
                }
            },
            appendLesLineFromCatalogItem(item) {
                clearTimeout(this.lesHeaderBlurTimer);
                if (!item) return;

                const idStr =
                    item.id !== undefined && item.id !== null && item.id !== '' ? String(item.id) : '';
                const itemArt = String(item.article_code ?? '').trim();

                for (let i = this.lines.length - 1; i >= 0; i--) {
                    const row = this.lines[i];
                    const rowId = String(row.good_id ?? '').trim();
                    const rowArt = String(row.article_code ?? '').trim();
                    let match = false;
                    if (idStr !== '' && rowId !== '') {
                        match = rowId === idStr;
                    } else if (itemArt !== '' && rowArt !== '') {
                        match = rowArt === itemArt;
                    }
                    if (!match) continue;

                    const raw = String(row.quantity ?? '')
                        .trim()
                        .replace(/\s/g, '')
                        .replace(',', '.');
                    let qPrev = parseFloat(raw);
                    if (!Number.isFinite(qPrev) || qPrev <= 0) qPrev = 0;
                    const next = qPrev + 1;
                    row.quantity = Number.isInteger(next) ? String(next) : String(next);
                    this.selectedRow = i;
                    this.nameSuggestClose();
                    this.counterpartySuggestClose();
                    const anchor = document.querySelector('[data-les-header-good-input]');
                    if (anchor?.focus) {
                        this.$nextTick(() => anchor.focus());
                    }
                    return;
                }

                const row = emptyLine();
                this.fillLesLineFromCatalogItem(row, item);
                row.quantity = '1';
                this.applyNewLesLineOrPush(row);
                this.nameSuggestClose();
                this.counterpartySuggestClose();
                const anchor = document.querySelector('[data-les-header-good-input]');
                if (anchor?.focus) {
                    this.$nextTick(() => anchor.focus());
                }
            },
            appendLesLineFromHeaderFreeText() {
                clearTimeout(this.lesHeaderBlurTimer);
                const q = (this.lesHeaderQuery || '').trim();
                if (q.length < 2) return;
                const row = emptyLine();
                row.name = q;
                this.applyNewLesLineOrPush(row);
                this.lesHeaderGoodsClose(false);
                const anchor = document.querySelector('[data-les-header-good-input]');
                if (anchor && anchor.focus) anchor.focus();
            },
            onLesHeaderGoodsFocus(event) {
                clearTimeout(this.lesHeaderBlurTimer);
                this.nameSuggestClose();
                this.counterpartySuggestClose();
                const el = event.target;
                this.lesHeaderOpen = true;
                const q = (el.value || '').trim();
                if (q.length >= 2) {
                    this.$nextTick(() => this.runLesHeaderGoodsFetch(el));
                }
            },
            onLesHeaderGoodsBlur() {
                this.lesHeaderBlurTimer = setTimeout(() => {
                    this.lesHeaderOpen = false;
                }, 180);
            },
            onLesHeaderGoodsInput(event) {
                const el = event.target;
                const q = (el.value || '').trim();
                clearTimeout(this.lesHeaderTimer);
                this.nameSuggestClose();
                this.counterpartySuggestClose();
                if (q.length < 2) {
                    this.lesHeaderItems = [];
                    this.lesHeaderLoading = false;
                    this.lesHeaderNoHits = false;
                    return;
                }
                this.lesHeaderOpen = true;
                this.lesHeaderLoading = true;
                this.lesHeaderNoHits = false;
                this.lesHeaderItems = [];
                this.lesHeaderTimer = setTimeout(() => this.runLesHeaderGoodsFetch(el), 280);
            },
            async runLesHeaderGoodsFetch(el) {
                const q = (this.lesHeaderQuery || '').trim();
                if (q.length < 2) return;
                try {
                    let url = this.goodsSearchUrl + '?q=' + encodeURIComponent(q);
                    if (this.warehouseId > 0) {
                        url += '&warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                    }
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('search');
                    const data = await res.json();
                    this.lesHeaderItems = Array.isArray(data) ? data : [];
                    this.lesHeaderNoHits = this.lesHeaderItems.length === 0;
                } catch (e) {
                    this.lesHeaderItems = [];
                    this.lesHeaderNoHits = false;
                } finally {
                    this.lesHeaderLoading = false;
                }
            },
            onLesHeaderGoodsEnter(e) {
                e.preventDefault();
                if (this.lesHeaderLoading) {
                    return;
                }
                if (this.lesHeaderItems.length === 1) {
                    this.appendLesLineFromCatalogItem(this.lesHeaderItems[0]);
                    return;
                }
                const q = (this.lesHeaderQuery || '').trim();
                if (q.length >= 2 && this.lesHeaderItems.length === 0) {
                    this.openNewGoodModal(q);
                }
            },
            repositionLesLineNameSuggest() {
                const ni = this.nameSuggestRow;
                if (
                    ni !== null &&
                    (this.nameSuggestLoading || this.nameSuggestItems.length > 0 || this.nameSuggestNoHits)
                ) {
                    const inp =
                        document.querySelector(`input[name="lines[${ni}][name]"]`) ||
                        document.querySelector(`input[name="lines[${ni}][barcode]"]`);
                    if (inp) this.refreshSuggestPosition(inp);
                }
            },
            closeAllSuggests() {
                if (this.newGoodModalOpen) {
                    this.closeNewGoodModal();
                    return;
                }
                this.nameSuggestClose();
                this.counterpartySuggestClose();
                this.lesHeaderGoodsClose();
            },
            showCpDropdown() {
                if (this.cpQuickOpen) {
                    return true;
                }
                const q = (this.buyerName || '').trim();
                if (q.length < 2) {
                    return false;
                }
                return this.cpSuggestLoading || this.cpSuggestItems.length > 0 || this.cpSuggestNoHits;
            },
            onNameFocus(index, event) {
                clearTimeout(this.nameSuggestBlurTimer);
                this.lesHeaderGoodsClose();
                this.counterpartySuggestClose();
                this.selectedRow = index;
                this.refreshSuggestPosition(event.target);
            },
            onNameBlur() {
                this.nameSuggestBlurTimer = setTimeout(() => this.nameSuggestClose(), 180);
            },
            onBarcodeFocus(index, event) {
                this.onNameFocus(index, event);
            },
            onBarcodeInput(index, event) {
                this.onNameInput(index, event);
            },
            onBarcodeBlur() {
                this.onNameBlur();
            },
            onNameInput(index, event) {
                const el = event.target;
                this.lesHeaderGoodsClose();
                this.refreshSuggestPosition(el);
                const q = (el.value || '').trim();
                clearTimeout(this.nameSuggestTimer);
                if (q.length < 2) {
                    this.nameSuggestClose();
                    return;
                }
                this.counterpartySuggestClose();
                this.nameSuggestRow = index;
                this.nameSuggestLoading = true;
                this.nameSuggestNoHits = false;
                this.nameSuggestItems = [];
                this.nameSuggestTimer = setTimeout(async () => {
                    try {
                        let url = this.goodsSearchUrl + '?q=' + encodeURIComponent(q);
                        if (this.warehouseId > 0) {
                            url += '&warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                        }
                        const res = await fetch(url, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) throw new Error('search');
                        const data = await res.json();
                        if (this.nameSuggestRow !== index) return;
                        this.nameSuggestItems = Array.isArray(data) ? data : [];
                        this.nameSuggestNoHits = this.nameSuggestItems.length === 0;
                    } catch (e) {
                        this.nameSuggestItems = [];
                        this.nameSuggestNoHits = false;
                    } finally {
                        this.nameSuggestLoading = false;
                        this.$nextTick(() => this.refreshSuggestPosition(el));
                    }
                }, 280);
            },
            pickGoodFromSuggest(item) {
                clearTimeout(this.nameSuggestBlurTimer);
                const index = this.nameSuggestRow;
                if (index === null || !item) return;
                const row = this.lines[index];
                if (!row) return;
                this.fillLesLineFromCatalogItem(row, item);
                this.nameSuggestClose();
            },
            async applyWholesalePrices() {
                this.closeAllSuggests();
                const skipped = [];
                for (let i = 0; i < this.lines.length; i++) {
                    const row = this.lines[i];
                    const code = (row.article_code || '').trim();
                    if (!code) {
                        continue;
                    }
                    let parsedWp = parseFloat(
                        String(row.wholesale_price ?? '').replace(/\s/g, '').replace(',', '.')
                    );
                    if (!Number.isFinite(parsedWp) || parsedWp <= 0) {
                        if (!this.goodsSearchUrl) {
                            skipped.push(code);
                            continue;
                        }
                        try {
                            let url =
                                this.goodsSearchUrl +
                                '?q=' +
                                encodeURIComponent(code) +
                                '&exact_article=1&exclude_services=1';
                            if (this.warehouseId > 0) {
                                url += '&warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                            }
                            const res = await fetch(url, {
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });
                            if (!res.ok) {
                                skipped.push(code);
                                continue;
                            }
                            const data = await res.json();
                            const hit = Array.isArray(data) && data[0] ? data[0] : null;
                            if (hit) {
                                row.wholesale_price =
                                    hit.wholesale_price != null && hit.wholesale_price !== ''
                                        ? String(hit.wholesale_price)
                                        : '';
                            }
                        } catch (e) {
                            skipped.push(code);
                            continue;
                        }
                    }
                    parsedWp = parseFloat(
                        String(row.wholesale_price ?? '').replace(/\s/g, '').replace(',', '.')
                    );
                    if (Number.isFinite(parsedWp) && parsedWp > 0) {
                        row.unit_price = String(row.wholesale_price).replace(/\s/g, '');
                    } else {
                        skipped.push(code);
                    }
                }
                if (skipped.length) {
                    window.alert(
                        'Не задана оптовая цена в карточке для артикулов: ' +
                            [...new Set(skipped)].join(', ')
                    );
                }
            },
            onBuyerFocus(event) {
                clearTimeout(this.cpSuggestBlurTimer);
                this.nameSuggestClose();
                this.lesHeaderGoodsClose();
                this.refreshCpSuggestPosition(event.target);
            },
            onBuyerBlur() {
                this.cpSuggestBlurTimer = setTimeout(() => {
                    const root = this.$refs.buyerRoot;
                    if (root && typeof root.contains === 'function' && root.contains(document.activeElement)) {
                        return;
                    }
                    this.counterpartySuggestClose();
                }, 250);
            },
            onBuyerInput(event) {
                const el = event.target;
                this.buyerName = el.value || '';
                this.refreshCpSuggestPosition(el);
                this.cpQuickOpen = false;
                this.cpQuickError = '';
                const q = (el.value || '').trim();
                clearTimeout(this.cpSuggestTimer);
                if (q.length < 2) {
                    this.counterpartySuggestClose();
                    return;
                }
                this.nameSuggestClose();
                this.lesHeaderGoodsClose();
                this.cpSuggestLoading = true;
                this.cpSuggestNoHits = false;
                this.cpSuggestItems = [];
                this.cpSuggestTimer = setTimeout(async () => {
                    try {
                        if (!this.counterpartySearchUrl) {
                            this.cpSuggestItems = [];
                            return;
                        }
                        const sep = this.counterpartySearchUrl.includes('?') ? '&' : '?';
                        const url = this.counterpartySearchUrl + sep + 'q=' + encodeURIComponent(q);
                        const res = await fetch(url, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) throw new Error('search');
                        const data = await res.json();
                        this.cpSuggestItems = Array.isArray(data) ? data : [];
                        this.cpSuggestNoHits = this.cpSuggestItems.length === 0;
                    } catch (e) {
                        this.cpSuggestItems = [];
                        this.cpSuggestNoHits = false;
                    } finally {
                        this.cpSuggestLoading = false;
                        this.$nextTick(() => this.refreshCpSuggestPosition(el));
                    }
                }, 280);
            },
            pickCounterparty(item) {
                clearTimeout(this.cpSuggestBlurTimer);
                if (!item) return;
                const label = (item.full_name && String(item.full_name).trim()) || (item.name && String(item.name).trim()) || '';
                this.buyerName = label;
                if (item.inn != null && String(item.inn).trim() !== '') {
                    this.buyerPin = String(item.inn).replace(/\D/g, '');
                }
                this.counterpartyId =
                    item.id != null && item.id !== '' ? parseInt(String(item.id), 10) || null : null;
                this.counterpartySuggestClose();
            },
            openCpQuickAdd(event) {
                if (event) event.preventDefault();
                clearTimeout(this.cpSuggestBlurTimer);
                const el = document.getElementById('buyer_name');
                const q = (el && el.value ? el.value : this.buyerName || '').trim();
                if (q.length >= 2) {
                    this.buyerName = q;
                }
                this.cpQuickLegalForm = 'osoo';
                this.cpQuickError = '';
                this.cpQuickOpen = true;
                this.cpSuggestItems = [];
                this.$nextTick(() => {
                    if (el && el.getBoundingClientRect) this.refreshCpSuggestPosition(el);
                });
            },
            async submitCpQuickAdd() {
                const name = (this.buyerName || '').trim();
                if (name.length < 1) {
                    this.cpQuickError = 'Введите наименование.';
                    return;
                }
                if (!this.counterpartyQuickUrl) {
                    this.cpQuickError = 'Сохранение недоступно.';
                    return;
                }
                const token =
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                this.cpQuickSaving = true;
                this.cpQuickError = '';
                try {
                    const res = await fetch(this.counterpartyQuickUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({
                            name,
                            legal_form: this.cpQuickLegalForm,
                            kind: 'buyer',
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        let msg = 'Не удалось сохранить.';
                        if (data && typeof data === 'object') {
                            if (data.message && typeof data.message === 'string') {
                                msg = data.message;
                            } else if (data.errors && typeof data.errors === 'object') {
                                const first = Object.values(data.errors).flat().find((e) => typeof e === 'string');
                                if (first) {
                                    msg = first;
                                }
                            }
                        }
                        this.cpQuickError = msg;
                        return;
                    }
                    const label =
                        (data.full_name && String(data.full_name).trim()) ||
                        (data.name && String(data.name).trim()) ||
                        name;
                    this.buyerName = label;
                    this.cpQuickOpen = false;
                    this.counterpartySuggestClose();
                } catch (e) {
                    this.cpQuickError = 'Ошибка сети.';
                } finally {
                    this.cpQuickSaving = false;
                }
            },
            addRow() {
                this.closeAllSuggests();
                this.lines.push(emptyLine());
                this.selectedRow = this.lines.length - 1;
            },
            removeSelectedRow() {
                if (this.lines.length <= 1) return;
                this.lines.splice(this.selectedRow, 1);
                this.selectedRow = Math.min(this.selectedRow, this.lines.length - 1);
                this.moreOpen = false;
                this.closeAllSuggests();
            },
            removeLineAt(index) {
                const i = typeof index === 'number' ? index : parseInt(String(index), 10);
                if (!Number.isFinite(i) || i < 0 || i >= this.lines.length) {
                    return;
                }
                if (this.lines.length <= 1) {
                    return;
                }
                this.lines.splice(i, 1);
                if (this.selectedRow === i) {
                    this.selectedRow = Math.min(i, this.lines.length - 1);
                } else if (this.selectedRow > i) {
                    this.selectedRow--;
                }
                this.moreOpen = false;
                this.closeAllSuggests();
            },
            moveUp() {
                const i = this.selectedRow;
                if (i <= 0) return;
                const next = this.lines.slice();
                [next[i - 1], next[i]] = [next[i], next[i - 1]];
                this.lines = next;
                this.selectedRow = i - 1;
                this.closeAllSuggests();
            },
            moveDown() {
                const i = this.selectedRow;
                if (i >= this.lines.length - 1) return;
                const next = this.lines.slice();
                [next[i], next[i + 1]] = [next[i + 1], next[i]];
                this.lines = next;
                this.selectedRow = i + 1;
                this.closeAllSuggests();
            },
            openDraftPrint() {
                const dateEl =
                    document.querySelector('#prt_finalize_document_date, #pr_finalize_document_date, #document_date');
                const rawDate = dateEl && dateEl.value ? dateEl.value : '';
                const months = [
                    'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                    'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
                ];
                let titleDate = '';
                if (/^\d{4}-\d{2}-\d{2}$/.test(rawDate)) {
                    const [y, m, d] = rawDate.split('-').map((x) => parseInt(x, 10));
                    titleDate = `${d} ${months[m - 1] || ''} ${y} г.`;
                } else {
                    titleDate = rawDate || '—';
                }
                const rows = this.lines.filter((r) => (r.article_code || '').trim() !== '');
                if (rows.length === 0) {
                    window.alert('Добавьте хотя бы одну строку с артикулом.');
                    return;
                }
                const esc = (s) =>
                    String(s ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                const fmtMoney = (n) => {
                    if (!Number.isFinite(n)) return '—';
                    return n
                        .toFixed(2)
                        .replace('.', ',')
                        .replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                };
                const fmtQty = (qRaw, unit) => {
                    const u = (unit || 'шт.').trim() || 'шт.';
                    const n = parseFloat(String(qRaw ?? '').replace(/\s/g, '').replace(',', '.'));
                    if (!Number.isFinite(n)) return `— ${u}`;
                    if (Math.abs(n - Math.round(n)) < 1e-5) {
                        return `${Math.round(n).toLocaleString('en-US')} ${u}`;
                    }
                    return `${String(qRaw ?? '').replace(/\s/g, '').replace(',', '.')} ${u}`;
                };
                let body = '';
                let totalSum = 0;
                rows.forEach((row, i) => {
                    const q = parseFloat(String(row.quantity ?? '').replace(/\s/g, '').replace(',', '.'));
                    const p = parseFloat(String(row.unit_price ?? '').replace(/\s/g, '').replace(',', '.'));
                    let sumVal = null;
                    if (Number.isFinite(q) && Number.isFinite(p)) {
                        sumVal = q * p;
                        totalSum += sumVal;
                    }
                    const sumStr = sumVal !== null ? fmtMoney(sumVal) : '—';
                    const priceStr = Number.isFinite(p) ? fmtMoney(p) : '—';
                    body += `<tr>
                    <td style="border:1px solid #000;padding:5px 6px;text-align:center">${i + 1}</td>
                    <td style="border:1px solid #000;padding:5px 6px">${esc(row.name)}</td>
                    <td style="border:1px solid #000;padding:5px 6px">${esc(fmtQty(row.quantity, row.unit))}</td>
                    <td style="border:1px solid #000;padding:5px 6px;text-align:right">${priceStr}</td>
                    <td style="border:1px solid #000;padding:5px 6px;text-align:right">${sumStr}</td>
                </tr>`;
                });
                const orgHeader = esc(this.branchName || '—');
                const wh = esc(this.warehouseName || '');
                const buyer = esc(this.buyerName || '');
                const commentEl = document.getElementById('les_document_comment');
                const commentRaw =
                    commentEl && commentEl.value ? String(commentEl.value).trim() : '';
                const commentRow =
                    commentRaw !== ''
                        ? `<div class="meta-row"><span class="lbl">Комментарий:</span> <span style="white-space:pre-wrap;">${esc(
                              commentRaw
                          )}</span></div>`
                        : '';
                const totalStr = fmtMoney(totalSum);
                const draftTitle = this.isCustomerReturn
                    ? `Накладная на возврат от клиента (черновик) от ${esc(titleDate)}`
                    : `Накладная на реализацию (черновик) от ${esc(titleDate)}`;
                const draftPageTitle = this.isCustomerReturn ? 'Черновик возврата от клиента' : 'Черновик реализации';
                const priceColLabel = this.isCustomerReturn ? 'Цена возврата' : 'Цена продажи';
                const html = `<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>${draftPageTitle}</title>
                <style>
                    body{font-family:Tahoma,Arial,sans-serif;font-size:10pt;color:#000;padding:16px;line-height:1.4;}
                    .doc-title{font-size:12pt;font-weight:bold;text-align:center;margin:0 0 8px 0;padding-bottom:6px;border-bottom:1px solid #000;}
                    .meta-block{margin:18px 0 22px 0;}
                    .meta-row{margin-bottom:6px;}
                    .lbl{display:inline-block;min-width:8.5em;}
                    table.grid{width:100%;border-collapse:collapse;font-size:9pt;margin:0 0 12px 0;}
                    table.grid th,table.grid td{border:1px solid #000;padding:5px 6px;vertical-align:middle;}
                    table.grid th{font-weight:bold;text-align:left;background:#fff;}
                    table.grid th.c,table.grid td.c{text-align:center;}
                    table.grid th.num,table.grid td.num{text-align:right;}
                    .totals-wrap{display:table;width:100%;margin-top:4px;}
                    .totals-left{display:table-cell;width:55%;}
                    .totals-right{display:table-cell;width:45%;text-align:right;font-size:10pt;vertical-align:top;}
                    .totals-right .row{margin-bottom:4px;}
                    .footer-line{margin-top:20px;font-size:10pt;}
                    .amount-words{margin-top:10px;font-weight:bold;font-size:10pt;}
                    .signatures{margin-top:36px;width:100%;display:table;font-size:10pt;}
                    .sign-left,.sign-right{display:table-cell;width:50%;}
                    .sign-right{text-align:right;}
                    .no-print{margin:16px 0;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;}
                    @media print{.no-print{display:none!important;}body{padding:0;}}
                </style></head><body>
                <div class="no-print">
                    <button type="button" onclick="window.print()" style="padding:8px 16px;margin-right:8px;cursor:pointer;border-radius:6px;border:1px solid #059669;background:#059669;color:#fff;font-size:11pt;">Печать</button>
                    <span style="font-size:10pt;color:#64748b;">PDF: в окне печати выберите «Сохранить как PDF».</span>
                </div>
                <h1 class="doc-title" style="border-top:3px solid #ffcc00;border-bottom:1px solid #000;padding:10px 8px;text-align:center;font-size:12pt;font-weight:bold;color:#1a1a1a;">${draftTitle}</h1>
                <div class="meta-block">
                    <div class="meta-row"><span class="lbl">Организация:</span> ${orgHeader}</div>
                    <div class="meta-row"><span class="lbl">Склад:</span> ${wh || '—'}</div>
                    <div class="meta-row"><span class="lbl">${this.isCustomerReturn ? 'Клиент' : 'Покупатель'}:</span> ${buyer || '—'}</div>
                    ${commentRow}
                </div>
                <table class="grid">
                    <thead><tr>
                        <th style="width:2.2rem;" class="c">№</th>
                        <th>Товар</th>
                        <th style="width:8rem;">Количество</th>
                        <th style="width:5rem;" class="num">${priceColLabel}</th>
                        <th style="width:5.5rem;" class="num">Сумма</th>
                    </tr></thead>
                    <tbody>${body}</tbody>
                </table>
                <div class="totals-wrap">
                    <div class="totals-left"></div>
                    <div class="totals-right">
                        <div class="row"><span>Итого:</span> <strong>${totalStr}</strong></div>
                        <div class="row">в том числе НДС: ________________</div>
                        <div class="row">в том числе НСП: ________________</div>
                    </div>
                </div>
                <div class="footer-line">Всего наименований ${rows.length}, на сумму ${totalStr}</div>
                <div class="amount-words">Сумма прописью уточняется после сохранения документа.</div>
                <div class="signatures">
                    <div class="sign-left">${
                        this.isCustomerReturn
                            ? 'Принял (на склад) _______________________'
                            : 'Отпустил _______________________'
                    }</div>
                    <div class="sign-right">${
                        this.isCustomerReturn ? 'Сдал (клиент) _______________________' : 'Получил _______________________'
                    }</div>
                </div>
                <p style="margin-top:16px;font-size:9pt;color:#64748b;">Черновик по полям формы. После проведения документа используйте печать из журнала (если настроена).</p>
                </body></html>`;
                const w = window.open('', '_blank');
                if (!w) {
                    window.alert('Разрешите всплывающие окна для печати.');
                    return;
                }
                w.document.open();
                w.document.write(html);
                w.document.close();
            },
        };
    });

    Alpine.data('tradeInvoiceCpFilter', () => {
        const init =
            typeof window !== 'undefined' &&
            window.__tradeInvoiceCpInit &&
            typeof window.__tradeInvoiceCpInit === 'object'
                ? window.__tradeInvoiceCpInit
                : {};
        const searchUrl = typeof init.searchUrl === 'string' ? init.searchUrl : '';
        const initialId = parseInt(String(init.counterpartyId ?? '0'), 10) || 0;
        const initialLabel = typeof init.counterpartyLabel === 'string' ? init.counterpartyLabel : '';

        return {
            counterpartyId: initialId,
            query: initialLabel,
            committedLabel: initialLabel,
            cpItems: [],
            cpLoading: false,
            cpNoHits: false,
            cpTimer: null,
            cpBlurTimer: null,
            cpPos: { top: 0, left: 0, width: 280 },
            refreshCpPos(el) {
                if (!el || !el.getBoundingClientRect) return;
                const r = el.getBoundingClientRect();
                const w = Math.max(r.width, 280);
                let left = r.left;
                if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8);
                this.cpPos = { top: r.bottom + 4, left, width: w };
            },
            showCpDropdown() {
                const q = (this.query || '').trim();
                if (q.length < 2) return false;
                return this.cpLoading || this.cpItems.length > 0 || this.cpNoHits;
            },
            onCpFocus(event) {
                clearTimeout(this.cpBlurTimer);
                this.refreshCpPos(event.target);
                const q = (this.query || '').trim();
                if (q.length >= 2) {
                    this.scheduleCpSearch(event.target, q);
                }
            },
            onCpInput(event) {
                const el = event.target;
                this.refreshCpPos(el);
                const q = (this.query || '').trim();
                if (q !== (this.committedLabel || '').trim()) {
                    this.counterpartyId = 0;
                }
                clearTimeout(this.cpTimer);
                if (q.length < 2) {
                    this.cpItems = [];
                    this.cpNoHits = false;
                    this.cpLoading = false;
                    return;
                }
                this.scheduleCpSearch(el, q);
            },
            scheduleCpSearch(el, q) {
                clearTimeout(this.cpTimer);
                this.cpTimer = setTimeout(() => this.runCpSearch(el, q), 280);
            },
            async runCpSearch(el, q) {
                if (!searchUrl || q.length < 2) return;
                this.cpLoading = true;
                this.cpNoHits = false;
                this.cpItems = [];
                try {
                    const sep = searchUrl.includes('?') ? '&' : '?';
                    const url = `${searchUrl}${sep}q=${encodeURIComponent(q)}`;
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('search');
                    const data = await res.json();
                    const rows = Array.isArray(data) ? data : [];
                    this.cpItems = rows.filter((it) => it && it.id);
                    this.cpNoHits = this.cpItems.length === 0;
                } catch {
                    this.cpItems = [];
                    this.cpNoHits = false;
                } finally {
                    this.cpLoading = false;
                    this.$nextTick(() => this.refreshCpPos(el));
                }
            },
            onCpBlur() {
                this.cpBlurTimer = setTimeout(() => {
                    this.cpItems = [];
                    this.cpNoHits = false;
                }, 200);
            },
            pickCounterparty(item) {
                clearTimeout(this.cpBlurTimer);
                if (!item || !item.id) return;
                this.counterpartyId = item.id;
                const label = item.full_name || item.name || '';
                this.query = label;
                this.committedLabel = label;
                this.cpItems = [];
                this.cpNoHits = false;
                this.$nextTick(() => {
                    if (this.$el && typeof this.$el.submit === 'function') {
                        this.$el.submit();
                    }
                });
            },
            clearCpFilter() {
                clearTimeout(this.cpBlurTimer);
                this.counterpartyId = 0;
                this.query = '';
                this.committedLabel = '';
                this.cpItems = [];
                this.cpNoHits = false;
                this.$nextTick(() => {
                    if (this.$el && typeof this.$el.submit === 'function') {
                        this.$el.submit();
                    }
                });
            },
        };
    });

    Alpine.data('tradeInvoiceJournal', () => {
        const cfg =
            typeof window !== 'undefined' &&
            window.__tradeInvoiceJournalInit &&
            typeof window.__tradeInvoiceJournalInit === 'object'
                ? window.__tradeInvoiceJournalInit
                : {};
        const invoiceBase = typeof cfg?.invoiceBase === 'string' ? cfg.invoiceBase : '';
        const mergedPrint = typeof cfg?.mergedPrint === 'string' ? cfg.mergedPrint : '';
        const mergedPdf = typeof cfg?.mergedPdf === 'string' ? cfg.mergedPdf : '';
        const printOrgIdInit = cfg?.printOrgId != null && cfg?.printOrgId !== '' ? cfg.printOrgId : null;

        return {
            selectedIds: [],
            invoiceBase,
            mergedPrint,
            mergedPdf,
            printOrgId: printOrgIdInit,
            /** @param {'summary'|'detail'} format */
            formatSuffixSingle(format) {
                const fmt = format === 'detail' ? 'detail' : 'summary';
                const p = new URLSearchParams();
                if (this.printOrgId != null && this.printOrgId !== '') {
                    p.set('organization_id', String(this.printOrgId));
                }
                if (fmt === 'detail') {
                    p.set('invoice_format', 'detail');
                }
                const s = p.toString();
                return s ? '?' + s : '';
            },
            /** @param {'summary'|'detail'} format */
            openPrint(format) {
                const fmt = format === 'detail' ? 'detail' : 'summary';
                if (this.selectedIds.length === 0 || !this.invoiceBase) return;
                if (this.selectedIds.length === 1) {
                    window.open(
                        this.invoiceBase +
                            '/' +
                            String(this.selectedIds[0]) +
                            '/print' +
                            this.formatSuffixSingle(fmt),
                        '_blank'
                    );
                    return;
                }
                if (!this.mergedPrint) return;
                const q = new URLSearchParams();
                this.selectedIds.forEach((id) => q.append('sale_ids[]', String(id)));
                if (this.printOrgId != null && this.printOrgId !== '') {
                    q.set('organization_id', String(this.printOrgId));
                }
                if (fmt === 'detail') {
                    q.set('invoice_format', 'detail');
                }
                window.open(this.mergedPrint + '?' + q.toString(), '_blank');
            },
            /** @param {'summary'|'detail'} format */
            openPdf(format) {
                const fmt = format === 'detail' ? 'detail' : 'summary';
                if (this.selectedIds.length === 0 || !this.invoiceBase) return;
                if (this.selectedIds.length === 1) {
                    window.location.href =
                        this.invoiceBase +
                        '/' +
                        String(this.selectedIds[0]) +
                        '/pdf' +
                        this.formatSuffixSingle(fmt);
                    return;
                }
                if (!this.mergedPdf) return;
                const q = new URLSearchParams();
                this.selectedIds.forEach((id) => q.append('sale_ids[]', String(id)));
                if (this.printOrgId != null && this.printOrgId !== '') {
                    q.set('organization_id', String(this.printOrgId));
                }
                if (fmt === 'detail') {
                    q.set('invoice_format', 'detail');
                }
                window.location.href = this.mergedPdf + '?' + q.toString();
            },
            bulkSubmit(sent) {
                if (this.selectedIds.length === 0) return;
                const f = this.$refs.bulkForm;
                if (!f) return;
                f.querySelectorAll('input[name="sale_ids[]"]').forEach((e) => e.remove());
                this.selectedIds.forEach((id) => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'sale_ids[]';
                    inp.value = String(id);
                    f.appendChild(inp);
                });
                const hiddenSent = f.querySelector('input[name="payment_invoice_sent"]');
                if (hiddenSent) hiddenSent.value = sent ? '1' : '0';
                f.submit();
            },
        };
    });

    Alpine.data('purchaseReturnForm', () => {
        const emptyLine = () => ({
            article_code: '',
            name: '',
            barcode: '',
            category: '',
            unit: 'шт.',
            quantity: '',
            unit_price: '',
            /** Совпадение с позицией каталога при повторных кликах в шапке (не уходит в форму). */
            prtCatalogGoodId: '',
        });

        const init =
            typeof window !== 'undefined' &&
            window.__purchaseReturnInit &&
            typeof window.__purchaseReturnInit === 'object'
                ? window.__purchaseReturnInit
                : {};
        const initialLines = init.lines;
        const urls = init.urls;
        const initialSupplierName = init.supplierName;
        const warehouseId =
            typeof init.warehouseId === 'number' && !Number.isNaN(init.warehouseId)
                ? init.warehouseId
                : parseInt(String(init.warehouseId ?? '0'), 10) || 0;

        let lines = Array.isArray(initialLines) ? initialLines.map((r) => ({ ...r })) : [];
        if (lines.length === 0) {
            lines = [emptyLine()];
        }
        lines = lines.map((r) => ({
            article_code: r.article_code ?? '',
            name: r.name ?? '',
            barcode: r.barcode ?? '',
            category: r.category ?? '',
            unit: r.unit ?? 'шт.',
            quantity: r.quantity ?? '',
            unit_price: r.unit_price ?? '',
            prtCatalogGoodId: r.prtCatalogGoodId ?? '',
        }));

        const u = urls && typeof urls === 'object' ? urls : {};
        const goodsSearchUrl = typeof u.goodsSearch === 'string' ? u.goodsSearch : '';
        const goodsQuickStoreUrl = typeof u.goodsQuickStore === 'string' ? u.goodsQuickStore : '';
        const counterpartySearchUrl = typeof u.counterpartySearch === 'string' ? u.counterpartySearch : '';
        const counterpartyQuickUrl = typeof u.counterpartyQuick === 'string' ? u.counterpartyQuick : '';
        const branchName = typeof init.branchName === 'string' ? init.branchName : '';
        const warehouseName = typeof init.warehouseName === 'string' ? init.warehouseName : '';
        const openFinalizeOnLoad = init.openFinalizeOnLoad === true;
        const purchaseReturnSwitchWarehouseUrl =
            typeof init.purchaseReturnSwitchWarehouseUrl === 'string'
                ? init.purchaseReturnSwitchWarehouseUrl
                : '';

        return {
            lines,
            selectedRow: 0,
            moreOpen: false,
            finalizeModalOpen: false,
            openFinalizeOnLoad,
            purchaseReturnSwitchWarehouseUrl,
            warehouseId,
            supplierName: typeof initialSupplierName === 'string' ? initialSupplierName : '',
            goodsSearchUrl,
            goodsQuickStoreUrl,
            counterpartySearchUrl,
            counterpartyQuickUrl,
            branchName,
            warehouseName,
            nameSuggestRow: null,
            nameSuggestItems: [],
            nameSuggestLoading: false,
            nameSuggestNoHits: false,
            nameSuggestTimer: null,
            nameSuggestBlurTimer: null,
            suggestPos: { top: 0, left: 0, width: 280 },
            cpSuggestItems: [],
            cpSuggestLoading: false,
            cpSuggestNoHits: false,
            cpSuggestTimer: null,
            cpSuggestBlurTimer: null,
            cpSuggestPos: { top: 0, left: 0, width: 320 },
            cpQuickOpen: false,
            cpQuickLegalForm: 'osoo',
            cpQuickSaving: false,
            cpQuickError: '',
            newGoodModalOpen: false,
            newGoodSaving: false,
            newGoodError: '',
            newGoodForm: {
                name: '',
                barcode: '',
                category: '',
                unit: 'шт.',
                quantity: '1',
                unit_price: '',
                wholesale_price: '',
                sale_price: '',
                oem: '',
                factory_number: '',
                min_stock: '',
            },
            resetPrtNewGoodForm() {
                this.newGoodForm = {
                    name: '',
                    barcode: '',
                    category: '',
                    unit: 'шт.',
                    quantity: '1',
                    unit_price: '',
                    wholesale_price: '',
                    sale_price: '',
                    oem: '',
                    factory_number: '',
                    min_stock: '',
                };
            },
            openNewGoodModal(presetName) {
                this.closeAllSuggests();
                this.resetPrtNewGoodForm();
                const n = presetName != null ? String(presetName).trim() : '';
                if (n !== '') {
                    this.newGoodForm.name = n;
                }
                this.newGoodError = '';
                this.newGoodModalOpen = true;
                this.$nextTick(() => {
                    document.getElementById('prt_new_good_name')?.focus();
                });
            },
            closeNewGoodModal() {
                this.newGoodModalOpen = false;
                this.newGoodError = '';
                this.newGoodSaving = false;
            },
            async submitNewGoodQuickStore() {
                const name = String(this.newGoodForm.name ?? '').trim();
                if (name === '') {
                    this.newGoodError = 'Укажите наименование.';
                    return;
                }
                const qtyRaw = String(this.newGoodForm.quantity ?? '').trim();
                if (qtyRaw === '') {
                    this.newGoodError = 'Укажите количество.';
                    return;
                }
                const qNum = this.parsePurchaseNum(qtyRaw);
                if (!Number.isFinite(qNum) || qNum <= 0) {
                    this.newGoodError = 'Количество должно быть числом больше нуля.';
                    return;
                }
                if (!this.goodsQuickStoreUrl) {
                    this.newGoodError = 'Создание товара недоступно.';
                    return;
                }
                const token =
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                this.newGoodSaving = true;
                this.newGoodError = '';
                const body = { name };
                const addStr = (k, v) => {
                    const t = String(v ?? '').trim();
                    if (t !== '') {
                        body[k] = t;
                    }
                };
                addStr('barcode', this.newGoodForm.barcode);
                addStr('category', this.newGoodForm.category);
                addStr('unit', this.newGoodForm.unit);
                addStr('sale_price', this.newGoodForm.sale_price);
                addStr('wholesale_price', this.newGoodForm.wholesale_price);
                addStr('oem', this.newGoodForm.oem);
                addStr('factory_number', this.newGoodForm.factory_number);
                addStr('min_stock', this.newGoodForm.min_stock);
                if (!body.unit) {
                    body.unit = 'шт.';
                }
                let url = this.goodsQuickStoreUrl;
                if (this.warehouseId > 0) {
                    url += (url.includes('?') ? '&' : '?') + 'warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                }
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify(body),
                    });
                    let data = {};
                    try {
                        data = await res.json();
                    } catch (_) {
                        data = {};
                    }
                    if (!res.ok) {
                        let msg =
                            typeof data.message === 'string'
                                ? data.message
                                : 'Не удалось сохранить товар.';
                        if (data.errors && typeof data.errors === 'object') {
                            const first = Object.values(data.errors).flat()[0];
                            if (typeof first === 'string' && first.trim() !== '') {
                                msg = first;
                            }
                        }
                        this.newGoodError = msg;
                        return;
                    }
                    const item = data;
                    const row = emptyLine();
                    this.fillPrtLineFromCatalogItem(row, item);
                    row.quantity = qtyRaw.replace(/\s/g, '').replace(',', '.');
                    const up = String(this.newGoodForm.unit_price ?? '').trim();
                    if (up !== '') {
                        row.unit_price = up.replace(/\s/g, '').replace(',', '.');
                    }
                    this.applyNewPrtLineOrPush(row);
                    this.closeNewGoodModal();
                    this.prtHeaderGoodsClose(false);
                    this.$nextTick(() => {
                        document.querySelector('[data-prt-header-good-input]')?.focus?.();
                    });
                } catch (_) {
                    this.newGoodError = 'Ошибка сети. Повторите попытку.';
                } finally {
                    this.newGoodSaving = false;
                }
            },
            prtHeaderQuery: '',
            prtHeaderOpen: false,
            prtHeaderLoading: false,
            prtHeaderItems: [],
            prtHeaderNoHits: false,
            prtHeaderTimer: null,
            prtHeaderBlurTimer: null,
            prtHeaderCopyFeedbackGoodId: null,
            prtHeaderCopyFeedbackTimer: null,
            lineSum(row) {
                const q = parseFloat(String(row.quantity ?? '').replace(/\s/g, '').replace(',', '.'));
                const p = parseFloat(String(row.unit_price ?? '').replace(/\s/g, '').replace(',', '.'));
                if (!Number.isFinite(q) || !Number.isFinite(p)) return '';
                return (q * p).toFixed(2);
            },
            parsePurchaseNum(v) {
                const n = parseFloat(String(v ?? '').replace(/\s/g, '').replace(',', '.'));
                return Number.isFinite(n) ? n : NaN;
            },
            /** Одна строка под наименованием в подсказке поиска товара — для чипов в шапке. */
            goodsSuggestCompactMeta(item) {
                const p = this.goodsSuggestMetaParts(item);
                return p.map((x) => x.label + ' ' + x.value).join(', ');
            },
            goodsSuggestMetaParts(item) {
                if (!item) return [];
                const out = [];
                const sq = item.stock_quantity;
                if (sq != null && String(sq).trim() !== '') {
                    const n = parseFloat(String(sq).replace(/\s/g, '').replace(',', '.'));
                    const st = Number.isFinite(n) ? String(n) : String(sq).trim();
                    out.push({ label: 'ост', value: st, danger: goodsStockQtyIsSoldOut(sq) });
                }
                const sp = item.sale_price;
                if (sp != null && String(sp).trim() !== '') {
                    out.push({ label: 'цена', value: String(sp).trim() });
                }
                const wp = item.wholesale_price;
                if (wp != null && String(wp).trim() !== '') {
                    out.push({ label: 'опт', value: String(wp).trim() });
                }
                return out;
            },
            formatGoodsStockQty(v) {
                if (v == null || v === '') return '';
                const n = Number(v);
                if (Number.isNaN(n)) return String(v);
                return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 4 }).format(n);
            },
            formatGoodsUnitCost(v) {
                if (v == null || v === '') return '';
                const n = Number(v);
                if (Number.isNaN(n)) return String(v);
                return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
            },
            goodsSuggestHasReturnHint(item) {
                if (!item || typeof item !== 'object') return false;
                const q = item.stock_quantity;
                const c = item.opening_unit_cost;
                const s = item.sale_price;
                return (q != null && q !== '') || (c != null && c !== '') || (s != null && s !== '');
            },
            goodsStockQtySoldOut(v) {
                return goodsStockQtyIsSoldOut(v);
            },
            prtHeaderGoodsClose(clearQuery = true) {
                clearTimeout(this.prtHeaderTimer);
                clearTimeout(this.prtHeaderBlurTimer);
                clearTimeout(this.prtHeaderCopyFeedbackTimer);
                this.prtHeaderCopyFeedbackTimer = null;
                this.prtHeaderCopyFeedbackGoodId = null;
                if (clearQuery) {
                    this.prtHeaderQuery = '';
                }
                this.prtHeaderItems = [];
                this.prtHeaderLoading = false;
                this.prtHeaderNoHits = false;
                this.prtHeaderOpen = false;
            },
            copyPrtHeaderGoodName(item, event) {
                if (event) {
                    event.stopPropagation();
                    event.preventDefault();
                }
                if (!item || item.name == null) return;
                const t = String(item.name);
                if (!t.trim()) return;
                const afterCopy = () => {
                    clearTimeout(this.prtHeaderCopyFeedbackTimer);
                    if (item.id != null && item.id !== '') {
                        this.prtHeaderCopyFeedbackGoodId = item.id;
                        this.prtHeaderCopyFeedbackTimer = setTimeout(() => {
                            this.prtHeaderCopyFeedbackGoodId = null;
                            this.prtHeaderCopyFeedbackTimer = null;
                        }, 2200);
                    }
                };
                const fallbackExec = () => {
                    try {
                        const ta = document.createElement('textarea');
                        ta.value = t;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'fixed';
                        ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.select();
                        const ok = document.execCommand('copy');
                        document.body.removeChild(ta);

                        return ok;
                    } catch (_) {
                        return false;
                    }
                };
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(t).then(afterCopy).catch(() => {
                        if (fallbackExec()) {
                            afterCopy();
                        }
                    });
                } else if (fallbackExec()) {
                    afterCopy();
                }
            },
            prtLineLooksBlank(row) {
                if (!row) return false;
                const t = (v) => String(v ?? '').trim();
                if (t(row.name)) return false;
                if (t(row.article_code)) return false;
                if (t(row.barcode)) return false;
                if (t(row.category)) return false;
                if (t(row.quantity)) return false;
                if (t(row.unit_price)) return false;
                const uNorm = t(row.unit)
                    .toLowerCase()
                    .replace(/\./g, '')
                    .replace(/\s/g, '');
                if (uNorm !== '' && uNorm !== 'шт') return false;
                return true;
            },
            applyNewPrtLineOrPush(row) {
                if (this.lines.length === 1 && this.prtLineLooksBlank(this.lines[0])) {
                    Object.assign(this.lines[0], row);
                    this.selectedRow = 0;
                    return;
                }
                this.lines.push(row);
                this.selectedRow = this.lines.length - 1;
            },
            fillPrtLineFromCatalogItem(row, item) {
                if (!row || !item) return;
                row.prtCatalogGoodId =
                    item.id != null && item.id !== '' ? String(item.id) : '';
                row.name = item.name || '';
                row.article_code = item.article_code || '';
                row.barcode = item.barcode != null && item.barcode !== '' ? String(item.barcode) : '';
                row.category = item.category != null && item.category !== '' ? String(item.category) : '';
                row.unit = item.unit && String(item.unit).trim() ? String(item.unit).trim() : 'шт.';
                if (item.sale_price != null && item.sale_price !== '') {
                    row.unit_price = String(item.sale_price);
                }
            },
            findPrtLineIndexForCatalogItem(item) {
                if (!item) return -1;
                const gid = item.id != null && item.id !== '' ? String(item.id) : '';
                if (gid !== '') {
                    const ix = this.lines.findIndex((r) => String(r.prtCatalogGoodId || '') === gid);
                    if (ix !== -1) return ix;
                }
                const ac = String(item.article_code ?? '').trim();
                if (ac !== '') {
                    const ix = this.lines.findIndex((r) => String(r.article_code ?? '').trim() === ac);
                    if (ix !== -1) return ix;
                }
                return -1;
            },
            incrementPrtHeaderLineQuantity(row) {
                const raw = String(row.quantity ?? '').trim();
                const q = parseFloat(raw.replace(/\s/g, '').replace(',', '.'));
                const base = Number.isFinite(q) && q >= 0 ? q : 0;
                const next = base + 1;
                if (Number.isInteger(next) || Math.abs(next - Math.round(next)) < 1e-9) {
                    row.quantity = String(Math.round(next));
                } else {
                    row.quantity = String(parseFloat(next.toFixed(6)));
                }
            },
            appendPrtLineFromCatalogItem(item) {
                clearTimeout(this.prtHeaderBlurTimer);
                if (!item) return;
                const existingIx = this.findPrtLineIndexForCatalogItem(item);
                if (existingIx !== -1) {
                    this.incrementPrtHeaderLineQuantity(this.lines[existingIx]);
                    this.selectedRow = existingIx;
                    this.nameSuggestClose();
                    this.counterpartySuggestClose();
                    return;
                }
                const row = emptyLine();
                this.fillPrtLineFromCatalogItem(row, item);
                row.quantity = '1';
                this.applyNewPrtLineOrPush(row);
                this.nameSuggestClose();
                this.counterpartySuggestClose();
            },
            appendPrtLineFromHeaderFreeText() {
                clearTimeout(this.prtHeaderBlurTimer);
                const q = (this.prtHeaderQuery || '').trim();
                if (q.length < 2) return;
                const row = emptyLine();
                row.name = q;
                this.applyNewPrtLineOrPush(row);
            },
            onPrtHeaderGoodsFocus(event) {
                clearTimeout(this.prtHeaderBlurTimer);
                this.nameSuggestClose();
                this.counterpartySuggestClose();
                const el = event.target;
                this.prtHeaderOpen = true;
                const q = (el.value || '').trim();
                if (q.length >= 2) {
                    this.$nextTick(() => this.runPrtHeaderGoodsFetch(el));
                }
            },
            onPrtHeaderGoodsBlur() {
                this.prtHeaderBlurTimer = setTimeout(() => {
                    this.prtHeaderOpen = false;
                }, 180);
            },
            onPrtHeaderGoodsInput(event) {
                const el = event.target;
                const q = (el.value || '').trim();
                clearTimeout(this.prtHeaderTimer);
                this.nameSuggestClose();
                this.counterpartySuggestClose();
                if (q.length < 2) {
                    this.prtHeaderItems = [];
                    this.prtHeaderLoading = false;
                    this.prtHeaderNoHits = false;
                    return;
                }
                this.prtHeaderOpen = true;
                this.prtHeaderLoading = true;
                this.prtHeaderNoHits = false;
                this.prtHeaderItems = [];
                this.prtHeaderTimer = setTimeout(() => this.runPrtHeaderGoodsFetch(el), 280);
            },
            async runPrtHeaderGoodsFetch(el) {
                const q = (this.prtHeaderQuery || '').trim();
                if (q.length < 2) return;
                try {
                    let url = this.goodsSearchUrl + '?q=' + encodeURIComponent(q);
                    if (this.warehouseId > 0) {
                        url += '&warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                    }
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) throw new Error('search');
                    const data = await res.json();
                    this.prtHeaderItems = Array.isArray(data) ? data : [];
                    this.prtHeaderNoHits = this.prtHeaderItems.length === 0;
                } catch (e) {
                    this.prtHeaderItems = [];
                    this.prtHeaderNoHits = false;
                } finally {
                    this.prtHeaderLoading = false;
                }
            },
            onPrtHeaderGoodsEnter(e) {
                e.preventDefault();
                if (this.prtHeaderLoading) {
                    return;
                }
                if (this.prtHeaderItems.length === 1) {
                    this.appendPrtLineFromCatalogItem(this.prtHeaderItems[0]);
                    return;
                }
                const q = (this.prtHeaderQuery || '').trim();
                if (q.length >= 2 && this.prtHeaderItems.length === 0) {
                    this.openNewGoodModal(q);
                }
            },
            refreshSuggestPosition(el) {
                if (!el || !el.getBoundingClientRect) return;
                const r = el.getBoundingClientRect();
                const w = Math.max(r.width, 300);
                let left = r.left;
                if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8);
                this.suggestPos = { top: r.bottom + 4, left, width: w };
            },
            refreshCpSuggestPosition(el) {
                if (!el || !el.getBoundingClientRect) return;
                const r = el.getBoundingClientRect();
                const w = Math.max(r.width, 280);
                let left = r.left;
                if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8);
                this.cpSuggestPos = { top: r.bottom + 4, left, width: w };
            },
            nameSuggestClose() {
                clearTimeout(this.nameSuggestTimer);
                this.nameSuggestItems = [];
                this.nameSuggestRow = null;
                this.nameSuggestLoading = false;
                this.nameSuggestNoHits = false;
            },
            counterpartySuggestClose() {
                clearTimeout(this.cpSuggestTimer);
                this.cpSuggestItems = [];
                this.cpSuggestLoading = false;
                this.cpSuggestNoHits = false;
                this.cpQuickOpen = false;
                this.cpQuickError = '';
            },
            closeAllSuggests() {
                if (this.newGoodModalOpen) {
                    this.closeNewGoodModal();
                    return;
                }
                this.nameSuggestClose();
                this.counterpartySuggestClose();
                this.prtHeaderGoodsClose();
            },
            purchaseReturnEscape() {
                if (this.newGoodModalOpen) {
                    this.closeNewGoodModal();
                    return;
                }
                if (this.finalizeModalOpen) {
                    this.closeFinalizeModal();
                    return;
                }
                this.closeAllSuggests();
            },
            openFinalizeModal() {
                this.closeAllSuggests();
                this.finalizeModalOpen = true;
                this.$nextTick(() => {
                    document.getElementById('prt_finalize_supplier')?.focus();
                });
            },
            openFinalizeIfNeeded() {
                if (!this.openFinalizeOnLoad) {
                    return;
                }
                this.$nextTick(() => {
                    this.finalizeModalOpen = true;
                    this.$nextTick(() => {
                        document.getElementById('prt_finalize_supplier')?.focus();
                    });
                });
            },
            closeFinalizeModal() {
                this.finalizeModalOpen = false;
                this.counterpartySuggestClose();
            },
            submitPurchaseReturnFromModal() {
                const form = document.getElementById('prt-purchase-return-form');
                if (!form || !form.reportValidity()) return;
                form.requestSubmit();
            },
            onFinalizeWarehouseChange(event) {
                const v = parseInt(String(event?.target?.value ?? ''), 10);
                if (!Number.isFinite(v) || v <= 0 || v === this.warehouseId) {
                    return;
                }
                if (!purchaseReturnSwitchWarehouseUrl) {
                    return;
                }
                const u = new URL(purchaseReturnSwitchWarehouseUrl, window.location.href);
                u.searchParams.set('warehouse_id', String(v));
                window.location.assign(u.href);
            },
            repositionOpenSuggests() {
                const ni = this.nameSuggestRow;
                if (
                    ni !== null &&
                    (this.nameSuggestLoading || this.nameSuggestItems.length > 0 || this.nameSuggestNoHits)
                ) {
                    const inp = document.querySelector(`input[name="lines[${ni}][name]"]`);
                    if (inp) this.refreshSuggestPosition(inp);
                }
                const supEl = document.getElementById('prt_finalize_supplier');
                if (
                    supEl &&
                    (this.cpQuickOpen ||
                        ((this.supplierName || '').trim().length >= 2 &&
                            (this.cpSuggestLoading || this.cpSuggestItems.length > 0 || this.cpSuggestNoHits)))
                ) {
                    this.refreshCpSuggestPosition(supEl);
                }
            },
            showCpDropdown() {
                if (this.cpQuickOpen) {
                    return true;
                }
                const q = (this.supplierName || '').trim();
                if (q.length < 2) {
                    return false;
                }
                return this.cpSuggestLoading || this.cpSuggestItems.length > 0 || this.cpSuggestNoHits;
            },
            onNameFocus(index, event) {
                clearTimeout(this.nameSuggestBlurTimer);
                this.prtHeaderGoodsClose();
                this.counterpartySuggestClose();
                this.selectedRow = index;
                this.refreshSuggestPosition(event.target);
            },
            onNameBlur() {
                this.nameSuggestBlurTimer = setTimeout(() => this.nameSuggestClose(), 180);
            },
            onNameInput(index, event) {
                const el = event.target;
                this.refreshSuggestPosition(el);
                const q = (el.value || '').trim();
                clearTimeout(this.nameSuggestTimer);
                if (q.length < 2) {
                    this.nameSuggestClose();
                    return;
                }
                this.prtHeaderGoodsClose();
                this.counterpartySuggestClose();
                this.nameSuggestRow = index;
                this.nameSuggestLoading = true;
                this.nameSuggestNoHits = false;
                this.nameSuggestItems = [];
                this.nameSuggestTimer = setTimeout(async () => {
                    try {
                        let url = this.goodsSearchUrl + '?q=' + encodeURIComponent(q);
                        if (this.warehouseId > 0) {
                            url += '&warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                        }
                        const res = await fetch(url, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) throw new Error('search');
                        const data = await res.json();
                        if (this.nameSuggestRow !== index) return;
                        this.nameSuggestItems = Array.isArray(data) ? data : [];
                        this.nameSuggestNoHits = this.nameSuggestItems.length === 0;
                    } catch (e) {
                        this.nameSuggestItems = [];
                        this.nameSuggestNoHits = false;
                    } finally {
                        this.nameSuggestLoading = false;
                        this.$nextTick(() => this.refreshSuggestPosition(el));
                    }
                }, 280);
            },
            pickGoodFromSuggest(item) {
                clearTimeout(this.nameSuggestBlurTimer);
                const index = this.nameSuggestRow;
                if (index === null || !item) return;
                const row = this.lines[index];
                if (!row) return;
                row.prtCatalogGoodId =
                    item.id != null && item.id !== '' ? String(item.id) : '';
                row.name = item.name || '';
                row.article_code = item.article_code || '';
                row.barcode = item.barcode != null && item.barcode !== '' ? String(item.barcode) : '';
                row.category = item.category != null && item.category !== '' ? String(item.category) : '';
                row.unit = item.unit && String(item.unit).trim() ? String(item.unit).trim() : 'шт.';
                if (item.sale_price != null && item.sale_price !== '') {
                    row.unit_price = String(item.sale_price);
                }
                this.nameSuggestClose();
            },
            onSupplierFocus(event) {
                clearTimeout(this.cpSuggestBlurTimer);
                this.nameSuggestClose();
                this.prtHeaderGoodsClose();
                this.refreshCpSuggestPosition(event.target);
            },
            onSupplierBlur() {
                this.cpSuggestBlurTimer = setTimeout(() => {
                    const root = this.$refs.supplierRoot;
                    if (root && typeof root.contains === 'function' && root.contains(document.activeElement)) {
                        return;
                    }
                    this.counterpartySuggestClose();
                }, 250);
            },
            onSupplierInput(event) {
                const el = event.target;
                this.supplierName = el.value || '';
                this.refreshCpSuggestPosition(el);
                this.cpQuickOpen = false;
                this.cpQuickError = '';
                const q = (el.value || '').trim();
                clearTimeout(this.cpSuggestTimer);
                if (q.length < 2) {
                    this.counterpartySuggestClose();
                    return;
                }
                this.nameSuggestClose();
                this.prtHeaderGoodsClose();
                this.cpSuggestLoading = true;
                this.cpSuggestNoHits = false;
                this.cpSuggestItems = [];
                this.cpSuggestTimer = setTimeout(async () => {
                    try {
                        if (!this.counterpartySearchUrl) {
                            this.cpSuggestItems = [];
                            return;
                        }
                        const sep = this.counterpartySearchUrl.includes('?') ? '&' : '?';
                        const url = this.counterpartySearchUrl + sep + 'q=' + encodeURIComponent(q);
                        const res = await fetch(url, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) throw new Error('search');
                        const data = await res.json();
                        this.cpSuggestItems = Array.isArray(data) ? data : [];
                        this.cpSuggestNoHits = this.cpSuggestItems.length === 0;
                    } catch (e) {
                        this.cpSuggestItems = [];
                        this.cpSuggestNoHits = false;
                    } finally {
                        this.cpSuggestLoading = false;
                        this.$nextTick(() => this.refreshCpSuggestPosition(el));
                    }
                }, 280);
            },
            pickCounterparty(item) {
                clearTimeout(this.cpSuggestBlurTimer);
                if (!item) return;
                const label = (item.full_name && String(item.full_name).trim()) || (item.name && String(item.name).trim()) || '';
                this.supplierName = label;
                this.counterpartySuggestClose();
            },
            openCpQuickAdd(event) {
                if (event) event.preventDefault();
                clearTimeout(this.cpSuggestBlurTimer);
                const el = document.getElementById('prt_finalize_supplier');
                const q = (el && el.value ? el.value : this.supplierName || '').trim();
                if (q.length >= 2) {
                    this.supplierName = q;
                }
                this.cpQuickLegalForm = 'osoo';
                this.cpQuickError = '';
                this.cpQuickOpen = true;
                this.cpSuggestItems = [];
                this.$nextTick(() => {
                    if (el && el.getBoundingClientRect) this.refreshCpSuggestPosition(el);
                });
            },
            async submitCpQuickAdd() {
                const name = (this.supplierName || '').trim();
                if (name.length < 1) {
                    this.cpQuickError = 'Введите наименование.';
                    return;
                }
                if (!this.counterpartyQuickUrl) {
                    this.cpQuickError = 'Сохранение недоступно.';
                    return;
                }
                const token =
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                this.cpQuickSaving = true;
                this.cpQuickError = '';
                try {
                    const res = await fetch(this.counterpartyQuickUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({
                            name,
                            legal_form: this.cpQuickLegalForm,
                            kind: 'supplier',
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        let msg = 'Не удалось сохранить.';
                        if (data && typeof data === 'object') {
                            if (data.message && typeof data.message === 'string') {
                                msg = data.message;
                            } else if (data.errors && typeof data.errors === 'object') {
                                const first = Object.values(data.errors).flat().find((e) => typeof e === 'string');
                                if (first) {
                                    msg = first;
                                }
                            }
                        }
                        this.cpQuickError = msg;
                        return;
                    }
                    const label =
                        (data.full_name && String(data.full_name).trim()) ||
                        (data.name && String(data.name).trim()) ||
                        name;
                    this.supplierName = label;
                    this.cpQuickOpen = false;
                    this.counterpartySuggestClose();
                } catch (e) {
                    this.cpQuickError = 'Ошибка сети.';
                } finally {
                    this.cpQuickSaving = false;
                }
            },
            addRow() {
                this.closeAllSuggests();
                this.lines.push(emptyLine());
                this.selectedRow = this.lines.length - 1;
            },
            removeSelectedRow() {
                if (this.lines.length <= 1) return;
                this.lines.splice(this.selectedRow, 1);
                this.selectedRow = Math.min(this.selectedRow, this.lines.length - 1);
                this.moreOpen = false;
                this.closeAllSuggests();
            },
            removeLineAt(index) {
                const i = typeof index === 'number' ? index : parseInt(String(index), 10);
                if (!Number.isFinite(i) || i < 0 || i >= this.lines.length) {
                    return;
                }
                if (this.lines.length <= 1) {
                    return;
                }
                this.lines.splice(i, 1);
                if (this.selectedRow === i) {
                    this.selectedRow = Math.min(i, this.lines.length - 1);
                } else if (this.selectedRow > i) {
                    this.selectedRow--;
                }
                this.moreOpen = false;
                this.closeAllSuggests();
            },
            moveUp() {
                const i = this.selectedRow;
                if (i <= 0) return;
                const next = this.lines.slice();
                [next[i - 1], next[i]] = [next[i], next[i - 1]];
                this.lines = next;
                this.selectedRow = i - 1;
                this.closeAllSuggests();
            },
            moveDown() {
                const i = this.selectedRow;
                if (i >= this.lines.length - 1) return;
                const next = this.lines.slice();
                [next[i], next[i + 1]] = [next[i + 1], next[i]];
                this.lines = next;
                this.selectedRow = i + 1;
                this.closeAllSuggests();
            },
            openDraftPrint() {
                const dateEl =
                    document.querySelector('#prt_finalize_document_date, #pr_finalize_document_date, #document_date');
                const rawDate = dateEl && dateEl.value ? dateEl.value : '';
                const months = [
                    'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                    'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря',
                ];
                let titleDate = '';
                if (/^\d{4}-\d{2}-\d{2}$/.test(rawDate)) {
                    const [y, m, d] = rawDate.split('-').map((x) => parseInt(x, 10));
                    titleDate = `${d} ${months[m - 1] || ''} ${y} г.`;
                } else {
                    titleDate = rawDate || '—';
                }
                const rows = this.lines.filter((r) => (r.article_code || '').trim() !== '');
                if (rows.length === 0) {
                    window.alert('Добавьте хотя бы одну строку с артикулом.');
                    return;
                }
                const esc = (s) =>
                    String(s ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                const fmtMoney = (n) => {
                    if (!Number.isFinite(n)) return '—';
                    return n
                        .toFixed(2)
                        .replace('.', ',')
                        .replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
                };
                const fmtQty = (qRaw, unit) => {
                    const u = (unit || 'шт.').trim() || 'шт.';
                    const n = parseFloat(String(qRaw ?? '').replace(/\s/g, '').replace(',', '.'));
                    if (!Number.isFinite(n)) return `— ${u}`;
                    if (Math.abs(n - Math.round(n)) < 1e-5) {
                        return `${Math.round(n).toLocaleString('en-US')} ${u}`;
                    }
                    return `${String(qRaw ?? '').replace(/\s/g, '').replace(',', '.')} ${u}`;
                };
                let body = '';
                let totalSum = 0;
                rows.forEach((row, i) => {
                    const q = parseFloat(String(row.quantity ?? '').replace(/\s/g, '').replace(',', '.'));
                    const p = parseFloat(String(row.unit_price ?? '').replace(/\s/g, '').replace(',', '.'));
                    let sumVal = null;
                    if (Number.isFinite(q) && Number.isFinite(p)) {
                        sumVal = q * p;
                        totalSum += sumVal;
                    }
                    const sumStr = sumVal !== null ? fmtMoney(sumVal) : '—';
                    const priceStr = Number.isFinite(p) ? fmtMoney(p) : '—';
                    body += `<tr>
                    <td style="border:1px solid #000;padding:5px 6px;text-align:center">${i + 1}</td>
                    <td style="border:1px solid #000;padding:5px 6px">${esc(row.name)}</td>
                    <td style="border:1px solid #000;padding:5px 6px">${esc(fmtQty(row.quantity, row.unit))}</td>
                    <td style="border:1px solid #000;padding:5px 6px;text-align:right">${priceStr}</td>
                    <td style="border:1px solid #000;padding:5px 6px;text-align:right">${sumStr}</td>
                </tr>`;
                });
                const orgHeader = esc(this.branchName || '—');
                const wh = esc(this.warehouseName || '');
                const supplier = esc(this.supplierName || '');
                const totalStr = fmtMoney(totalSum);
                const html = `<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Черновик возврата поставщику</title>
                <style>
                    body{font-family:Tahoma,Arial,sans-serif;font-size:10pt;color:#000;padding:16px;line-height:1.4;}
                    .doc-title{font-size:12pt;font-weight:bold;text-align:center;margin:0 0 8px 0;padding-bottom:6px;border-bottom:1px solid #000;}
                    .meta-block{margin:18px 0 22px 0;}
                    .meta-row{margin-bottom:6px;}
                    .lbl{display:inline-block;min-width:8.5em;}
                    table.grid{width:100%;border-collapse:collapse;font-size:9pt;margin:0 0 12px 0;}
                    table.grid th,table.grid td{border:1px solid #000;padding:5px 6px;vertical-align:middle;}
                    table.grid th{font-weight:bold;text-align:left;background:#fff;}
                    table.grid th.c,table.grid td.c{text-align:center;}
                    table.grid th.num,table.grid td.num{text-align:right;}
                    .totals-wrap{display:table;width:100%;margin-top:4px;}
                    .totals-left{display:table-cell;width:55%;}
                    .totals-right{display:table-cell;width:45%;text-align:right;font-size:10pt;vertical-align:top;}
                    .totals-right .row{margin-bottom:4px;}
                    .footer-line{margin-top:20px;font-size:10pt;}
                    .amount-words{margin-top:10px;font-weight:bold;font-size:10pt;}
                    .signatures{margin-top:36px;width:100%;display:table;font-size:10pt;}
                    .sign-left,.sign-right{display:table-cell;width:50%;}
                    .sign-right{text-align:right;}
                    .no-print{margin:16px 0;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;}
                    @media print{.no-print{display:none!important;}body{padding:0;}}
                </style></head><body>
                <div class="no-print">
                    <button type="button" onclick="window.print()" style="padding:8px 16px;margin-right:8px;cursor:pointer;border-radius:6px;border:1px solid #059669;background:#059669;color:#fff;font-size:11pt;">Печать</button>
                    <span style="font-size:10pt;color:#64748b;">PDF: в окне печати выберите «Сохранить как PDF».</span>
                </div>
                <h1 class="doc-title" style="border-top:3px solid #ffcc00;border-bottom:1px solid #000;padding:10px 8px;text-align:center;font-size:12pt;font-weight:bold;color:#1a1a1a;">Накладная на возврат поставщику (черновик) от ${esc(titleDate)}</h1>
                <div class="meta-block">
                    <div class="meta-row"><span class="lbl">Организация:</span> ${orgHeader}</div>
                    <div class="meta-row"><span class="lbl">Склад:</span> ${wh || '—'}</div>
                    <div class="meta-row"><span class="lbl">Поставщик:</span> ${supplier || '—'}</div>
                </div>
                <table class="grid">
                    <thead><tr>
                        <th style="width:2.2rem;" class="c">№</th>
                        <th>Товар</th>
                        <th style="width:8rem;">Количество</th>
                        <th style="width:5rem;" class="num">Цена возврата</th>
                        <th style="width:5.5rem;" class="num">Сумма</th>
                    </tr></thead>
                    <tbody>${body}</tbody>
                </table>
                <div class="totals-wrap">
                    <div class="totals-left"></div>
                    <div class="totals-right">
                        <div class="row"><span>Итого:</span> <strong>${totalStr}</strong></div>
                        <div class="row">в том числе НДС: ________________</div>
                        <div class="row">в том числе НСП: ________________</div>
                    </div>
                </div>
                <div class="footer-line">Всего наименований ${rows.length}, на сумму ${totalStr}</div>
                <div class="amount-words">Сумма прописью уточняется после сохранения документа.</div>
                <div class="signatures">
                    <div class="sign-left">Сдал _______________________</div>
                    <div class="sign-right">Принял (поставщик) _______________________</div>
                </div>
                <p style="margin-top:16px;font-size:9pt;color:#64748b;">Черновик по полям формы.</p>
                </body></html>`;
                const w = window.open('', '_blank');
                if (!w) {
                    window.alert('Разрешите всплывающие окна для печати.');
                    return;
                }
                w.document.open();
                w.document.write(html);
                w.document.close();
            },
        };
    });

    Alpine.data('serviceOrderHeaderForm', () => {
        const c =
            typeof window !== 'undefined' &&
            window.__serviceOrderHeaderInit &&
            typeof window.__serviceOrderHeaderInit === 'object'
                ? window.__serviceOrderHeaderInit
                : {};
        const counterpartySearchUrl = typeof c.counterpartySearchUrl === 'string' ? c.counterpartySearchUrl : '';
        const counterpartyQuickUrl = typeof c.counterpartyQuickUrl === 'string' ? c.counterpartyQuickUrl : '';
        const vehiclesIndexUrl = typeof c.customerVehiclesIndexUrl === 'string' ? c.customerVehiclesIndexUrl : '';
        const vehiclesStoreUrl = typeof c.customerVehiclesStoreUrl === 'string' ? c.customerVehiclesStoreUrl : '';
        const vehicleHistoryUrlBase =
            typeof c.vehicleHistoryUrlBase === 'string' ? c.vehicleHistoryUrlBase.trim().replace(/\/+$/, '') : '';
        const csrf = typeof c.csrf === 'string' ? c.csrf : '';
        const masters = Array.isArray(c.masters) ? c.masters : [];
        const initialCp =
            c.initialCounterparty && typeof c.initialCounterparty === 'object' ? c.initialCounterparty : null;
        const rawVid = c.initialVehicleId;
        const initialVehicleId =
            rawVid != null && rawVid !== '' && !Number.isNaN(parseInt(String(rawVid), 10))
                ? parseInt(String(rawVid), 10)
                : null;
        const warehouseId =
            typeof c.warehouseId === 'number' && !Number.isNaN(c.warehouseId)
                ? c.warehouseId
                : parseInt(String(c.warehouseId ?? '0'), 10) || 0;

        return {
            counterpartySearchUrl,
            counterpartyQuickUrl,
            vehiclesIndexUrl,
            vehiclesStoreUrl,
            vehicleHistoryUrlBase,
            csrf,
            masters,
            warehouseId,
            counterpartyId: null,
            counterpartyLabel: '',
            cpQuery: '',
            cpItems: [],
            cpOpen: false,
            cpLoading: false,
            vehicles: [],
            vehicleLoading: false,
            customerVehicleId: '',
            clientModalOpen: false,
            quickName: '',
            quickLegalForm: 'individual',
            quickPhone: '',
            quickSaving: false,
            quickError: '',
            vehicleModalOpen: false,
            vBrand: '',
            vVin: '',
            vYear: '',
            vEngine: '',
            vPlate: '',
            vehicleSaving: false,
            vehicleError: '',
            vehicleHistoryOpen: false,
            vehicleHistoryLoading: false,
            vehicleHistoryError: '',
            vehicleHistoryPayload: null,
            init() {
                if (initialCp && initialCp.id) {
                    this.counterpartyId = initialCp.id;
                    this.counterpartyLabel = initialCp.label || '';
                    this.cpQuery = this.counterpartyLabel;
                    this.loadVehicles().then(() => {
                        if (initialVehicleId != null) {
                            this.customerVehicleId = String(initialVehicleId);
                        }
                    });
                }
                this.$watch('cpQuery', (val) => {
                    if (
                        this.counterpartyId != null &&
                        String(val ?? '').trim() !== String(this.counterpartyLabel ?? '').trim()
                    ) {
                        this.counterpartyId = null;
                        this.vehicles = [];
                        this.customerVehicleId = '';
                    }
                });
            },
            async loadVehicles() {
                if (!this.counterpartyId) {
                    this.vehicles = [];
                    return;
                }
                this.vehicleLoading = true;
                try {
                    const url =
                        this.vehiclesIndexUrl + '?counterparty_id=' + encodeURIComponent(String(this.counterpartyId));
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    this.vehicles = Array.isArray(data) ? data : [];
                } catch (e) {
                    this.vehicles = [];
                } finally {
                    this.vehicleLoading = false;
                }
            },
            async searchCp() {
                if (!this.counterpartySearchUrl) {
                    return;
                }
                const q = this.cpQuery.trim();
                if (q.length < 2) {
                    this.cpItems = [];
                    return;
                }
                this.cpLoading = true;
                try {
                    const res = await fetch(
                        this.counterpartySearchUrl + '?q=' + encodeURIComponent(q) + '&for=sale',
                        { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
                    );
                    const data = await res.json();
                    this.cpItems = Array.isArray(data) ? data : [];
                    this.cpOpen = true;
                } catch (e) {
                    this.cpItems = [];
                } finally {
                    this.cpLoading = false;
                }
            },
            pickCp(item) {
                if (!item) return;
                this.counterpartyId = item.id;
                this.counterpartyLabel = item.full_name || item.name || '';
                this.cpQuery = this.counterpartyLabel;
                this.cpItems = [];
                this.cpOpen = false;
                this.customerVehicleId = '';
                this.loadVehicles().then(() => {
                    if (this.vehicles.length === 1) {
                        this.customerVehicleId = String(this.vehicles[0].id);
                    }
                });
            },
            clearCp() {
                this.counterpartyId = null;
                this.counterpartyLabel = '';
                this.cpQuery = '';
                this.cpItems = [];
                this.cpOpen = false;
                this.vehicles = [];
                this.customerVehicleId = '';
            },
            async quickSaveClient() {
                this.quickError = '';
                const name = this.quickName.trim();
                const phone = this.quickPhone.trim();
                if (!name) {
                    this.quickError = 'Введите наименование.';
                    return;
                }
                if (!phone) {
                    this.quickError = 'Введите номер телефона.';
                    return;
                }
                if (!this.counterpartyQuickUrl) {
                    this.quickError = 'Внутренняя ошибка.';
                    return;
                }
                this.quickSaving = true;
                try {
                    const res = await fetch(this.counterpartyQuickUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            name,
                            legal_form: this.quickLegalForm,
                            phone,
                            kind: 'buyer',
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        let msg = data.message || '';
                        if (!msg && data.errors && typeof data.errors === 'object') {
                            msg = Object.values(data.errors)
                                .flat()
                                .join(' ');
                        }
                        this.quickError = msg || 'Не удалось сохранить.';
                        return;
                    }
                    this.pickCp({
                        id: data.id,
                        full_name: data.full_name,
                        name: data.name,
                    });
                    this.clientModalOpen = false;
                    this.quickName = '';
                    this.quickPhone = '';
                } catch (e) {
                    this.quickError = 'Ошибка сети.';
                } finally {
                    this.quickSaving = false;
                }
            },
            openVehicleModal() {
                if (!this.counterpartyId) {
                    return;
                }
                this.vehicleError = '';
                this.vBrand = '';
                this.vVin = '';
                this.vYear = '';
                this.vEngine = '';
                this.vPlate = '';
                this.vehicleModalOpen = true;
            },
            async saveVehicle() {
                this.vehicleError = '';
                if (!this.counterpartyId) {
                    this.vehicleError = 'Сначала выберите клиента.';
                    return;
                }
                this.vehicleSaving = true;
                try {
                    const vehicle_brand = this.vBrand.trim();
                    const vin = this.vVin.trim();
                    const body = {
                        counterparty_id: this.counterpartyId,
                        vehicle_brand,
                        vin,
                    };
                    const y = parseInt(String(this.vYear || ''), 10);
                    if (Number.isFinite(y) && y > 0) {
                        body.vehicle_year = y;
                    }
                    const ev = this.vEngine.trim();
                    if (ev) {
                        body.engine_volume = ev;
                    }
                    const pl = this.vPlate.trim();
                    if (pl) {
                        body.plate_number = pl;
                    }
                    const res = await fetch(this.vehiclesStoreUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(body),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        let msg = data.message || '';
                        if (!msg && data.errors && typeof data.errors === 'object') {
                            msg = Object.values(data.errors)
                                .flat()
                                .join(' ');
                        }
                        this.vehicleError = msg || 'Не удалось сохранить.';
                        return;
                    }
                    if (data.id) {
                        await this.loadVehicles();
                        this.customerVehicleId = String(data.id);
                    }
                    this.vehicleModalOpen = false;
                } catch (e) {
                    this.vehicleError = 'Ошибка сети.';
                } finally {
                    this.vehicleSaving = false;
                }
            },
            vehicleLabel(v) {
                if (!v) return '';
                const parts = [
                    v.vehicle_brand,
                    v.plate_number ? '№ ' + v.plate_number : null,
                    v.vin ? 'VIN ' + v.vin : null,
                ].filter(Boolean);
                return parts.length ? parts.join(' · ') : 'Авто #' + v.id;
            },
            formatVisitDate(iso) {
                if (!iso || typeof iso !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
                    return iso || '—';
                }
                const [y, m, d] = iso.split('-');
                return `${d}.${m}.${y}`;
            },
            mileageDisplay(km) {
                if (km == null || km === '') {
                    return '—';
                }
                const n = parseFloat(String(km).replace(/\s/g, '').replace(',', '.'));
                if (Number.isFinite(n)) {
                    return (
                        n.toLocaleString('ru-RU', { maximumFractionDigits: 1, minimumFractionDigits: 0 }) + ' км'
                    );
                }
                return String(km) + ' км';
            },
            visitStatusLabel(s) {
                if (s === 'fulfilled') {
                    return 'Оформлена';
                }
                if (s === 'awaiting_fulfillment') {
                    return 'В работе';
                }
                if (s === 'cancelled') {
                    return 'Отменена';
                }
                return s ? String(s) : '—';
            },
            async openVehicleHistory() {
                const id = String(this.customerVehicleId ?? '').trim();
                if (!id || !this.vehicleHistoryUrlBase) {
                    return;
                }
                this.vehicleHistoryOpen = true;
                this.vehicleHistoryLoading = true;
                this.vehicleHistoryError = '';
                this.vehicleHistoryPayload = null;
                const url = `${this.vehicleHistoryUrlBase}/${encodeURIComponent(id)}`;
                try {
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const msg =
                            (data && data.message) ||
                            (data && data.error) ||
                            (typeof data === 'string' ? data : '') ||
                            'Не удалось загрузить историю.';
                        this.vehicleHistoryError = msg;
                        return;
                    }
                    this.vehicleHistoryPayload = data && typeof data === 'object' ? data : null;
                } catch (e) {
                    this.vehicleHistoryError = 'Ошибка сети.';
                } finally {
                    this.vehicleHistoryLoading = false;
                }
            },
            closeVehicleHistory() {
                this.vehicleHistoryOpen = false;
                this.vehicleHistoryError = '';
                this.vehicleHistoryPayload = null;
            },
        };
    });

    Alpine.data('vehicleHistoryIndexPage', (opts) => {
        const o = opts && typeof opts === 'object' ? opts : {};
        /** @param {string} raw абсолютный или относительный URL с сервера — привести к URL для fetch с текущим origin */
        function sameOriginRequestUrl(raw) {
            const t = typeof raw === 'string' ? raw.trim() : '';
            if (!t) {
                return '';
            }
            try {
                return new URL(t, window.location.href).href;
            } catch {
                return t;
            }
        }
        const jsonBaseRaw =
            typeof o.jsonBase === 'string' ? o.jsonBase.trim().replace(/\/+$/, '') : '';
        const searchUrlRaw = typeof o.searchUrl === 'string' ? o.searchUrl.trim() : '';
        const jsonBase = jsonBaseRaw ? sameOriginRequestUrl(jsonBaseRaw) : '';
        const searchUrl = searchUrlRaw ? sameOriginRequestUrl(searchUrlRaw) : '';

        return {
            jsonBase,
            searchUrl,
            searchQuery: '',
            suggestOpen: false,
            suggestLoading: false,
            suggestItems: [],
            suggestNoHits: false,
            suggestTimer: null,
            suggestBlurTimer: null,
            modalOpen: false,
            loading: false,
            error: '',
            payload: null,
            scheduleSuggest() {
                clearTimeout(this.suggestTimer);
                const q = String(this.searchQuery ?? '').trim();
                if (q.length < 2) {
                    this.suggestItems = [];
                    this.suggestOpen = false;
                    this.suggestNoHits = false;
                    this.suggestLoading = false;
                    return;
                }
                this.suggestTimer = setTimeout(() => {
                    this.fetchSuggest();
                }, 300);
            },
            async fetchSuggest() {
                const q = String(this.searchQuery ?? '').trim();
                if (q.length < 2 || !this.searchUrl) {
                    return;
                }
                this.suggestLoading = true;
                this.suggestNoHits = false;
                this.suggestOpen = true;
                try {
                    const join = this.searchUrl.indexOf('?') >= 0 ? '&' : '?';
                    const url = `${this.searchUrl}${join}q=${encodeURIComponent(q)}`;
                    const res = await fetch(url, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json().catch(() => ({}));
                    const list = data && Array.isArray(data.vehicles) ? data.vehicles : [];
                    this.suggestItems = list;
                    this.suggestNoHits = list.length === 0;
                } catch (e) {
                    this.suggestItems = [];
                    this.suggestNoHits = true;
                } finally {
                    this.suggestLoading = false;
                }
            },
            onSearchFocus() {
                const q = String(this.searchQuery ?? '').trim();
                if (q.length >= 2) {
                    this.suggestOpen = true;
                    this.fetchSuggest();
                }
            },
            onSearchBlur() {
                clearTimeout(this.suggestBlurTimer);
                this.suggestBlurTimer = setTimeout(() => {
                    this.suggestOpen = false;
                }, 200);
            },
            pickVehicle(item) {
                if (!item || item.id == null) {
                    return;
                }
                this.searchQuery = item.label ? String(item.label) : '';
                this.suggestOpen = false;
                this.suggestItems = [];
                this.openHistory(item.id);
            },
            formatVisitDate(iso) {
                if (!iso || typeof iso !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
                    return iso || '—';
                }
                const [y, m, d] = iso.split('-');
                return `${d}.${m}.${y}`;
            },
            mileageDisplay(km) {
                if (km == null || km === '') {
                    return '—';
                }
                const n = parseFloat(String(km).replace(/\s/g, '').replace(',', '.'));
                if (Number.isFinite(n)) {
                    return (
                        n.toLocaleString('ru-RU', { maximumFractionDigits: 1, minimumFractionDigits: 0 }) + ' км'
                    );
                }
                return String(km) + ' км';
            },
            visitStatusLabel(s) {
                if (s === 'fulfilled') {
                    return 'Оформлена';
                }
                if (s === 'awaiting_fulfillment') {
                    return 'В работе';
                }
                if (s === 'cancelled') {
                    return 'Отменена';
                }
                return s ? String(s) : '—';
            },
            async openHistory(id) {
                const vid = String(id ?? '').trim();
                if (!vid || !this.jsonBase) {
                    return;
                }
                this.modalOpen = true;
                this.loading = true;
                this.error = '';
                this.payload = null;
                const url = `${this.jsonBase}/${encodeURIComponent(vid)}/json`;
                try {
                    const res = await fetch(url, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const msg =
                            (data && data.message) ||
                            (data && data.error) ||
                            'Не удалось загрузить историю.';
                        this.error = msg;
                        return;
                    }
                    this.payload = data && typeof data === 'object' ? data : null;
                } catch (e) {
                    this.error = 'Ошибка сети.';
                } finally {
                    this.loading = false;
                }
            },
            closeModal() {
                this.modalOpen = false;
                this.error = '';
                this.payload = null;
            },
        };
    });

    Alpine.data('retailPosForm', () => {
        const c =
            typeof window !== 'undefined' && window.__retailPosInit && typeof window.__retailPosInit === 'object'
                ? window.__retailPosInit
                : {};
        const goodsSearchUrl = typeof c.goodsSearchUrl === 'string' ? c.goodsSearchUrl : '';
        const rawWarehouseId =
            typeof c.warehouseId === 'number' && !Number.isNaN(c.warehouseId)
                ? c.warehouseId
                : parseInt(String(c.warehouseId ?? '0'), 10) || 0;
        const defaultWarehouseId =
            typeof c.defaultWarehouseId === 'number' && !Number.isNaN(c.defaultWarehouseId)
                ? c.defaultWarehouseId
                : parseInt(String(c.defaultWarehouseId ?? '0'), 10) || 0;
        const warehouseId =
            rawWarehouseId > 0 ? rawWarehouseId : defaultWarehouseId > 0 ? defaultWarehouseId : 0;
        const editMode = c.editMode === true;
        const serviceRequestMode = c.serviceRequestMode === true;
        const initialCartRaw = Array.isArray(c.initialCart) ? c.initialCart : [];
        const counterpartySearchUrl =
            typeof c.counterpartySearchUrl === 'string' ? c.counterpartySearchUrl : '';
        const masters = Array.isArray(c.masters) ? c.masters : [];
        const initialCp =
            c.initialCounterparty && typeof c.initialCounterparty === 'object' ? c.initialCounterparty : null;
        const defaultDocumentDate =
            typeof c.defaultDocumentDate === 'string'
                ? c.defaultDocumentDate
                : new Date().toISOString().slice(0, 10);

        return {
            query: '',
            searchOpen: false,
            loading: false,
            results: [],
            cart: [],
            clientPaid: '',
            clientQueue: [],
            defaultDocumentDate,
            goodsSearchUrl,
            warehouseId,
            editMode,
            serviceRequestMode,
            counterpartySearchUrl,
            masters,
            counterpartyId: null,
            counterpartyLabel: '',
            cpQuery: '',
            cpItems: [],
            cpOpen: false,
            cpLoading: false,
            init() {
                if (initialCp && initialCp.id) {
                    this.counterpartyId = initialCp.id;
                    this.counterpartyLabel = initialCp.label || '';
                    this.cpQuery = this.counterpartyLabel;
                }
                if (initialCartRaw.length > 0) {
                    this.cart = initialCartRaw.map((row) => ({
                        line_id: row.line_id != null && row.line_id !== '' ? row.line_id : null,
                        good_id: row.good_id,
                        article_code: row.article_code || '',
                        name: row.name || '',
                        quantity: String(row.quantity ?? '1'),
                        unit_price:
                            row.unit_price != null && row.unit_price !== '' ? String(row.unit_price) : '',
                        stock_quantity: row.stock_quantity != null ? row.stock_quantity : null,
                        is_service: row.is_service === true || row.is_service === 1,
                        performer_employee_id:
                            row.performer_employee_id != null && row.performer_employee_id !== ''
                                ? String(row.performer_employee_id)
                                : '',
                    }));
                }
                if (this.editMode) {
                    this.clientPaid = '';
                }
                this.$watch(
                    'cart',
                    () => {
                        if (!this.editMode && !this.serviceRequestMode) {
                            this.syncClientPaidFromCart();
                        }
                    },
                    { deep: true },
                );
                if (this.serviceRequestMode) {
                    this.$watch('cpQuery', (val) => {
                        if (
                            this.counterpartyId != null &&
                            String(val ?? '').trim() !== String(this.counterpartyLabel ?? '').trim()
                        ) {
                            this.counterpartyId = null;
                        }
                    });
                }
                this.loadQueueFromStorage();
            },
            loadQueueFromStorage() {
                try {
                    const raw = localStorage.getItem('retailPosQueue');
                    if (!raw) {
                        return;
                    }
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) {
                        this.clientQueue = parsed;
                    }
                } catch (_) {
                    /* ignore */
                }
            },
            saveQueueToStorage() {
                try {
                    localStorage.setItem('retailPosQueue', JSON.stringify(this.clientQueue));
                } catch (_) {
                    /* ignore */
                }
            },
            holdCurrentClient() {
                if (this.cart.length === 0) {
                    return;
                }
                const n = this.clientQueue.length + 1;
                this.clientQueue.push({
                    id: Date.now(),
                    cart: JSON.parse(JSON.stringify(this.cart)),
                    label: 'Клиент ' + n,
                    lines: this.cart.length,
                });
                this.cart = [];
                this.query = '';
                this.results = [];
                this.searchOpen = false;
                this.saveQueueToStorage();
            },
            resumeQueue(idx) {
                const slot = this.clientQueue[idx];
                if (!slot || !Array.isArray(slot.cart)) {
                    return;
                }
                if (this.cart.length > 0) {
                    this.holdCurrentClient();
                }
                this.cart = slot.cart.map((row) => ({ ...row }));
                this.clientQueue.splice(idx, 1);
                this.saveQueueToStorage();
            },
            syncClientPaidFromCart() {
                if (this.cart.length === 0) {
                    this.clientPaid = '';
                    return;
                }
                this.clientPaid = this.cartTotalFormatted;
            },
            parseMoney(s) {
                if (s == null || s === '') return NaN;
                return parseFloat(String(s).replace(/\s/g, '').replace(',', '.'));
            },
            rowIsRetailService(row) {
                if (!row) return false;
                const v = row.is_service;
                return v === true || v === 1 || v === '1';
            },
            /** Текст остатка в списке поиска (0, если записи на складе нет). */
            goodsRowStockDisplay(row) {
                if (!row || this.rowIsRetailService(row)) return '';
                const raw = row.stock_quantity ?? row.stockQty;
                if (raw == null || String(raw).trim() === '') return '0';
                const s = String(raw).trim();
                if (s === 'undefined' || s === 'null') return '0';
                return s;
            },
            /** Товар без остатка на выбранном складе (услуги не учитываем). */
            goodsRowOutOfStock(row) {
                if (!row) return false;
                if (this.rowIsRetailService(row)) return false;
                if (this.warehouseId <= 0) return false;
                const raw = row.stock_quantity ?? row.stockQty;
                if (raw == null || String(raw).trim() === '') return true;
                const n = this.parseMoney(raw);
                if (!Number.isFinite(n)) return true;
                return n < 1 - 1e-9;
            },
            /** Позиция в чеке: нет остатка или количество больше доступного. */
            cartLineStockDanger(line) {
                if (!line) return false;
                if (this.rowIsRetailService(line)) return false;
                const raw = line.stock_quantity ?? line.stockQty;
                if (raw == null || String(raw).trim() === '') {
                    return this.warehouseId > 0;
                }
                const stock = this.parseMoney(raw);
                if (!Number.isFinite(stock)) return this.warehouseId > 0;
                if (stock < 1 - 1e-9) return true;
                const qty = this.parseMoney(line.quantity);
                if (Number.isFinite(qty) && qty > stock + 1e-9) return true;
                return false;
            },
            async search() {
                const q = this.query.trim();
                if (q.length < 2) {
                    this.results = [];
                    return;
                }
                this.loading = true;
                try {
                    let url = this.goodsSearchUrl + '?q=' + encodeURIComponent(q);
                    if (this.warehouseId > 0) url += '&warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    this.results = Array.isArray(data) ? data : [];
                    if (this.results.length === 1) {
                        const r = this.results[0];
                        const qb = q.trim();
                        if (
                            r.barcode != null &&
                            String(r.barcode).trim() !== '' &&
                            String(r.barcode).trim() === qb
                        ) {
                            this.addProduct(r, { fromBarcode: true });
                            return;
                        }
                    }
                } catch (e) {
                    this.results = [];
                } finally {
                    this.loading = false;
                    // После клика по «×» у type=search Alpine ставит searchOpen=false (@click.outside).
                    // Иначе запрос выполняется, но выпадающий список остаётся скрытым, пока не сфокусировать поле снова.
                    if (this.query.trim().length >= 2) {
                        this.searchOpen = true;
                    }
                }
            },
            addProduct(row, options = {}) {
                if (!row || row.id == null) return;
                const fromBarcode = options.fromBarcode === true;
                const id = row.id;
                const idx = this.cart.findIndex((c) => c.good_id === id);
                const isService = this.rowIsRetailService(row);
                const rawStock = row.stock_quantity ?? row.stockQty;
                const stock =
                    isService
                        ? null
                        : rawStock != null && rawStock !== ''
                          ? this.parseMoney(rawStock)
                          : null;
                if (idx >= 0) {
                    let q = this.parseMoney(this.cart[idx].quantity);
                    if (!Number.isFinite(q)) q = 0;
                    q += 1;
                    const lineIsService =
                        this.rowIsRetailService(this.cart[idx]) || isService;
                    if (!lineIsService && stock != null && Number.isFinite(stock) && q > stock + 1e-9) q = stock;
                    this.cart[idx].quantity = String(q);
                } else {
                    let price = '';
                    if (row.sale_price != null && row.sale_price !== '') price = String(row.sale_price);
                    this.cart.push({
                        line_id: null,
                        good_id: id,
                        article_code: row.article_code || '',
                        name: row.name || '',
                        quantity: '1',
                        unit_price: price,
                        stock_quantity: isService ? null : rawStock,
                        is_service: isService,
                        performer_employee_id: isService ? '' : '',
                    });
                }
                if (fromBarcode) {
                    this.searchOpen = false;
                    this.query = '';
                    this.results = [];
                    return;
                }
                this.searchOpen = true;
            },
            async searchCp() {
                if (!this.counterpartySearchUrl) {
                    return;
                }
                const q = this.cpQuery.trim();
                if (q.length < 2) {
                    this.cpItems = [];
                    return;
                }
                this.cpLoading = true;
                try {
                    const res = await fetch(
                        this.counterpartySearchUrl +
                            '?q=' +
                            encodeURIComponent(q) +
                            '&for=sale',
                        {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        },
                    );
                    const data = await res.json();
                    this.cpItems = Array.isArray(data) ? data : [];
                    this.cpOpen = true;
                } catch (e) {
                    this.cpItems = [];
                } finally {
                    this.cpLoading = false;
                }
            },
            pickCp(item) {
                if (!item) return;
                this.counterpartyId = item.id;
                this.counterpartyLabel = item.full_name || item.name || '';
                this.cpQuery = this.counterpartyLabel;
                this.cpItems = [];
                this.cpOpen = false;
            },
            clearCp() {
                this.counterpartyId = null;
                this.counterpartyLabel = '';
                this.cpQuery = '';
                this.cpItems = [];
                this.cpOpen = false;
            },
            removeLine(i) {
                this.cart.splice(i, 1);
            },
            incQty(i) {
                const line = this.cart[i];
                if (!line) return;
                let q = this.parseMoney(line.quantity);
                if (!Number.isFinite(q)) q = 0;
                const rawSq = line.stock_quantity ?? line.stockQty;
                const stock =
                    this.rowIsRetailService(line)
                        ? null
                        : rawSq != null && rawSq !== ''
                          ? this.parseMoney(rawSq)
                          : null;
                q += 1;
                if (stock != null && Number.isFinite(stock) && q > stock + 1e-9) q = stock;
                line.quantity = String(q);
            },
            decQty(i) {
                const line = this.cart[i];
                if (!line) return;
                let q = this.parseMoney(line.quantity);
                if (!Number.isFinite(q)) q = 1;
                q -= 1;
                if (q < 1) {
                    this.removeLine(i);
                    return;
                }
                line.quantity = String(q);
            },
            lineSumFormatted(line) {
                const q = this.parseMoney(line.quantity);
                const p = this.parseMoney(line.unit_price);
                if (!Number.isFinite(q) || !Number.isFinite(p)) return '—';
                return (q * p).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            get cartTotal() {
                let t = 0;
                for (const line of this.cart) {
                    const q = this.parseMoney(line.quantity);
                    const p = this.parseMoney(line.unit_price);
                    if (Number.isFinite(q) && Number.isFinite(p)) t += q * p;
                }
                return t;
            },
            get cartTotalFormatted() {
                return this.cartTotal.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            formatSum(n) {
                if (!Number.isFinite(n)) return '—';
                return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            get clientPaidParsed() {
                return this.parseMoney(this.clientPaid);
            },
            get changeAmount() {
                const paid = this.clientPaidParsed;
                if (!Number.isFinite(paid)) return null;
                return paid - this.cartTotal;
            },
            handleSubmit(e) {
                if (this.cart.length === 0) {
                    e.preventDefault();
                }
            },
            handleCheckoutDraft(e) {
                if (this.cart.length === 0) {
                    e.preventDefault();
                }
            },
        };
    });

    Alpine.data('retailCheckoutForm', () => {
        const c =
            typeof window !== 'undefined' && window.__retailCheckoutInit && typeof window.__retailCheckoutInit === 'object'
                ? window.__retailCheckoutInit
                : {};
        const defaultId =
            c.defaultAccountId != null && c.defaultAccountId !== '' ? String(c.defaultAccountId) : '';
        const draftTotalSrc = c.draftTotal;
        const parseDraftTotal = () => {
            if (draftTotalSrc == null || draftTotalSrc === '') {
                return 0;
            }
            const n = parseFloat(String(draftTotalSrc).replace(/\s/g, '').replace(',', '.'));
            return Number.isFinite(n) ? n : 0;
        };
        const debtorHintsUrl = typeof c.debtorHintsUrl === 'string' ? c.debtorHintsUrl : '';
        const counterpartySearchUrl = typeof c.counterpartySearchUrl === 'string' ? c.counterpartySearchUrl : '';
        const documentDateInit =
            typeof c.documentDate === 'string' && c.documentDate.trim() !== ''
                ? c.documentDate
                : new Date().toISOString().slice(0, 10);
        const accounts = Array.isArray(c.accounts) ? c.accounts : [];
        const payments =
            accounts.length > 0
                ? accounts.map((a) => ({
                      organization_bank_account_id: String(a.id),
                      amount: '',
                  }))
                : [{ organization_bank_account_id: defaultId, amount: '' }];
        return {
            accounts,
            payments,
            documentDate: documentDateInit,
            draftTotal: parseDraftTotal(),
            debtorName: typeof c.oldDebtorName === 'string' ? c.oldDebtorName : '',
            debtorPhone: typeof c.oldDebtorPhone === 'string' ? c.oldDebtorPhone : '',
            debtorComment: typeof c.oldDebtorComment === 'string' ? c.oldDebtorComment : '',
            debtorHints: [],
            debtorHintsOpen: false,
            debtorHintsLoading: false,
            parseMoney(s) {
                if (s == null || s === '') {
                    return NaN;
                }
                return parseFloat(String(s).replace(/\s/g, '').replace(',', '.'));
            },
            get sumPaid() {
                let t = 0;
                for (const row of this.payments) {
                    const v = this.parseMoney(row.amount);
                    if (Number.isFinite(v)) {
                        t += v;
                    }
                }
                return t;
            },
            get sumPaidFormatted() {
                return this.sumPaid.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            get debtAmount() {
                return Math.max(0, this.draftTotal - this.sumPaid);
            },
            get debtFormatted() {
                return this.debtAmount.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
            onDebtorNameFocus() {
                const q = String(this.debtorName ?? '').trim();
                if (q.length >= 2) {
                    this.fetchDebtorHints();
                }
            },
            async fetchDebtorHints() {
                if (this.debtAmount <= 0.004 || !debtorHintsUrl) {
                    this.debtorHints = [];
                    this.debtorHintsOpen = false;
                    return;
                }
                const q = String(this.debtorName ?? '').trim();
                if (q.length < 2) {
                    this.debtorHints = [];
                    this.debtorHintsOpen = false;
                    return;
                }
                this.debtorHintsLoading = true;
                const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
                try {
                    const histRes = await fetch(debtorHintsUrl + '?q=' + encodeURIComponent(q), { headers });
                    const hist = await histRes.json();
                    let cps = [];
                    if (counterpartySearchUrl !== '') {
                        let cpUrl;
                        try {
                            const u = new URL(counterpartySearchUrl, window.location.href);
                            u.searchParams.set('q', q);
                            cpUrl = u.toString();
                        } catch (_) {
                            const sep = counterpartySearchUrl.includes('?') ? '&' : '?';
                            cpUrl = counterpartySearchUrl + sep + 'q=' + encodeURIComponent(q);
                        }
                        const cpRes = await fetch(cpUrl, { headers });
                        cps = await cpRes.json();
                    }
                    const merged = [];
                    const seen = new Set();
                    const add = (name, phone) => {
                        const n = String(name || '').trim();
                        if (!n) return;
                        const p = String(phone || '').trim();
                        const key = n.toLowerCase() + '|' + p;
                        if (seen.has(key)) return;
                        seen.add(key);
                        merged.push({ debtor_name: n, debtor_phone: p });
                    };
                    for (const h of Array.isArray(hist) ? hist : []) {
                        add(h.debtor_name, h.debtor_phone);
                    }
                    for (const row of Array.isArray(cps) ? cps : []) {
                        add(row.full_name || row.name, row.phone || '');
                    }
                    this.debtorHints = merged.slice(0, 22);
                    this.debtorHintsOpen = this.debtorHints.length > 0;
                } catch (_) {
                    this.debtorHints = [];
                    this.debtorHintsOpen = false;
                } finally {
                    this.debtorHintsLoading = false;
                }
            },
            pickDebtorHint(h) {
                if (!h) return;
                this.debtorName = h.debtor_name || '';
                this.debtorPhone = h.debtor_phone != null ? String(h.debtor_phone) : '';
                this.debtorHints = [];
                this.debtorHintsOpen = false;
            },
        };
    });

    Alpine.data('retailDebtsPage', (raw) => {
        const config = typeof raw === 'object' && raw !== null ? raw : {};
        const defaultId =
            config.defaultAccountId != null && config.defaultAccountId !== ''
                ? String(config.defaultAccountId)
                : '';
        const limit = config.limit != null ? Number(config.limit) : 100;
        const payUrls = config.payUrls && typeof config.payUrls === 'object' ? config.payUrls : {};
        const groupPayUrl = typeof config.groupPayUrl === 'string' ? config.groupPayUrl : '';

        return {
            modalOpen: false,
            formAction: '',
            amount: '',
            accountId: defaultId,
            limit,
            saleIdLabel: '',
            payUrls,
            groupModalOpen: false,
            groupAmount: '',
            groupAccountId: defaultId,
            groupSaleIds: [],
            detailModalOpen: false,
            detailSale: null,
            openPay(saleId, debtAmountRaw) {
                const id = Number(saleId);
                const url = payUrls[id] ?? payUrls[String(id)];
                if (!url) {
                    return;
                }
                this.formAction = url;
                this.saleIdLabel = String(id);
                const s = String(debtAmountRaw ?? '')
                    .replace(/\s/g, '')
                    .replace(',', '.');
                const n = parseFloat(s);
                this.amount = Number.isFinite(n)
                    ? n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '';
                this.accountId = defaultId;
                this.modalOpen = true;
            },
            closeModal() {
                this.modalOpen = false;
            },
            openGroupPay(saleIds, totalDebtRaw) {
                if (!groupPayUrl || !Array.isArray(saleIds) || saleIds.length < 2) {
                    return;
                }
                this.groupSaleIds = saleIds.map((id) => Number(id));
                const s = String(totalDebtRaw ?? '')
                    .replace(/\s/g, '')
                    .replace(',', '.');
                const n = parseFloat(s);
                this.groupAmount = Number.isFinite(n)
                    ? n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '';
                this.groupAccountId = defaultId;
                this.groupModalOpen = true;
            },
            closeGroupModal() {
                this.groupModalOpen = false;
            },
            openSaleDetail(sale) {
                this.detailSale = sale && typeof sale === 'object' ? sale : null;
                this.detailModalOpen = true;
            },
            closeDetailModal() {
                this.detailModalOpen = false;
                this.detailSale = null;
            },
        };
    });

    /** Справочник товаров: тот же API, что у розницы (admin.goods.search + exclude_services), выпадающий список как на POS. */
    Alpine.data('saleGoodsSearch', () => {
        const c =
            typeof window !== 'undefined' && window.__saleGoodsInit && typeof window.__saleGoodsInit === 'object'
                ? window.__saleGoodsInit
                : {};
        const searchUrl = typeof c.searchUrl === 'string' ? c.searchUrl : '';
        const editUrlTemplate = typeof c.editUrlTemplate === 'string' ? c.editUrlTemplate : '';
        const initialQuery = typeof c.initialQuery === 'string' ? c.initialQuery : '';
        const openModalEvent = c.openModalEvent === true;
        const openModalEventName =
            typeof c.openModalEventName === 'string' ? c.openModalEventName.trim() : '';

        return {
            query: initialQuery,
            results: [],
            loading: false,
            open: false,
            buildFetchUrl() {
                if (searchUrl === '') {
                    return '';
                }
                const q = this.query.trim();
                try {
                    const u = new URL(searchUrl, window.location.href);
                    u.searchParams.set('q', q);
                    return u.toString();
                } catch (e) {
                    const j = searchUrl.includes('?') ? '&' : '?';
                    return searchUrl + j + 'q=' + encodeURIComponent(q);
                }
            },
            async fetchResults() {
                const q = this.query.trim();
                if (q.length < 2) {
                    this.results = [];
                    return;
                }
                this.loading = true;
                try {
                    const u = this.buildFetchUrl();
                    if (!u) {
                        this.results = [];
                        return;
                    }
                    const res = await fetch(u, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    this.results = Array.isArray(data) ? data : [];
                } catch (e) {
                    this.results = [];
                } finally {
                    this.loading = false;
                }
            },
            onFocus() {
                this.open = true;
                if (this.query.trim().length >= 2) {
                    this.fetchResults();
                }
            },
            goEdit(row) {
                if (!row || row.id == null) {
                    return;
                }
                this.open = false;
                if (openModalEventName !== '') {
                    window.dispatchEvent(new CustomEvent(openModalEventName, { detail: { id: row.id } }));
                    return;
                }
                if (openModalEvent) {
                    window.dispatchEvent(new CustomEvent('sale-good-open-modal', { detail: { id: row.id } }));
                    return;
                }
                if (editUrlTemplate === '') {
                    return;
                }
                window.location.href = editUrlTemplate.replace('__ID__', String(row.id));
            },
            onInputEnter(e) {
                if (e.key !== 'Enter') {
                    return;
                }
                e.preventDefault();
                if (this.results.length === 1) {
                    this.goEdit(this.results[0]);
                } else if (this.$refs.saleGoodsTableForm && this.query.trim() !== '') {
                    this.$refs.saleGoodsTableForm.submit();
                }
            },
            formatSalePrice(v) {
                if (v == null || v === '') {
                    return '';
                }
                const n = parseFloat(String(v).replace(/\s/g, '').replace(',', '.'));
                if (!Number.isFinite(n)) {
                    return '';
                }

                return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' сом';
            },
        };
    });

    /** Карточка товара в модальном окне (справочник trade.sale-goods). */
    Alpine.data('saleGoodDetailModal', (config) => {
        const c = config && typeof config === 'object' ? config : {};
        const dataUrlTemplate = typeof c.dataUrlTemplate === 'string' ? c.dataUrlTemplate : '';
        const updateUrlTemplate = typeof c.updateUrlTemplate === 'string' ? c.updateUrlTemplate : '';
        const csrf = typeof c.csrf === 'string' ? c.csrf : '';

        return {
            modalOpen: false,
            loading: false,
            saving: false,
            loadError: '',
            saveError: '',
            fieldErrors: {},
            activeTab: 'info',
            goodId: null,
            meta: { display_name: '', aggregated_stock: 0, aggregated_purchase_price: null },
            movements: [],
            form: {
                article_code: '',
                name: '',
                barcode: '',
                category: '',
                unit: '',
                sale_price: '',
                wholesale_price: '',
                min_sale_price: '',
                oem: '',
                factory_number: '',
                min_stock: '',
            },
            categoryList: [],
            showCategoryAdd: false,
            newCategoryName: '',
            openModal(id) {
                if (id == null || dataUrlTemplate === '') {
                    return;
                }
                this.loadGood(id);
            },
            closeModal() {
                this.modalOpen = false;
                try {
                    document.body.style.overflow = '';
                } catch (e) {}
                this.loadError = '';
                this.saveError = '';
                this.fieldErrors = {};
                this.showCategoryAdd = false;
                this.newCategoryName = '';
            },
            async loadGood(id) {
                this.loading = true;
                this.loadError = '';
                this.saveError = '';
                this.fieldErrors = {};
                this.modalOpen = true;
                try {
                    document.body.style.overflow = 'hidden';
                } catch (e) {}
                this.activeTab = 'info';
                this.goodId = id;
                this.showCategoryAdd = false;
                this.newCategoryName = '';
                try {
                    const url = dataUrlTemplate.replace('__ID__', String(id));
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) {
                        throw new Error('load');
                    }
                    const data = await res.json();
                    const g = data.good && typeof data.good === 'object' ? data.good : {};
                    this.goodId = g.id != null ? g.id : id;
                    this.form = {
                        article_code: g.article_code != null ? String(g.article_code) : '',
                        name: g.name != null ? String(g.name) : '',
                        barcode: g.barcode != null ? String(g.barcode) : '',
                        category: g.category != null ? String(g.category) : '',
                        unit: g.unit != null ? String(g.unit) : '',
                        sale_price: g.sale_price != null ? String(g.sale_price) : '',
                        wholesale_price: g.wholesale_price != null ? String(g.wholesale_price) : '',
                        min_sale_price: g.min_sale_price != null ? String(g.min_sale_price) : '',
                        oem: g.oem != null ? String(g.oem) : '',
                        factory_number: g.factory_number != null ? String(g.factory_number) : '',
                        min_stock: g.min_stock != null ? String(g.min_stock) : '',
                    };
                    const fromApi =
                        data.categories && Array.isArray(data.categories)
                            ? data.categories.map((x) => String(x).trim()).filter((s) => s !== '')
                            : [];
                    const curCat = (this.form.category || '').trim();
                    const merged = new Set(fromApi);
                    if (curCat !== '') {
                        merged.add(curCat);
                    }
                    this.categoryList = Array.from(merged);
                    this.meta = {
                        display_name: g.display_name != null ? String(g.display_name) : this.form.name,
                        aggregated_stock: g.aggregated_stock != null ? Number(g.aggregated_stock) : 0,
                        aggregated_purchase_price:
                            g.aggregated_purchase_price != null && g.aggregated_purchase_price !== ''
                                ? Number(g.aggregated_purchase_price)
                                : null,
                    };
                    this.movements = Array.isArray(data.movements) ? data.movements : [];
                } catch (e) {
                    this.loadError = 'Не удалось загрузить карточку товара.';
                } finally {
                    this.loading = false;
                }
            },
            sortedCategories() {
                return [...this.categoryList].sort((a, b) =>
                    String(a).localeCompare(String(b), 'ru', { sensitivity: 'base' })
                );
            },
            addNewCategory() {
                const name = (this.newCategoryName || '').trim();
                if (!name || name.length > 120) {
                    return;
                }
                if (!this.categoryList.includes(name)) {
                    this.categoryList = [...this.categoryList, name];
                }
                this.form.category = name;
                this.newCategoryName = '';
                this.showCategoryAdd = false;
            },
            fieldErr(key) {
                const e = this.fieldErrors[key];
                return Array.isArray(e) && e.length ? e[0] : '';
            },
            async saveGood() {
                if (this.goodId == null || updateUrlTemplate === '' || csrf === '') {
                    return;
                }
                this.saving = true;
                this.saveError = '';
                this.fieldErrors = {};
                const fd = new FormData();
                fd.append('_token', csrf);
                fd.append('_method', 'PUT');
                const keys = [
                    'article_code',
                    'name',
                    'barcode',
                    'category',
                    'unit',
                    'sale_price',
                    'wholesale_price',
                    'min_sale_price',
                    'oem',
                    'factory_number',
                    'min_stock',
                ];
                keys.forEach((k) => {
                    fd.append(k, this.form[k] != null ? String(this.form[k]) : '');
                });
                try {
                    const url = updateUrlTemplate.replace('__ID__', String(this.goodId));
                    const res = await fetch(url, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    let data = {};
                    try {
                        data = await res.json();
                    } catch (e2) {
                        data = {};
                    }
                    if (res.status === 422 && data.errors && typeof data.errors === 'object') {
                        this.fieldErrors = data.errors;
                        return;
                    }
                    if (!res.ok) {
                        this.saveError =
                            typeof data.message === 'string' ? data.message : 'Не удалось сохранить изменения.';
                        return;
                    }
                    this.closeModal();
                    window.location.reload();
                } catch (e) {
                    this.saveError = 'Ошибка сети.';
                } finally {
                    this.saving = false;
                }
            },
            fmtQty(n, dir) {
                const x = Number(n);
                if (!Number.isFinite(x)) {
                    return '—';
                }
                const s = x.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
                if (dir === 'in') {
                    return '+' + s;
                }
                if (dir === 'out') {
                    return '−' + s;
                }
                return s;
            },
        };
    });

    /** Карточка услуги (справочник trade.sale-services), поля + категория select. */
    Alpine.data('saleServiceDetailModal', (config) => {
        const c = config && typeof config === 'object' ? config : {};
        const dataUrlTemplate = typeof c.dataUrlTemplate === 'string' ? c.dataUrlTemplate : '';
        const updateUrlTemplate = typeof c.updateUrlTemplate === 'string' ? c.updateUrlTemplate : '';
        const csrf = typeof c.csrf === 'string' ? c.csrf : '';

        return {
            modalOpen: false,
            loading: false,
            saving: false,
            loadError: '',
            saveError: '',
            fieldErrors: {},
            serviceId: null,
            meta: { display_name: '', article_code: '' },
            form: {
                name: '',
                unit: '',
                sale_price: '',
                category: '',
            },
            categoryList: [],
            showCategoryAdd: false,
            newCategoryName: '',
            openModal(id) {
                if (id == null || dataUrlTemplate === '') {
                    return;
                }
                this.loadService(id);
            },
            closeModal() {
                this.modalOpen = false;
                try {
                    document.body.style.overflow = '';
                } catch (e) {}
                this.loadError = '';
                this.saveError = '';
                this.fieldErrors = {};
                this.showCategoryAdd = false;
                this.newCategoryName = '';
            },
            async loadService(id) {
                this.loading = true;
                this.loadError = '';
                this.saveError = '';
                this.fieldErrors = {};
                this.modalOpen = true;
                try {
                    document.body.style.overflow = 'hidden';
                } catch (e) {}
                this.serviceId = id;
                this.showCategoryAdd = false;
                this.newCategoryName = '';
                try {
                    const url = dataUrlTemplate.replace('__ID__', String(id));
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) {
                        throw new Error('load');
                    }
                    const data = await res.json();
                    const g = data.good && typeof data.good === 'object' ? data.good : {};
                    this.serviceId = g.id != null ? g.id : id;
                    this.form = {
                        name: g.name != null ? String(g.name) : '',
                        unit: g.unit != null ? String(g.unit) : '',
                        sale_price: g.sale_price != null ? String(g.sale_price) : '',
                        category: g.category != null ? String(g.category) : '',
                    };
                    this.meta = {
                        display_name: g.name != null ? String(g.name) : '',
                        article_code: g.article_code != null ? String(g.article_code) : '',
                    };
                    const fromApi =
                        data.categories && Array.isArray(data.categories)
                            ? data.categories.map((x) => String(x).trim()).filter((s) => s !== '')
                            : [];
                    const curCat = (this.form.category || '').trim();
                    const merged = new Set(fromApi);
                    if (curCat !== '') {
                        merged.add(curCat);
                    }
                    this.categoryList = Array.from(merged);
                } catch (e) {
                    this.loadError = 'Не удалось загрузить карточку услуги.';
                } finally {
                    this.loading = false;
                }
            },
            sortedCategories() {
                return [...this.categoryList].sort((a, b) =>
                    String(a).localeCompare(String(b), 'ru', { sensitivity: 'base' })
                );
            },
            addNewCategory() {
                const name = (this.newCategoryName || '').trim();
                if (!name || name.length > 120) {
                    return;
                }
                if (!this.categoryList.includes(name)) {
                    this.categoryList = [...this.categoryList, name];
                }
                this.form.category = name;
                this.newCategoryName = '';
                this.showCategoryAdd = false;
            },
            fieldErr(key) {
                const e = this.fieldErrors[key];
                return Array.isArray(e) && e.length ? e[0] : '';
            },
            async saveService() {
                if (this.serviceId == null || updateUrlTemplate === '' || csrf === '') {
                    return;
                }
                this.saving = true;
                this.saveError = '';
                this.fieldErrors = {};
                const fd = new FormData();
                fd.append('_token', csrf);
                fd.append('_method', 'PUT');
                ['name', 'unit', 'sale_price', 'category'].forEach((k) => {
                    fd.append(k, this.form[k] != null ? String(this.form[k]) : '');
                });
                try {
                    const url = updateUrlTemplate.replace('__ID__', String(this.serviceId));
                    const res = await fetch(url, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    let data = {};
                    try {
                        data = await res.json();
                    } catch (e2) {
                        data = {};
                    }
                    if (res.status === 422 && data.errors && typeof data.errors === 'object') {
                        this.fieldErrors = data.errors;
                        return;
                    }
                    if (!res.ok) {
                        this.saveError =
                            typeof data.message === 'string' ? data.message : 'Не удалось сохранить изменения.';
                        return;
                    }
                    this.closeModal();
                    window.location.reload();
                } catch (e) {
                    this.saveError = 'Ошибка сети.';
                } finally {
                    this.saving = false;
                }
            },
        };
    });

    Alpine.data('serviceFulfillLegalCp', () => {
        const c =
            typeof window !== 'undefined' && window.__serviceFulfillLegal && typeof window.__serviceFulfillLegal === 'object'
                ? window.__serviceFulfillLegal
                : {};
        const searchUrl = typeof c.searchUrl === 'string' ? c.searchUrl : '';
        const quickUrl = typeof c.quickUrl === 'string' ? c.quickUrl : '';
        const csrf = typeof c.csrf === 'string' ? c.csrf : '';
        const prefill = c.prefill && typeof c.prefill === 'object' ? c.prefill : null;

        return {
            query: '',
            items: [],
            loading: false,
            open: false,
            counterpartyId: null,
            label: '',
            modalOpen: false,
            quickName: '',
            quickForm: 'ip',
            quickSaving: false,
            quickError: '',
            init() {
                if (prefill && prefill.id) {
                    this.counterpartyId = prefill.id;
                    this.label = prefill.label || '';
                    this.query = this.label;
                }
            },
            async onInput() {
                const q = this.query.trim();
                if (this.counterpartyId && q !== this.label) {
                    this.counterpartyId = null;
                    this.label = '';
                }
                if (q.length < 2) {
                    this.items = [];
                    this.open = false;
                    return;
                }
                this.loading = true;
                this.open = true;
                try {
                    const url = searchUrl + '?q=' + encodeURIComponent(q) + '&buyers_only=1';
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    this.items = Array.isArray(data) ? data : [];
                } catch (e) {
                    this.items = [];
                } finally {
                    this.loading = false;
                }
            },
            pick(item) {
                this.counterpartyId = item.id;
                this.label = item.full_name || item.name || '';
                this.query = this.label;
                this.items = [];
                this.open = false;
            },
            async quickSave() {
                this.quickError = '';
                const name = this.quickName.trim();
                if (!name) {
                    this.quickError = 'Введите наименование.';
                    return;
                }
                const legalForm = this.quickForm === 'osoo' ? 'osoo' : 'ip';
                this.quickSaving = true;
                try {
                    const res = await fetch(quickUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            name,
                            legal_form: legalForm,
                            kind: 'buyer',
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        let msg = data.message || '';
                        if (!msg && data.errors && typeof data.errors === 'object') {
                            msg = Object.values(data.errors)
                                .flat()
                                .join(' ');
                        }
                        this.quickError = msg || 'Не удалось сохранить.';
                        return;
                    }
                    this.pick({
                        id: data.id,
                        full_name: data.full_name,
                        name: data.name,
                    });
                    this.modalOpen = false;
                    this.quickName = '';
                } catch (e) {
                    this.quickError = 'Ошибка сети.';
                } finally {
                    this.quickSaving = false;
                }
            },
        };
    });

    Alpine.data('serviceGoodsStockLookup', () => {
        const c =
            typeof window !== 'undefined' && window.__serviceSalesPage && typeof window.__serviceSalesPage === 'object'
                ? window.__serviceSalesPage
                : {};
        const goodsSearchUrl = typeof c.goodsStockUrl === 'string' ? c.goodsStockUrl : '';
        const warehouseId =
            typeof c.warehouseId === 'number' && !Number.isNaN(c.warehouseId)
                ? c.warehouseId
                : parseInt(String(c.warehouseId ?? '0'), 10) || 0;

        return {
            query: '',
            loading: false,
            results: [],
            searchOpen: false,
            goodsSearchUrl,
            warehouseId,
            async search() {
                const q = this.query.trim();
                if (this.warehouseId <= 0) {
                    this.results = [];
                    return;
                }
                if (q.length < 2) {
                    this.results = [];
                    return;
                }
                if (!this.goodsSearchUrl) {
                    this.results = [];
                    return;
                }
                this.loading = true;
                try {
                    let url = this.goodsSearchUrl + '?q=' + encodeURIComponent(q);
                    url += '&warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    this.results = Array.isArray(data) ? data : [];
                } catch (e) {
                    this.results = [];
                } finally {
                    this.loading = false;
                }
            },
            stockLabel(row) {
                if (!row || row.stock_quantity == null || row.stock_quantity === '') {
                    return '—';
                }
                return String(row.stock_quantity);
            },
        };
    });

    Alpine.data('serviceCatalogSearch', () => {
        const c =
            typeof window !== 'undefined' && window.__serviceSalesPage && typeof window.__serviceSalesPage === 'object'
                ? window.__serviceSalesPage
                : {};
        const servicesCatalogUrl = typeof c.servicesCatalogUrl === 'string' ? c.servicesCatalogUrl : '';

        return {
            query: '',
            loading: false,
            results: [],
            searchOpen: false,
            servicesCatalogUrl,
            async search() {
                const q = this.query.trim();
                if (q.length < 2) {
                    this.results = [];
                    return;
                }
                if (!this.servicesCatalogUrl) {
                    this.results = [];
                    return;
                }
                this.loading = true;
                try {
                    const url = this.servicesCatalogUrl + '?q=' + encodeURIComponent(q);
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    this.results = Array.isArray(data) ? data : [];
                } catch (e) {
                    this.results = [];
                } finally {
                    this.loading = false;
                }
            },
            defaultPrice(row) {
                if (!row || row.sale_price == null || row.sale_price === '') {
                    return '';
                }
                const p = parseFloat(String(row.sale_price).replace(/\s/g, '').replace(',', '.'));
                if (!Number.isFinite(p)) {
                    return '';
                }
                return p.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },
        };
    });

    Alpine.data('bankCounterpartyField', () => {
        const cfg =
            typeof window !== 'undefined' &&
            window.__bankCounterpartyFieldInit &&
            typeof window.__bankCounterpartyFieldInit === 'object'
                ? window.__bankCounterpartyFieldInit
                : {};
        const searchUrl = typeof cfg.searchUrl === 'string' ? cfg.searchUrl : '';
        const quickUrl = typeof cfg.quickUrl === 'string' ? cfg.quickUrl : '';
        const quickKind =
            cfg.quickKind === 'buyer' ? 'buyer' : cfg.quickKind === 'other' ? 'other' : 'supplier';
        const allowKinds =
            quickKind === 'buyer'
                ? new Set(['buyer', 'other'])
                : quickKind === 'other'
                  ? new Set(['other'])
                  : new Set(['supplier', 'other']);
        const initialId = parseInt(String(cfg.initialId ?? '0'), 10) || 0;
        const initialLabel = typeof cfg.initialLabel === 'string' ? cfg.initialLabel : '';
        const quickTitle =
            typeof cfg.quickTitle === 'string' && cfg.quickTitle.trim() !== ''
                ? cfg.quickTitle.trim()
                : quickKind === 'buyer'
                  ? 'Новый клиент'
                  : quickKind === 'other'
                    ? 'Новый контрагент (прочее)'
                    : 'Новый поставщик';
        const quickBtnAdd =
            typeof cfg.quickBtnAdd === 'string' && cfg.quickBtnAdd.trim() !== ''
                ? cfg.quickBtnAdd.trim()
                : quickKind === 'buyer'
                  ? 'Добавить клиента…'
                  : quickKind === 'other'
                    ? 'Добавить (прочее)…'
                    : 'Добавить поставщика…';
        const requireSelection = cfg.requireSelection !== false;

        return {
            counterpartyId: initialId,
            query: initialLabel,
            committedLabel: initialLabel,
            cpItems: [],
            cpLoading: false,
            cpNoHits: false,
            cpTimer: null,
            cpBlurTimer: null,
            cpPos: { top: 0, left: 0, width: 320 },
            cpQuickOpen: false,
            cpQuickLegalForm: 'osoo',
            cpQuickSaving: false,
            cpQuickError: '',
            quickTitle,
            quickBtnAdd,
            kindLabel(k) {
                const m = { buyer: 'Покупатель', supplier: 'Поставщик', other: 'Прочее' };
                return m[k] || k || '';
            },
            refreshCpPos(el) {
                if (!el || !el.getBoundingClientRect) {
                    return;
                }
                const r = el.getBoundingClientRect();
                const w = Math.max(r.width, 300);
                let left = r.left;
                if (left + w > window.innerWidth - 8) {
                    left = Math.max(8, window.innerWidth - w - 8);
                }
                this.cpPos = { top: r.bottom + 3, left, width: w };
            },
            showCpDropdown() {
                if (this.cpQuickOpen) {
                    return true;
                }
                const q = (this.query || '').trim();
                if (q.length < 2) {
                    return false;
                }
                return this.cpLoading || this.cpItems.length > 0 || this.cpNoHits;
            },
            closeCpUi() {
                clearTimeout(this.cpTimer);
                this.cpItems = [];
                this.cpLoading = false;
                this.cpNoHits = false;
                this.cpQuickOpen = false;
                this.cpQuickError = '';
            },
            onCpEscape() {
                this.closeCpUi();
            },
            onCpFocus(event) {
                clearTimeout(this.cpBlurTimer);
                this.refreshCpPos(event.target);
                const q = (this.query || '').trim();
                if (q.length >= 2) {
                    clearTimeout(this.cpTimer);
                    this.cpTimer = setTimeout(() => this.runCpSearch(event.target, q), 120);
                }
            },
            onCpInput(event) {
                const el = event.target;
                this.query = el.value || '';
                this.refreshCpPos(el);
                if ((this.query || '').trim() !== (this.committedLabel || '').trim()) {
                    this.counterpartyId = 0;
                }
                this.cpQuickOpen = false;
                this.cpQuickError = '';
                clearTimeout(this.cpTimer);
                const q = (this.query || '').trim();
                if (q.length < 2) {
                    this.closeCpUi();
                    return;
                }
                this.cpLoading = true;
                this.cpNoHits = false;
                this.cpItems = [];
                this.cpTimer = setTimeout(() => this.runCpSearch(el, q), 280);
            },
            onCpBlur() {
                this.cpBlurTimer = setTimeout(() => {
                    const root = this.$refs.bankCpRoot;
                    if (root && typeof root.contains === 'function' && root.contains(document.activeElement)) {
                        return;
                    }
                    this.cpItems = [];
                    this.cpNoHits = false;
                    this.cpQuickOpen = false;
                }, 220);
            },
            async runCpSearch(el, q) {
                if (!searchUrl || q.length < 2) {
                    return;
                }
                this.cpLoading = true;
                this.cpNoHits = false;
                this.cpItems = [];
                try {
                    const sep = searchUrl.includes('?') ? '&' : '?';
                    const url = `${searchUrl}${sep}q=${encodeURIComponent(q)}`;
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) {
                        throw new Error('search');
                    }
                    const data = await res.json();
                    const rows = Array.isArray(data) ? data : [];
                    this.cpItems = rows.filter((it) => it && allowKinds.has(it.kind));
                    this.cpNoHits = this.cpItems.length === 0;
                } catch {
                    this.cpItems = [];
                    this.cpNoHits = false;
                } finally {
                    this.cpLoading = false;
                    this.$nextTick(() => this.refreshCpPos(el));
                }
            },
            pickCounterparty(item) {
                clearTimeout(this.cpBlurTimer);
                if (!item || !item.id) {
                    return;
                }
                this.counterpartyId = item.id;
                const label =
                    (item.full_name && String(item.full_name).trim()) ||
                    (item.name && String(item.name).trim()) ||
                    '';
                this.query = label;
                this.committedLabel = label;
                this.closeCpUi();
            },
            openCpQuickAdd(event) {
                if (event) {
                    event.preventDefault();
                }
                clearTimeout(this.cpBlurTimer);
                const el = this.$refs.bankCpInput;
                const q = (el && el.value ? el.value : this.query || '').trim();
                if (q.length >= 2) {
                    this.query = q;
                }
                this.cpQuickLegalForm = 'osoo';
                this.cpQuickError = '';
                this.cpQuickOpen = true;
                this.cpItems = [];
                this.cpNoHits = false;
                this.$nextTick(() => {
                    if (el && el.getBoundingClientRect) {
                        this.refreshCpPos(el);
                    }
                });
            },
            async submitCpQuickAdd() {
                const name = (this.query || '').trim();
                if (name.length < 1) {
                    this.cpQuickError = 'Введите наименование.';
                    return;
                }
                if (!quickUrl) {
                    this.cpQuickError = 'Сохранение недоступно.';
                    return;
                }
                const token =
                    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                this.cpQuickSaving = true;
                this.cpQuickError = '';
                try {
                    const res = await fetch(quickUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({
                            name,
                            legal_form: this.cpQuickLegalForm,
                            kind: quickKind,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        let msg = 'Не удалось сохранить.';
                        if (data && typeof data === 'object') {
                            if (data.message && typeof data.message === 'string') {
                                msg = data.message;
                            } else if (data.errors && typeof data.errors === 'object') {
                                const first = Object.values(data.errors).flat().find((e) => typeof e === 'string');
                                if (first) {
                                    msg = first;
                                }
                            }
                        }
                        this.cpQuickError = msg;
                        return;
                    }
                    this.counterpartyId = data.id;
                    const label =
                        (data.full_name && String(data.full_name).trim()) ||
                        (data.name && String(data.name).trim()) ||
                        name;
                    this.query = label;
                    this.committedLabel = label;
                    this.cpQuickOpen = false;
                    this.closeCpUi();
                } catch {
                    this.cpQuickError = 'Ошибка сети.';
                } finally {
                    this.cpQuickSaving = false;
                }
            },
            validateBeforeSubmit(e) {
                if (!requireSelection) {
                    return;
                }
                if (!this.counterpartyId || this.counterpartyId <= 0) {
                    e.preventDefault();
                    window.alert(
                        'Укажите контрагента: введите от 2 букв и выберите строку в списке или создайте нового контрагента.',
                    );
                }
            },
        };
    });

    /**
     * Списки банковских операций с фильтром по контрагенту:
     * ввод от 2 символов → API поиск → выбор строки задаёт counterparty_id; кнопка — поиск по тексту q.
     * Конфиг: window.__bankMovementCpListFilterInit — searchUrl, listUrl, initialValue, optional allowedKinds ['buyer','other'] или ['supplier','other'].
     */
    Alpine.data('bankMovementCpListFilter', () => {
        const cfg =
            typeof window !== 'undefined' &&
            window.__bankMovementCpListFilterInit &&
            typeof window.__bankMovementCpListFilterInit === 'object'
                ? window.__bankMovementCpListFilterInit
                : {};
        const searchUrl = typeof cfg.searchUrl === 'string' ? cfg.searchUrl : '';
        const listUrl = typeof cfg.listUrl === 'string' ? cfg.listUrl : '';
        const initialValue =
            typeof cfg.initialValue === 'string' ? cfg.initialValue : '';
        const appliedCounterpartyId = parseInt(String(cfg.appliedCounterpartyId ?? '0'), 10) || 0;
        let defaultKinds = ['buyer', 'other'];
        if (Array.isArray(cfg.allowedKinds) && cfg.allowedKinds.length > 0) {
            defaultKinds = cfg.allowedKinds.filter((k) => typeof k === 'string');
        }
        const allowKinds = new Set(defaultKinds.length > 0 ? defaultKinds : ['buyer', 'other']);

        return {
            query: initialValue,
            cpItems: [],
            cpLoading: false,
            cpNoHits: false,
            cpTimer: null,
            cpBlurTimer: null,
            cpPos: { top: 0, left: 0, width: 320 },
            kindLabel(k) {
                const m = { buyer: 'Покупатель', supplier: 'Поставщик', other: 'Прочее' };
                return m[k] || k || '';
            },
            refreshCpPos(el) {
                if (!el || !el.getBoundingClientRect) {
                    return;
                }
                const r = el.getBoundingClientRect();
                const w = Math.max(r.width, 300);
                let left = r.left;
                if (left + w > window.innerWidth - 8) {
                    left = Math.max(8, window.innerWidth - w - 8);
                }
                this.cpPos = { top: r.bottom + 3, left, width: w };
            },
            showCpDropdown() {
                const q = (this.query || '').trim();
                if (q.length < 2) {
                    return false;
                }
                return this.cpLoading || this.cpItems.length > 0 || this.cpNoHits;
            },
            closeCpUi() {
                clearTimeout(this.cpTimer);
                this.cpItems = [];
                this.cpLoading = false;
                this.cpNoHits = false;
            },
            onCpEscape() {
                this.closeCpUi();
            },
            onCpFocus(event) {
                clearTimeout(this.cpBlurTimer);
                const el = event.target;
                this.refreshCpPos(el);
                const q = (this.query || '').trim();
                if (q.length >= 2) {
                    clearTimeout(this.cpTimer);
                    this.cpTimer = setTimeout(() => this.runCpSearch(el, q), 120);
                }
            },
            onCpInput(event) {
                const el = event.target;
                this.refreshCpPos(el);
                clearTimeout(this.cpTimer);
                const q = ((this.query != null ? String(this.query) : '') || '').trim();
                if (q.length < 2) {
                    this.closeCpUi();
                    return;
                }
                this.cpLoading = true;
                this.cpNoHits = false;
                this.cpItems = [];
                this.cpTimer = setTimeout(() => this.runCpSearch(el, q), 280);
            },
            onCpBlur() {
                this.cpBlurTimer = setTimeout(() => {
                    const root = this.$refs.bankCpListFilterRoot;
                    if (
                        root &&
                        typeof root.contains === 'function' &&
                        root.contains(document.activeElement)
                    ) {
                        return;
                    }
                    this.cpItems = [];
                    this.cpNoHits = false;
                }, 220);
            },
            async runCpSearch(el, q) {
                if (!searchUrl || q.length < 2) {
                    return;
                }
                this.cpLoading = true;
                this.cpNoHits = false;
                this.cpItems = [];
                try {
                    const sep = searchUrl.includes('?') ? '&' : '?';
                    const url = `${searchUrl}${sep}q=${encodeURIComponent(q)}`;
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) {
                        throw new Error('search');
                    }
                    const data = await res.json();
                    const rows = Array.isArray(data) ? data : [];
                    this.cpItems = rows.filter((it) => it && allowKinds.has(it.kind));
                    this.cpNoHits = this.cpItems.length === 0;
                } catch {
                    this.cpItems = [];
                    this.cpNoHits = false;
                } finally {
                    this.cpLoading = false;
                    this.$nextTick(() => this.refreshCpPos(el));
                }
            },
            pickCounterparty(item) {
                clearTimeout(this.cpBlurTimer);
                if (!item || !item.id || !listUrl) {
                    return;
                }
                try {
                    const u = listUrl.startsWith('http')
                        ? new URL(listUrl)
                        : new URL(listUrl, window.location.origin);
                    u.searchParams.set('counterparty_id', String(item.id));
                    window.location.assign(u.toString());
                } catch {
                    const sep = listUrl.includes('?') ? '&' : '?';
                    window.location.assign(`${listUrl}${sep}counterparty_id=${encodeURIComponent(String(item.id))}`);
                }
            },
            submitTextSearch(ev) {
                if (ev) {
                    ev.preventDefault();
                }
                const raw = this.query ?? '';
                const q = typeof raw === 'string' ? raw.trim() : String(raw).trim();
                if (!listUrl) {
                    return;
                }
                try {
                    const u = listUrl.startsWith('http')
                        ? new URL(listUrl)
                        : new URL(listUrl, window.location.origin);
                    u.search = '';
                    if (q !== '') {
                        u.searchParams.set('q', q);
                    }
                    window.location.assign(u.toString());
                } catch {
                    let target = listUrl;
                    const join = listUrl.includes('?') ? '&' : '?';
                    target = q !== '' ? `${listUrl}${join}q=${encodeURIComponent(q)}` : listUrl.split('?')[0];
                    window.location.assign(target);
                }
            },
        };
    });

    /** Банк: поле категории (прочий приход / прочий расход) — подсказки из expense_category по филиалу */
    Alpine.data('bankCashExpenseCategoryField', () => {
        const cfg =
            typeof window !== 'undefined' &&
            window.__bankCashExpenseCategoryFieldInit &&
            typeof window.__bankCashExpenseCategoryFieldInit === 'object'
                ? window.__bankCashExpenseCategoryFieldInit
                : {};
        const searchUrl = typeof cfg.searchUrl === 'string' ? cfg.searchUrl : '';
        const initialValue =
            typeof cfg.initialValue === 'string' ? cfg.initialValue : '';

        return {
            query: initialValue,
            catItems: [],
            catLoading: false,
            catNoHits: false,
            catTimer: null,
            catBlurTimer: null,
            catPos: { top: 0, left: 0, width: 320 },
            refreshCatPos(el) {
                if (!el || !el.getBoundingClientRect) {
                    return;
                }
                const r = el.getBoundingClientRect();
                const w = Math.max(r.width, 260);
                let left = r.left;
                if (left + w > window.innerWidth - 8) {
                    left = Math.max(8, window.innerWidth - w - 8);
                }
                this.catPos = { top: r.bottom + 3, left, width: w };
            },
            showCatDropdown() {
                const q = (String(this.query ?? '').trim());
                if (q.length < 2) {
                    return false;
                }
                return this.catLoading || this.catItems.length > 0 || this.catNoHits;
            },
            closeCatUi() {
                clearTimeout(this.catTimer);
                this.catItems = [];
                this.catLoading = false;
                this.catNoHits = false;
            },
            onCatEscape() {
                this.closeCatUi();
            },
            onCatFocus(ev) {
                clearTimeout(this.catBlurTimer);
                const el = ev.target;
                this.refreshCatPos(el);
                const q = (String(this.query ?? '').trim());
                if (q.length >= 2) {
                    clearTimeout(this.catTimer);
                    this.catTimer = setTimeout(() => this.runCatSearch(el, q), 120);
                }
            },
            onCatInput(ev) {
                const el = ev.target;
                this.refreshCatPos(el);
                clearTimeout(this.catTimer);
                const q = (String(this.query ?? '').trim());
                if (q.length < 2) {
                    this.closeCatUi();
                    return;
                }
                this.catLoading = true;
                this.catNoHits = false;
                this.catItems = [];
                this.catTimer = setTimeout(() => this.runCatSearch(el, q), 280);
            },
            onCatBlur() {
                this.catBlurTimer = setTimeout(() => {
                    const root = this.$refs.catSuggestRoot;
                    if (
                        root &&
                        typeof root.contains === 'function' &&
                        root.contains(document.activeElement)
                    ) {
                        return;
                    }
                    this.catItems = [];
                    this.catNoHits = false;
                }, 220);
            },
            async runCatSearch(el, q) {
                if (!searchUrl || q.length < 2) {
                    return;
                }
                this.catLoading = true;
                this.catNoHits = false;
                this.catItems = [];
                try {
                    const sep = searchUrl.includes('?') ? '&' : '?';
                    const url = `${searchUrl}${sep}q=${encodeURIComponent(q)}`;
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) {
                        throw new Error('bad');
                    }
                    const data = await res.json();
                    const rows = Array.isArray(data) ? data : [];
                    this.catItems = rows
                        .map((x) => (typeof x === 'string' ? x : String(x ?? '')))
                        .map((x) => x.trim())
                        .filter((x) => x !== '');
                    this.catNoHits = this.catItems.length === 0;
                } catch {
                    this.catItems = [];
                    this.catNoHits = false;
                } finally {
                    this.catLoading = false;
                    this.$nextTick(() => this.refreshCatPos(el));
                }
            },
            pickCategory(label) {
                clearTimeout(this.catBlurTimer);
                const s =
                    typeof label === 'string'
                        ? label.trim()
                        : String(label ?? '').trim();
                if (!s) {
                    return;
                }
                this.query = s;
                this.closeCatUi();
            },
            confirmTypedCategory(ev) {
                if (ev) {
                    ev.preventDefault();
                }
                clearTimeout(this.catBlurTimer);
                const s = String(this.query ?? '').trim();
                if (!s) {
                    return;
                }
                this.query = s;
                this.closeCatUi();
            },
        };
    });

    Alpine.data('stockInventoryDoc', (config) => ({
        searchUrl: typeof config.searchUrl === 'string' ? config.searchUrl : '',
        warehouseId: Number(config.warehouseId) || 0,
        warehouseFromId: Number(config.warehouseFromId) || 0,
        mode: config.mode === 'transfer' ? 'transfer' : 'single',
        qtyField: config.qtyField === 'quantity_counted' ? 'quantity_counted' : 'quantity',
        rows: [],
        extraUnitCost: Boolean(config.extraUnitCost),
        allowManualNewGood: Boolean(config.allowManualNewGood),
        enableHeaderSearch: Boolean(config.enableHeaderSearch),
        goodsQuickStoreUrl: typeof config.goodsQuickStoreUrl === 'string' ? config.goodsQuickStoreUrl : '',
        enableQuickNewGood: Boolean(config.enableQuickNewGood),
        newGoodIdPrefix: typeof config.newGoodIdPrefix === 'string' ? config.newGoodIdPrefix : 'gqm',
        stockQuickNewTarget: null,
        newGoodModalOpen: false,
        newGoodSaving: false,
        newGoodError: '',
        newGoodForm: {
            name: '',
            barcode: '',
            category: '',
            unit: 'шт.',
            quantity: '1',
            unit_price: '',
            wholesale_price: '',
            sale_price: '',
            oem: '',
            factory_number: '',
            min_stock: '',
        },
        selectedRow: null,
        moreOpen: false,
        headerGoodQuery: '',
        headerGoodOpen: false,
        headerGoodLoading: false,
        headerGoodItems: [],
        headerGoodBlurTimer: null,
        init() {
            const initial = Array.isArray(config.initialRows) ? config.initialRows : null;
            if (initial && initial.length > 0) {
                this.rows = initial.map((r) => ({
                    goodId:
                        r.good_id != null && Number(r.good_id) > 0 ? String(r.good_id) : '',
                    query: String(r.query ?? ''),
                    name: String(r.name ?? ''),
                    article: String(r.article ?? ''),
                    unit: String(r.unit ?? 'шт.'),
                    qty: String(r.qty ?? ''),
                    unitCost: String(r.unit_cost ?? ''),
                    sale_price: String(r.sale_price ?? ''),
                    stockQty: r.stock_qty != null && r.stock_qty !== '' ? r.stock_qty : null,
                    articleManual: String(r.article_manual ?? ''),
                    nameManual: String(r.name_manual ?? ''),
                    unitManual: String(r.unit_manual ?? 'шт.'),
                    results: [],
                    open: false,
                    loading: false,
                }));
                this.selectedRow = this.rows.length > 0 ? 0 : null;
                return;
            }
            const raw = config.rowCount;
            const n =
                raw === undefined || raw === null
                    ? 12
                    : Math.min(32, Math.max(0, Number(raw) || 0));
            for (let i = 0; i < n; i++) {
                this.rows.push(this.emptyRow());
            }
            this.selectedRow = this.rows.length > 0 ? 0 : null;
        },
        emptyRow() {
            return {
                goodId: '',
                query: '',
                name: '',
                article: '',
                unit: '',
                qty: '',
                unitCost: '',
                sale_price: '',
                stockQty: null,
                articleManual: '',
                nameManual: '',
                unitManual: 'шт.',
                results: [],
                open: false,
                loading: false,
            };
        },
        addLine() {
            if (this.rows.length >= 32) {
                return;
            }
            this.rows.push(this.emptyRow());
            this.selectedRow = this.rows.length - 1;
        },
        removeLine(i) {
            if (i < 0 || i >= this.rows.length) {
                return;
            }
            this.rows.splice(i, 1);
            if (this.rows.length === 0) {
                this.selectedRow = null;
            } else if (this.selectedRow !== null) {
                if (i < this.selectedRow) {
                    this.selectedRow--;
                } else if (i === this.selectedRow) {
                    this.selectedRow = Math.min(this.selectedRow, this.rows.length - 1);
                }
            }
        },
        moveUp() {
            if (this.selectedRow === null || this.selectedRow <= 0) {
                return;
            }
            const i = this.selectedRow;
            const row = this.rows.splice(i, 1)[0];
            this.rows.splice(i - 1, 0, row);
            this.selectedRow = i - 1;
        },
        moveDown() {
            if (this.selectedRow === null || this.selectedRow >= this.rows.length - 1) {
                return;
            }
            const i = this.selectedRow;
            const row = this.rows.splice(i, 1)[0];
            this.rows.splice(i + 1, 0, row);
            this.selectedRow = i + 1;
        },
        removeSelectedRow() {
            if (this.selectedRow === null) {
                return;
            }
            this.removeLine(this.selectedRow);
        },
        switchToManual(i) {
            const row = this.rows[i];
            row.goodId = '';
            row.query = '';
            row.name = '';
            row.article = '';
            row.unit = '';
            row.unitCost = '';
            row.sale_price = '';
            row.stockQty = null;
            row.results = [];
            row.open = false;
        },
        effectiveWarehouseId() {
            if (this.mode === 'transfer') {
                return this.warehouseFromId;
            }
            return this.warehouseId;
        },
        async searchRow(i) {
            const row = this.rows[i];
            const q = (row.query || '').trim();
            if (q.length < 2) {
                row.results = [];
                row.open = false;
                return;
            }
            const w = this.effectiveWarehouseId();
            row.loading = true;
            try {
                const url =
                    this.searchUrl +
                    '?q=' +
                    encodeURIComponent(q) +
                    '&warehouse_id=' +
                    encodeURIComponent(String(w > 0 ? w : 0)) +
                    '&exclude_services=1';
                const res = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                row.results = Array.isArray(data) ? data : [];
                row.open = q.length >= 2;
            } catch (e) {
                row.results = [];
                row.open = false;
            } finally {
                row.loading = false;
            }
        },
        pickGood(i, g) {
            const row = this.rows[i];
            row.goodId = String(g.id);
            row.query = (g.article_code || '') + ' — ' + (g.name || '');
            row.name = g.name || '';
            row.article = g.article_code || '';
            row.unit = g.unit || 'шт.';
            row.stockQty = g.stock_quantity != null && g.stock_quantity !== '' ? g.stock_quantity : null;
            const oCost = g.opening_unit_cost;
            const wPrice = g.wholesale_price;
            let unitCostStr = '';
            if (oCost != null && String(oCost).trim() !== '') {
                unitCostStr = String(oCost);
            } else if (wPrice != null && String(wPrice).trim() !== '') {
                unitCostStr = String(wPrice);
            }
            row.unitCost = unitCostStr;
            const rawSp = g.sale_price;
            if (rawSp != null && String(rawSp).trim() !== '') {
                row.sale_price = String(rawSp);
            } else {
                row.sale_price = '';
            }
            row.articleManual = '';
            row.nameManual = '';
            row.unitManual = 'шт.';
            row.results = [];
            row.open = false;
        },
        onHeaderGoodFocus() {
            if (!this.enableHeaderSearch) {
                return;
            }
            this.headerGoodOpen = true;
            if ((this.headerGoodQuery || '').trim().length >= 2) {
                this.fetchHeaderGoods();
            }
        },
        onHeaderGoodInput() {
            if (!this.enableHeaderSearch) {
                return;
            }
            this.fetchHeaderGoods();
        },
        onHeaderGoodBlur() {
            if (!this.enableHeaderSearch) {
                return;
            }
            clearTimeout(this.headerGoodBlurTimer);
            this.headerGoodBlurTimer = setTimeout(() => {
                this.headerGoodOpen = false;
            }, 200);
        },
        onHeaderGoodEnter(ev) {
            if (!this.enableHeaderSearch) {
                return;
            }
            if (ev.key !== 'Enter') {
                return;
            }
            ev.preventDefault();
            if (this.headerGoodItems.length === 1) {
                this.appendLineFromCatalogItem(this.headerGoodItems[0]);
            }
        },
        async fetchHeaderGoods() {
            if (!this.enableHeaderSearch) {
                return;
            }
            const q = (this.headerGoodQuery || '').trim();
            if (q.length < 2) {
                this.headerGoodItems = [];
                return;
            }
            const w = this.effectiveWarehouseId();
            this.headerGoodLoading = true;
            try {
                const url =
                    this.searchUrl +
                    '?q=' +
                    encodeURIComponent(q) +
                    '&warehouse_id=' +
                    encodeURIComponent(String(w > 0 ? w : 0)) +
                    '&exclude_services=1';
                const res = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                this.headerGoodItems = Array.isArray(data) ? data : [];
                this.headerGoodOpen = true;
            } catch (e) {
                this.headerGoodItems = [];
            } finally {
                this.headerGoodLoading = false;
            }
        },
        headerStockSoldOut(stockQty) {
            if (stockQty == null || stockQty === '') {
                return false;
            }
            const n = parseFloat(String(stockQty).replace(/\s/g, '').replace(',', '.'));
            return Number.isFinite(n) && n <= 0;
        },
        formatHeaderStockQty(v) {
            if (v == null || v === '') {
                return '';
            }
            const n = parseFloat(String(v).replace(/\s/g, '').replace(',', '.'));
            if (!Number.isFinite(n)) {
                return String(v);
            }
            return n.toLocaleString('ru-RU', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
        },
        formatHeaderUnitCost(v) {
            if (v == null || v === '') {
                return '';
            }
            const n = parseFloat(String(v).replace(/\s/g, '').replace(',', '.'));
            if (!Number.isFinite(n)) {
                return String(v);
            }
            return n.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' сом';
        },
        headerSuggestHasStockHint(item) {
            if (!item) {
                return false;
            }
            return (
                (item.stock_quantity != null && item.stock_quantity !== '') ||
                (item.opening_unit_cost != null && item.opening_unit_cost !== '')
            );
        },
        appendLineFromCatalogItem(item) {
            if (!this.enableHeaderSearch || !item) {
                return;
            }
            clearTimeout(this.headerGoodBlurTimer);

            const gid = item.id != null && item.id !== '' ? String(item.id) : '';
            const existingIdx =
                gid !== '' ? this.rows.findIndex((r) => String(r.goodId || '') === gid) : -1;

            if (existingIdx >= 0) {
                const row = this.rows[existingIdx];
                let cur = parseFloat(String(row.qty ?? '').replace(/\s/g, '').replace(',', '.'));
                if (!Number.isFinite(cur) || cur < 0) {
                    cur = 0;
                }
                row.qty = String(cur + 1);
                this.selectedRow = existingIdx;
            } else {
                let i = this.rows.findIndex((r) => !r.goodId);
                if (i < 0) {
                    if (this.rows.length >= 32) {
                        return;
                    }
                    this.addLine();
                    i = this.rows.length - 1;
                }
                this.pickGood(i, item);
                this.rows[i].qty = '1';
                this.selectedRow = i;
            }

            /* Поиск и список остаются: не очищаем headerGoodQuery / headerGoodItems */
            this.headerGoodOpen = true;
            this.$nextTick(() => {
                clearTimeout(this.headerGoodBlurTimer);
                document.getElementById('stock_header_good_q')?.focus?.();
                clearTimeout(this.headerGoodBlurTimer);
            });
        },
        resetNewGoodForm() {
            this.newGoodForm = {
                name: '',
                barcode: '',
                category: '',
                unit: 'шт.',
                quantity: '1',
                unit_price: '',
                wholesale_price: '',
                sale_price: '',
                oem: '',
                factory_number: '',
                min_stock: '',
            };
        },
        closeNewGoodModal() {
            this.newGoodModalOpen = false;
            this.newGoodError = '';
            this.newGoodSaving = false;
            this.stockQuickNewTarget = null;
        },
        openStockQuickNewGoodModal(presetQuery, target) {
            if (!this.enableQuickNewGood) {
                return;
            }
            this.resetNewGoodForm();
            const q = presetQuery != null ? String(presetQuery).trim() : '';
            if (q !== '') {
                this.newGoodForm.name = q;
            }
            this.newGoodError = '';
            if (target === 'header') {
                this.stockQuickNewTarget = { mode: 'header' };
            } else {
                const idx = Number(target);
                if (Number.isFinite(idx) && idx >= 0) {
                    this.stockQuickNewTarget = { mode: 'row', index: idx };
                } else {
                    this.stockQuickNewTarget = { mode: 'header' };
                }
            }
            this.newGoodModalOpen = true;
            this.headerGoodOpen = false;
            clearTimeout(this.headerGoodBlurTimer);
            const p = this.newGoodIdPrefix || 'gqm';
            this.$nextTick(() => {
                document.getElementById(`${p}_new_good_name`)?.focus();
            });
        },
        normalizeQuickStoreGoodItem(item) {
            if (!item || typeof item !== 'object') {
                return item;
            }
            const o = { ...item };
            if (o.stock_quantity == null || o.stock_quantity === '') {
                o.stock_quantity = '0';
            }
            return o;
        },
        async submitNewGoodQuickStore() {
            if (!this.enableQuickNewGood) {
                return;
            }
            const name = String(this.newGoodForm.name ?? '').trim();
            if (name === '') {
                this.newGoodError = 'Укажите наименование.';
                return;
            }
            const qtyRaw = String(this.newGoodForm.quantity ?? '').trim();
            if (qtyRaw === '') {
                this.newGoodError = 'Укажите количество.';
                return;
            }
            const qNum = parseFloat(qtyRaw.replace(/\s/g, '').replace(',', '.'));
            if (!Number.isFinite(qNum) || qNum <= 0) {
                this.newGoodError = 'Количество должно быть числом больше нуля.';
                return;
            }
            if (!this.goodsQuickStoreUrl) {
                this.newGoodError = 'Создание товара недоступно.';
                return;
            }
            const token =
                document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            this.newGoodSaving = true;
            this.newGoodError = '';
            /** @type {Record<string, string>} */
            const body = { name };
            const addStr = (k, v) => {
                const t = String(v ?? '').trim();
                if (t !== '') {
                    body[k] = t;
                }
            };
            addStr('barcode', this.newGoodForm.barcode);
            addStr('category', this.newGoodForm.category);
            addStr('unit', this.newGoodForm.unit);
            addStr('sale_price', this.newGoodForm.sale_price);
            addStr('wholesale_price', this.newGoodForm.wholesale_price);
            addStr('oem', this.newGoodForm.oem);
            addStr('factory_number', this.newGoodForm.factory_number);
            addStr('min_stock', this.newGoodForm.min_stock);
            if (!body.unit) {
                body.unit = 'шт.';
            }
            let url = this.goodsQuickStoreUrl;
            const w = this.effectiveWarehouseId();
            if (w > 0) {
                url += (url.includes('?') ? '&' : '?') + 'warehouse_id=' + encodeURIComponent(String(w));
            }
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify(body),
                });
                /** @type {Record<string, unknown>} */
                let data = {};
                try {
                    data = await res.json();
                } catch (_) {
                    data = {};
                }
                if (!res.ok) {
                    let msg =
                        typeof data.message === 'string'
                            ? data.message
                            : 'Не удалось сохранить товар.';
                    if (data.errors && typeof data.errors === 'object') {
                        const flat = Object.values(data.errors).flat();
                        const first = flat[0];
                        if (typeof first === 'string' && first.trim() !== '') {
                            msg = first;
                        }
                    }
                    this.newGoodError = msg;
                    return;
                }
                const item = this.normalizeQuickStoreGoodItem(data);
                let i = -1;
                const t = this.stockQuickNewTarget;
                if (t && t.mode === 'row' && typeof t.index === 'number') {
                    i = t.index;
                } else {
                    i = this.rows.findIndex((r) => !r.goodId);
                    if (i < 0) {
                        if (this.rows.length >= 32) {
                            this.newGoodError = 'Достигнут лимит строк (32).';
                            return;
                        }
                        this.addLine();
                        i = this.rows.length - 1;
                    }
                }
                if (i < 0 || i >= this.rows.length) {
                    this.newGoodError = 'Не удалось выбрать строку.';
                    return;
                }
                this.pickGood(i, item);
                this.rows[i].qty = qtyRaw.replace(/\s/g, '').replace(',', '.');
                const up = String(this.newGoodForm.unit_price ?? '').trim();
                if (up !== '') {
                    this.rows[i].unitCost = up.replace(/\s/g, '').replace(',', '.');
                }
                const sp = String(this.newGoodForm.sale_price ?? '').trim();
                if (sp !== '') {
                    this.rows[i].sale_price = sp.replace(/\s/g, '').replace(',', '.');
                }
                this.rows[i].open = false;
                this.selectedRow = i;
                this.closeNewGoodModal();
                this.headerGoodQuery = '';
                this.headerGoodItems = [];
                clearTimeout(this.headerGoodBlurTimer);
            } catch (_) {
                this.newGoodError = 'Ошибка сети. Повторите попытку.';
            } finally {
                this.newGoodSaving = false;
            }
        },
        clearRow(i) {
            const row = this.rows[i];
            row.goodId = '';
            row.query = '';
            row.name = '';
            row.article = '';
            row.unit = '';
            row.qty = '';
            row.unitCost = '';
            row.sale_price = '';
            row.stockQty = null;
            row.articleManual = '';
            row.nameManual = '';
            row.unitManual = 'шт.';
            row.results = [];
            row.open = false;
        },
        stockLabel(row) {
            if (!row || row.stock_quantity == null || row.stock_quantity === '') {
                return '—';
            }
            return String(row.stock_quantity);
        },
        rowStockDisplay(row) {
            if (!row.goodId) {
                return '—';
            }
            if (row.stockQty == null || row.stockQty === '') {
                return '—';
            }
            return String(row.stockQty);
        },
        rowUnitDisplay(row) {
            if (row.goodId) {
                return row.unit || '—';
            }
            return (row.unitManual || '').trim() || 'шт.';
        },
    }));

    Alpine.data('stockAuditDoc', (config) => ({
        searchUrl: typeof config.searchUrl === 'string' ? config.searchUrl : '',
        warehouseId: Number(config.warehouseId) || 0,
        formAction: typeof config.formAction === 'string' ? config.formAction : '',
        csrfToken: typeof config.csrfToken === 'string' ? config.csrfToken : '',
        isEdit: !!config.isEdit,
        scanQuery: '',
        scanResults: [],
        scanOpen: false,
        scanLoading: false,
        linesLoading: false,
        linesLoadError: '',
        auditSubmitting: false,
        auditPage: 1,
        auditPageSize: 100,
        rows: [],
        maxRows: 200000,
        mapInitialRow(r) {
            return {
                goodId: r.good_id != null && Number(r.good_id) > 0 ? String(r.good_id) : '',
                manual: false,
                query: '',
                name: String(r.name ?? ''),
                article: String(r.article ?? ''),
                unit: String(r.unit ?? 'шт.'),
                barcode: String(r.barcode ?? ''),
                stockQty: r.stock_qty != null && r.stock_qty !== '' ? r.stock_qty : null,
                qty: String(r.quantity_counted ?? ''),
                results: [],
                open: false,
                loading: false,
            };
        },
        auditTotalPages() {
            const size = Number(this.auditPageSize) || 100;
            if (this.rows.length === 0) {
                return 1;
            }
            return Math.max(1, Math.ceil(this.rows.length / size));
        },
        auditRowIndices() {
            const size = Number(this.auditPageSize) || 100;
            const tp = this.auditTotalPages();
            const page = Math.min(Math.max(1, this.auditPage), tp);
            const start = (page - 1) * size;
            const end = Math.min(start + size, this.rows.length);
            const indices = [];
            for (let i = start; i < end; i++) {
                indices.push(i);
            }
            return indices;
        },
        auditPageLabel() {
            const tp = this.auditTotalPages();
            const p = Math.min(Math.max(1, this.auditPage), tp);
            return `${p} / ${tp}`;
        },
        goToLastAuditPage() {
            this.auditPage = this.auditTotalPages();
        },
        goToPageForGlobalIndex(globalIdx) {
            const size = Number(this.auditPageSize) || 100;
            if (size <= 0) {
                return;
            }
            const idx = Number(globalIdx) || 0;
            this.auditPage = Math.max(1, Math.floor(idx / size) + 1);
        },
        async init() {
            const loadUrl = typeof config.linesLoadUrl === 'string' ? config.linesLoadUrl : '';
            if (loadUrl.length > 0) {
                this.linesLoading = true;
                this.linesLoadError = '';
                try {
                    const res = await fetch(loadUrl, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) {
                        throw new Error('load failed');
                    }
                    const data = await res.json();
                    const lines = Array.isArray(data.lines) ? data.lines : [];
                    this.rows = lines.map((r) => this.mapInitialRow(r));
                } catch {
                    this.linesLoadError = 'Не удалось загрузить строки документа. Обновите страницу.';
                } finally {
                    this.linesLoading = false;
                }
            } else {
                const initial = Array.isArray(config.initialRows) ? config.initialRows : null;
                if (initial && initial.length > 0) {
                    this.rows = initial.map((r) => this.mapInitialRow(r));
                }
            }
            this.$watch('warehouseId', (value, old) => {
                if (old == null) {
                    return;
                }
                if (Number(value) === Number(old)) {
                    return;
                }
                if (this.rows.length > 0) {
                    this.rows = [];
                }
                this.scanQuery = '';
                this.scanResults = [];
                this.scanOpen = false;
                this.auditPage = 1;
            });
            this.$watch('rows', () => {
                const tp = this.auditTotalPages();
                if (this.auditPage > tp) {
                    this.auditPage = Math.max(1, tp);
                }
            });
            this.$watch('auditPageSize', () => {
                const tp = this.auditTotalPages();
                if (this.auditPage > tp) {
                    this.auditPage = Math.max(1, tp);
                }
            });
            this.$nextTick(() => this.$refs.scanEl?.focus());
        },
        playAmbiguousBarcodeAlert() {
            try {
                const AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) {
                    return;
                }
                if (!this._auditAudioCtx) {
                    this._auditAudioCtx = new AC();
                }
                const ctx = this._auditAudioCtx;
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }
                const tone = (startOffsetSec, freqHz, durationSec, peakGain) => {
                    const o = ctx.createOscillator();
                    const g = ctx.createGain();
                    o.type = 'triangle';
                    o.frequency.value = freqHz;
                    o.connect(g);
                    g.connect(ctx.destination);
                    const t0 = ctx.currentTime + startOffsetSec;
                    g.gain.setValueAtTime(0.0001, t0);
                    g.gain.exponentialRampToValueAtTime(peakGain, t0 + 0.03);
                    g.gain.exponentialRampToValueAtTime(0.0001, t0 + durationSec);
                    o.start(t0);
                    o.stop(t0 + durationSec + 0.02);
                };
                /* Два заметных тона + короткое повторение — чтобы однозначно остановиться и выбрать позицию из списка */
                tone(0, 585, 0.32, 0.38);
                tone(0.36, 440, 0.32, 0.38);
                tone(0.74, 585, 0.22, 0.32);
            } catch (_) {
                /* ignore */
            }
        },
        incrementCountedQty(row) {
            const raw = (row.qty || '').trim().replace(',', '.');
            let n = 0;
            if (raw !== '') {
                const p = parseFloat(raw);
                if (!Number.isNaN(p)) {
                    n = p;
                }
            }
            const next = n + 1;
            if (Math.abs(next - Math.round(next)) < 1e-8) {
                row.qty = String(Math.round(next));
            } else {
                const r = Math.round(next * 10000) / 10000;
                row.qty = String(r);
            }
        },
        async handleScanSubmit() {
            const q = (this.scanQuery || '').trim();
            if (q.length < 2) {
                return;
            }
            this.scanOpen = false;
            this.scanResults = [];
            if (this.warehouseId <= 0) {
                window.alert('Сначала выберите склад — остатки подставляются по складу.');
                return;
            }
            this.scanLoading = true;
            try {
                const w = this.warehouseId;
                const base =
                    this.searchUrl +
                    '?q=' +
                    encodeURIComponent(q) +
                    '&warehouse_id=' +
                    encodeURIComponent(String(w)) +
                    '&exclude_services=1';
                let res = await fetch(base + '&barcode_exact=1', {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                let data = await res.json();
                if (!Array.isArray(data) || data.length === 0) {
                    res = await fetch(base, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    data = await res.json();
                }
                if (!Array.isArray(data) || data.length === 0) {
                    window.alert('Товар не найден. Проверьте штрихкод или выберите вручную.');
                    return;
                }
                if (data.length === 1) {
                    this.addFromGood(data[0]);
                    return;
                }
                this.playAmbiguousBarcodeAlert();
                this.scanResults = data;
                this.scanOpen = true;
            } catch {
                window.alert('Ошибка поиска.');
            } finally {
                this.scanLoading = false;
            }
        },
        pickScanResult(g) {
            this.scanOpen = false;
            this.scanResults = [];
            this.addFromGood(g);
        },
        focusQtyInput(rowIndex) {
            this.goToPageForGlobalIndex(rowIndex);
            const id = `audit-qty-${rowIndex}`;
            const tryFocus = () => {
                const el = document.getElementById(id);
                if (!el) {
                    return false;
                }
                if (this.$refs.scanEl) {
                    this.$refs.scanEl.blur();
                }
                el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                el.focus({ preventScroll: true });
                if (typeof el.select === 'function') {
                    el.select();
                }
                return true;
            };
            this.$nextTick(() => {
                this.$nextTick(() => {
                    if (tryFocus()) {
                        return;
                    }
                    requestAnimationFrame(() => {
                        if (tryFocus()) {
                            return;
                        }
                        setTimeout(() => {
                            if (tryFocus()) {
                                return;
                            }
                            setTimeout(tryFocus, 100);
                        }, 0);
                    });
                });
            });
        },
        focusScanInput() {
            this.scanOpen = false;
            this.scanResults = [];
            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    const el = this.$refs.scanEl;
                    if (!el) {
                        return;
                    }
                    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    el.focus();
                    if (typeof el.select === 'function') {
                        el.select();
                    }
                });
            });
        },
        addFromGood(g) {
            const id = String(g.id);
            const idx = this.rows.findIndex((r) => r.goodId === id);
            if (idx >= 0) {
                const row = this.rows[idx];
                this.incrementCountedQty(row);
                this.scanQuery = '';
                this.goToPageForGlobalIndex(idx);
                this.focusScanInput();
                return;
            }
            if (this.rows.length >= this.maxRows) {
                window.alert('Достигнут лимит позиций (' + this.maxRows + ').');
                return;
            }
            this.rows.push({
                goodId: id,
                manual: false,
                query: '',
                name: g.name || '',
                article: g.article_code || '',
                unit: g.unit || 'шт.',
                barcode: g.barcode || '',
                stockQty: g.stock_quantity != null && g.stock_quantity !== '' ? g.stock_quantity : null,
                qty: '1',
                results: [],
                open: false,
                loading: false,
            });
            this.scanQuery = '';
            this.goToLastAuditPage();
            this.focusScanInput();
        },
        addManualLine() {
            if (this.rows.length >= this.maxRows) {
                window.alert('Достигнут лимит позиций (' + this.maxRows + ').');
                return;
            }
            this.rows.push({
                goodId: '',
                manual: true,
                query: '',
                name: '',
                article: '',
                unit: '',
                barcode: '',
                stockQty: null,
                qty: '',
                results: [],
                open: false,
                loading: false,
            });
            this.goToLastAuditPage();
            this.$nextTick(() => {
                const i = this.rows.length - 1;
                const el = this.$el.querySelector(`[data-audit-search="${i}"]`);
                el?.focus();
            });
        },
        removeLine(i) {
            if (i < 0 || i >= this.rows.length) {
                return;
            }
            this.rows.splice(i, 1);
            const tp = this.auditTotalPages();
            if (this.auditPage > tp) {
                this.auditPage = Math.max(1, tp);
            }
        },
        onQtyEnter(e) {
            e.preventDefault();
            this.focusScanInput();
        },
        onQtyTab(e) {
            if (e.shiftKey) {
                return;
            }
            e.preventDefault();
            this.focusScanInput();
        },
        async searchRow(i) {
            const row = this.rows[i];
            const q = (row.query || '').trim();
            if (q.length < 2) {
                row.results = [];
                row.open = false;
                return;
            }
            const w = this.warehouseId;
            row.loading = true;
            try {
                const url =
                    this.searchUrl +
                    '?q=' +
                    encodeURIComponent(q) +
                    '&warehouse_id=' +
                    encodeURIComponent(String(w > 0 ? w : 0)) +
                    '&exclude_services=1';
                const res = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                row.results = Array.isArray(data) ? data : [];
                row.open = row.results.length > 0;
            } catch {
                row.results = [];
                row.open = false;
            } finally {
                row.loading = false;
            }
        },
        pickGood(i, g) {
            const row = this.rows[i];
            row.goodId = String(g.id);
            row.manual = false;
            row.query = (g.article_code || '') + ' — ' + (g.name || '');
            row.name = g.name || '';
            row.article = g.article_code || '';
            row.unit = g.unit || 'шт.';
            row.barcode = g.barcode || '';
            row.stockQty = g.stock_quantity != null && g.stock_quantity !== '' ? g.stock_quantity : null;
            row.results = [];
            row.open = false;
            this.focusQtyInput(i);
        },
        rowStockDisplay(row) {
            if (!row.goodId) {
                return '—';
            }
            if (row.stockQty == null || row.stockQty === '') {
                return '—';
            }
            return String(row.stockQty);
        },
        rowUnitDisplay(row) {
            if (row.goodId) {
                return row.unit || '—';
            }
            return '—';
        },
        async submitStockAudit(commit) {
            if (this.auditSubmitting) {
                return;
            }
            const has = this.rows.some((r) => {
                if (!r.goodId) {
                    return false;
                }
                const raw = (r.qty || '').trim().replace(',', '.');
                if (raw === '') {
                    return false;
                }
                const n = parseFloat(raw);
                return !Number.isNaN(n) && n >= 0;
            });
            if (!has) {
                window.alert('Добавьте хотя бы одну строку: выберите товар и укажите фактическое количество.');
                return;
            }
            const lines = [];
            for (const r of this.rows) {
                if (!r.goodId) {
                    continue;
                }
                const raw = (r.qty || '').trim().replace(',', '.');
                if (raw === '') {
                    continue;
                }
                lines.push({
                    good_id: String(r.goodId),
                    quantity_counted: raw,
                });
            }
            if (lines.length === 0) {
                window.alert('Укажите фактическое количество по выбранным товарам.');
                return;
            }
            if (!this.formAction || !this.csrfToken) {
                window.alert('Ошибка формы. Обновите страницу.');
                return;
            }
            const whEl = document.getElementById('au_wh');
            const dateEl = document.getElementById('au_date');
            const noteEl = document.getElementById('au_note');
            const payload = {
                _token: this.csrfToken,
                warehouse_id: whEl ? whEl.value : '',
                document_date: dateEl ? dateEl.value : '',
                note: noteEl ? noteEl.value : '',
                commit,
                lines,
            };
            if (this.isEdit) {
                payload._method = 'PUT';
            }
            this.auditSubmitting = true;
            try {
                // follow: при redirect:manual ответ на 302 часто opaqueredirect → status 0 (ложная ошибка).
                const res = await fetch(this.formAction, {
                    method: 'POST',
                    credentials: 'same-origin',
                    redirect: 'follow',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify(payload),
                });
                if (res.status === 422) {
                    let msg = 'Проверьте данные.';
                    try {
                        const j = await res.json();
                        if (j.message) {
                            msg = j.message;
                        } else if (j.errors) {
                            msg = Object.values(j.errors)
                                .flat()
                                .join('\n');
                        }
                    } catch (_) {
                        /* ignore */
                    }
                    window.alert(msg);
                    return;
                }
                if (res.status === 419) {
                    window.alert('Сессия устарела. Обновите страницу.');
                    return;
                }
                if (res.ok) {
                    window.location.href = res.url || this.formAction;
                    return;
                }
                window.alert('Не удалось сохранить документ (код ' + res.status + ').');
            } catch {
                window.alert('Ошибка сети.');
            } finally {
                this.auditSubmitting = false;
            }
        },
    }));

    Alpine.data('journalSaleGoodFilter', (rawConfig) => {
        const cfg = rawConfig && typeof rawConfig === 'object' ? rawConfig : {};
        const goodsSearchUrl = typeof cfg.goodsSearchUrl === 'string' ? cfg.goodsSearchUrl : '';
        const warehouseId =
            typeof cfg.warehouseId === 'number' && !Number.isNaN(cfg.warehouseId)
                ? cfg.warehouseId
                : parseInt(String(cfg.warehouseId ?? '0'), 10) || 0;
        const initialGoodId = parseInt(String(cfg.initialGoodId ?? '0'), 10) || 0;
        const initialSummary = typeof cfg.initialSummary === 'string' ? cfg.initialSummary : '';
        const formSelector =
            typeof cfg.formSelector === 'string' && cfg.formSelector !== ''
                ? cfg.formSelector
                : '[data-journal-filter-form]';
        return {
            query: '',
            results: [],
            open: false,
            loading: false,
            suggestTimer: null,
            blurTimer: null,
            selectedGoodId: initialGoodId > 0 ? initialGoodId : 0,
            selectedSummary: initialSummary,
            goodsSearchUrl,
            warehouseId,
            formSelector,
            init() {
                this.$watch('query', (q) => {
                    clearTimeout(this.suggestTimer);
                    const term = (q || '').trim();
                    if (term.length < 2) {
                        this.results = [];
                        this.open = false;
                        return;
                    }
                    this.suggestTimer = setTimeout(() => {
                        this.runSearch(term);
                    }, 280);
                });
            },
            async runSearch(term) {
                if (!this.goodsSearchUrl) return;
                this.loading = true;
                try {
                    let url = this.goodsSearchUrl + '?q=' + encodeURIComponent(term);
                    if (this.warehouseId > 0) {
                        url += '&warehouse_id=' + encodeURIComponent(String(this.warehouseId));
                    }
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    this.results = Array.isArray(data) ? data : [];
                    this.open = this.results.length > 0;
                } catch {
                    this.results = [];
                    this.open = false;
                } finally {
                    this.loading = false;
                }
            },
            scheduleBlurClose() {
                clearTimeout(this.blurTimer);
                this.blurTimer = setTimeout(() => {
                    this.open = false;
                }, 180);
            },
            focusSuggest() {
                clearTimeout(this.blurTimer);
            },
            pick(row) {
                if (!row || row.id == null) return;
                const form = document.querySelector(this.formSelector);
                if (!form) return;
                let input = form.querySelector('input[name="good_id"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'good_id';
                    form.appendChild(input);
                }
                input.value = String(row.id);
                form.submit();
            },
            clear() {
                const form = document.querySelector(this.formSelector);
                if (!form) return;
                const input = form.querySelector('input[name="good_id"]');
                if (input) input.value = '';
                this.query = '';
                this.selectedGoodId = 0;
                this.selectedSummary = '';
                this.results = [];
                this.open = false;
                form.submit();
            },
            itemLabel(row) {
                if (!row) return '—';
                const code = row.article_code != null ? String(row.article_code).trim() : '';
                const name = row.name != null ? String(row.name).trim() : '';
                if (code && name) return code + ' · ' + name;
                return name || code || '—';
            },
        };
    });

    Alpine.data('retailSalesHistoryPage', (rawCfg) => {
        const cfg = rawCfg && typeof rawCfg === 'object' ? rawCfg : {};
        const physicalBase = typeof cfg.physicalBase === 'string' ? cfg.physicalBase.replace(/\/$/, '') : '';
        const csrf = typeof cfg.csrf === 'string' ? cfg.csrf : '';
        const hist = cfg.historyParams && typeof cfg.historyParams === 'object' ? cfg.historyParams : {};

        return {
            returnOpen: false,
            returnLoading: false,
            returnSubmitting: false,
            returnErr: '',
            activeSaleId: null,
            returnLines: [],
            returnAccounts: [],
            returnAccountId: '',
            returnDocDate: new Date().toISOString().slice(0, 10),
            qty: {},
            formatMoney(n) {
                const x = Number(n);
                if (!Number.isFinite(x)) return '—';
                return new Intl.NumberFormat('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(x);
            },
            formatQty(s) {
                const n = parseFloat(String(s ?? '').replace(/\s/g, '').replace(',', '.'));
                if (!Number.isFinite(n)) return '—';
                return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 4 }).format(n);
            },
            parseNum(s) {
                if (s == null || s === '') return NaN;
                return parseFloat(String(s).replace(/\s/g, '').replace(',', '.'));
            },
            lineSubtotal(row) {
                const q = this.parseNum(this.qty[row.id] ?? '');
                const p = this.parseNum(row.unit_price);
                if (!Number.isFinite(q) || q <= 0 || !Number.isFinite(p)) return 0;
                return Math.round(q * p * 100) / 100;
            },
            totalRefund() {
                let t = 0;
                for (const row of this.returnLines || []) {
                    t += this.lineSubtotal(row);
                }
                return Math.round(t * 100) / 100;
            },
            closeReturnModal() {
                this.returnOpen = false;
                this.returnErr = '';
            },
            async openReturn(saleId) {
                if (!physicalBase) return;
                this.activeSaleId = saleId;
                this.returnErr = '';
                this.returnLoading = true;
                this.returnOpen = true;
                this.qty = {};
                this.returnLines = [];
                this.returnAccounts = [];
                this.returnAccountId = '';
                try {
                    const url = physicalBase + '/' + saleId + '/return-data';
                    const res = await fetch(url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.returnErr = data.message || 'Не удалось загрузить чек.';
                        return;
                    }
                    this.returnLines = Array.isArray(data.lines) ? data.lines : [];
                    this.returnAccounts = Array.isArray(data.accounts) ? data.accounts : [];
                    const def = data.defaultAccountId != null ? parseInt(String(data.defaultAccountId), 10) : 0;
                    this.returnAccountId = def > 0 ? String(def) : this.returnAccounts[0] ? String(this.returnAccounts[0].id) : '';
                    if (data.sale && data.sale.document_date) {
                        this.returnDocDate = String(data.sale.document_date).slice(0, 10);
                    }
                    const next = {};
                    for (const row of this.returnLines) {
                        next[row.id] = '0';
                    }
                    this.qty = next;
                    const nothingToReturn = this.returnLines.every((r) => {
                        const av = this.parseNum(r.quantity_available);
                        return !Number.isFinite(av) || av <= 0;
                    });
                    if (nothingToReturn && this.returnLines.length > 0) {
                        this.returnErr = 'По этому чеку уже всё возвращено или нет доступного количества.';
                    }
                } catch (e) {
                    this.returnErr = 'Ошибка сети.';
                } finally {
                    this.returnLoading = false;
                }
            },
            async submitReturn() {
                if (!physicalBase || !this.activeSaleId || !csrf) return;
                const rows = [];
                for (const row of this.returnLines) {
                    const raw = (this.qty[row.id] ?? '').toString().trim();
                    if (raw === '') continue;
                    const q = this.parseNum(raw.replace(',', '.'));
                    if (!Number.isFinite(q) || q <= 0) continue;
                    rows.push({ retail_sale_line_id: row.id, quantity: String(raw).replace(',', '.') });
                }
                if (rows.length === 0) {
                    this.returnErr = 'Укажите количество хотя бы по одной позиции.';
                    return;
                }
                const acc = parseInt(String(this.returnAccountId), 10);
                if (!acc) {
                    this.returnErr = 'Выберите счёт, с которого вернёте деньги покупателю.';
                    return;
                }
                this.returnSubmitting = true;
                this.returnErr = '';
                const body = new URLSearchParams();
                body.append('_token', csrf);
                body.append('document_date', this.returnDocDate);
                body.append('organization_bank_account_id', String(acc));
                rows.forEach((r, i) => {
                    body.append(`lines[${i}][retail_sale_line_id]`, String(r.retail_sale_line_id));
                    body.append(`lines[${i}][quantity]`, r.quantity);
                });
                if (hist.warehouse_id != null && hist.warehouse_id !== '') body.append('return_warehouse_id', String(hist.warehouse_id));
                if (hist.limit != null && hist.limit !== '') body.append('return_limit', String(hist.limit));
                if (hist.date_from) body.append('return_date_from', String(hist.date_from));
                if (hist.date_to) body.append('return_date_to', String(hist.date_to));
                if (hist.good_id != null && hist.good_id !== '') body.append('return_good_id', String(hist.good_id));
                try {
                    const url = physicalBase + '/' + this.activeSaleId + '/return';
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'text/html,application/xhtml+xml',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: body.toString(),
                        credentials: 'same-origin',
                        redirect: 'manual',
                    });
                    if (res.status === 302 || res.status === 301 || res.status === 303 || res.status === 307 || res.status === 308) {
                        const loc = res.headers.get('Location');
                        window.location.href = loc || window.location.href;
                        return;
                    }
                    if (res.ok) {
                        window.location.reload();
                        return;
                    }
                    if (res.status === 0) {
                        this.returnErr =
                            'Ответ сервера недоступен (код 0). Обычно это несовпадение адреса: откройте тот же URL, что в .env (APP_URL), например только localhost или только 127.0.0.1. Либо сбой сети — повторите попытку.';
                        return;
                    }
                    this.returnErr = 'Не удалось провести возврат (HTTP ' + res.status + ').';
                } catch (e) {
                    this.returnErr = 'Ошибка сети.';
                } finally {
                    this.returnSubmitting = false;
                }
            },
        };
    });

    /** Модальное окно детализации строки отчёта «Движение товаров» (readonly). */
    Alpine.data('goodsMovementPeriodModal', (initialMeta) => {
        const metaDefaults = { from: '', to: '', warehouse: '', warehouseId: 0, ledgerUrl: '' };
        const merged =
            initialMeta != null && typeof initialMeta === 'object'
                ? { ...metaDefaults, ...initialMeta }
                : metaDefaults;

        function fmtQtyVal(v) {
            const n = Number(v);
            if (!Number.isFinite(n)) {
                return '—';
            }

            return new Intl.NumberFormat('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
                useGrouping: true,
            })
                .format(n)
                .replace(/\u00a0/g, ' ')
                .replace(/\u202f/g, ' ');
        }

        return {
            meta: merged,
            modalOpen: false,
            row: null,
            ledgerRows: [],
            ledgerLoading: false,
            ledgerError: '',
            _ledgerAbort: null,
            fmtQty(val) {
                return fmtQtyVal(val);
            },
            fmtLedgerQty(n, dir) {
                const x = Number(n);
                if (!Number.isFinite(x)) {
                    return '—';
                }
                const s = x.toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
                if (dir === 'in') {
                    return '+' + s;
                }
                if (dir === 'out') {
                    return '−' + s;
                }
                return s;
            },
            async openRow(r) {
                if (!r || typeof r !== 'object') {
                    return;
                }
                this.row = r;
                this.modalOpen = true;
                this.ledgerRows = [];
                this.ledgerError = '';
                try {
                    document.body.style.overflow = 'hidden';
                } catch (e) {}

                const gid = Number(r.good_id);
                const baseUrl = typeof merged.ledgerUrl === 'string' ? merged.ledgerUrl.trim() : '';
                if (!Number.isFinite(gid) || gid <= 0 || baseUrl === '') {
                    return;
                }

                if (this._ledgerAbort) {
                    try {
                        this._ledgerAbort.abort();
                    } catch (e) {}
                }
                const ac = new AbortController();
                this._ledgerAbort = ac;
                this.ledgerLoading = true;

                try {
                    const u = new URL(baseUrl, window.location.origin);
                    u.searchParams.set('good_id', String(Math.trunc(gid)));
                    if (merged.from) {
                        u.searchParams.set('from', String(merged.from));
                    }
                    if (merged.to) {
                        u.searchParams.set('to', String(merged.to));
                    }
                    const whId =
                        merged.warehouseId != null && Number.isFinite(Number(merged.warehouseId))
                            ? Math.max(0, Math.trunc(Number(merged.warehouseId)))
                            : 0;
                    u.searchParams.set('warehouse_id', String(whId));

                    const res = await fetch(u.toString(), {
                        signal: ac.signal,
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = res.ok ? await res.json().catch(() => ({})) : {};
                    const list = Array.isArray(data.rows) ? data.rows : [];
                    if (!res.ok) {
                        this.ledgerError =
                            typeof data.message === 'string' ? data.message : 'Не удалось загрузить журнал движений.';
                        return;
                    }
                    this.ledgerRows = list;
                } catch (e) {
                    if (e != null && e.name === 'AbortError') {
                        return;
                    }
                    this.ledgerError = 'Ошибка сети при загрузке журнала.';
                } finally {
                    if (this._ledgerAbort === ac) {
                        this._ledgerAbort = null;
                        this.ledgerLoading = false;
                    }
                }
            },
            closeModal() {
                this.modalOpen = false;
                this.row = null;
                this.ledgerRows = [];
                this.ledgerError = '';
                this.ledgerLoading = false;
                if (this._ledgerAbort) {
                    try {
                        this._ledgerAbort.abort();
                    } catch (e) {}
                    this._ledgerAbort = null;
                }
                try {
                    document.body.style.overflow = '';
                } catch (e) {}
            },
        };
    });
});

Alpine.start();
