import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
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
            category: '',
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
            category: r.category ?? '',
            unit: r.unit ?? 'шт.',
            quantity: r.quantity ?? '',
            unit_price: r.unit_price ?? '',
            sale_price: r.sale_price ?? '',
        }));

        const u = urls && typeof urls === 'object' ? urls : {};
        const goodsSearchUrl = typeof u.goodsSearch === 'string' ? u.goodsSearch : '';
        const counterpartySearchUrl = typeof u.counterpartySearch === 'string' ? u.counterpartySearch : '';
        const counterpartyQuickUrl = typeof u.counterpartyQuick === 'string' ? u.counterpartyQuick : '';
        const categorySearchUrl = typeof u.categoriesSearch === 'string' ? u.categoriesSearch : '';
        const branchName = typeof init.branchName === 'string' ? init.branchName : '';
        const warehouseName = typeof init.warehouseName === 'string' ? init.warehouseName : '';

        return {
        lines,
        selectedRow: 0,
        moreOpen: false,
        warehouseId,
        supplierName: typeof initialSupplierName === 'string' ? initialSupplierName : '',
        goodsSearchUrl,
        counterpartySearchUrl,
        counterpartyQuickUrl,
        categorySearchUrl,
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
        categorySuggestRow: null,
        categorySuggestItems: [],
        categorySuggestLoading: false,
        categorySuggestNoHits: false,
        categorySuggestTimer: null,
        categorySuggestBlurTimer: null,
        categorySuggestPos: { top: 0, left: 0, width: 260 },
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
        refreshCategorySuggestPosition(el) {
            if (!el || !el.getBoundingClientRect) return;
            const r = el.getBoundingClientRect();
            const w = Math.max(r.width, 240);
            let left = r.left;
            if (left + w > window.innerWidth - 8) left = Math.max(8, window.innerWidth - w - 8);
            this.categorySuggestPos = { top: r.bottom + 4, left, width: w };
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
        categorySuggestClose() {
            clearTimeout(this.categorySuggestTimer);
            this.categorySuggestItems = [];
            this.categorySuggestRow = null;
            this.categorySuggestLoading = false;
            this.categorySuggestNoHits = false;
        },
        closeAllSuggests() {
            this.nameSuggestClose();
            this.counterpartySuggestClose();
            this.categorySuggestClose();
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
            this.counterpartySuggestClose();
            this.categorySuggestClose();
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
        onCategoryFocus(index, event) {
            clearTimeout(this.categorySuggestBlurTimer);
            this.nameSuggestClose();
            this.counterpartySuggestClose();
            this.selectedRow = index;
            const el = event.target;
            this.refreshCategorySuggestPosition(el);
            clearTimeout(this.categorySuggestTimer);
            this.categorySuggestRow = index;
            this.runCategoryFetch(index, el);
        },
        onCategoryBlur() {
            this.categorySuggestBlurTimer = setTimeout(() => this.categorySuggestClose(), 180);
        },
        onCategoryInput(index, event) {
            const el = event.target;
            this.refreshCategorySuggestPosition(el);
            clearTimeout(this.categorySuggestTimer);
            this.nameSuggestClose();
            this.counterpartySuggestClose();
            this.categorySuggestRow = index;
            this.categorySuggestTimer = setTimeout(() => this.runCategoryFetch(index, el), 250);
        },
        async runCategoryFetch(index, el) {
            this.categorySuggestLoading = true;
            this.categorySuggestNoHits = false;
            this.categorySuggestItems = [];
            if (!this.categorySearchUrl) {
                this.categorySuggestItems = [];
                this.categorySuggestLoading = false;
                this.categorySuggestNoHits = true;
                return;
            }
            const q = (el.value || '').trim();
            try {
                const url = this.categorySearchUrl + '?q=' + encodeURIComponent(q);
                const res = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('search');
                const data = await res.json();
                if (this.categorySuggestRow !== index) return;
                this.categorySuggestItems = Array.isArray(data) ? data.filter((x) => typeof x === 'string' && x.trim() !== '') : [];
                this.categorySuggestNoHits = this.categorySuggestItems.length === 0;
            } catch (e) {
                this.categorySuggestItems = [];
                this.categorySuggestNoHits = false;
            } finally {
                this.categorySuggestLoading = false;
                this.$nextTick(() => this.refreshCategorySuggestPosition(el));
            }
        },
        pickCategoryFromSuggest(label) {
            clearTimeout(this.categorySuggestBlurTimer);
            const index = this.categorySuggestRow;
            if (index === null || label == null || label === '') return;
            const row = this.lines[index];
            if (!row) return;
            row.category = String(label);
            this.categorySuggestClose();
        },
        pickGoodFromSuggest(item) {
            clearTimeout(this.nameSuggestBlurTimer);
            const index = this.nameSuggestRow;
            if (index === null || !item) return;
            const row = this.lines[index];
            if (!row) return;
            row.name = item.name || '';
            row.article_code = item.article_code || '';
            row.barcode = item.barcode != null && item.barcode !== '' ? String(item.barcode) : '';
            row.category = item.category != null && item.category !== '' ? String(item.category) : '';
            row.unit = item.unit && String(item.unit).trim() ? String(item.unit).trim() : 'шт.';
            if (item.opening_unit_cost != null && item.opening_unit_cost !== '') {
                row.unit_price = String(item.opening_unit_cost);
            }
            if (item.sale_price != null && item.sale_price !== '') {
                row.sale_price = String(item.sale_price);
            }
            this.nameSuggestClose();
        },
        onSupplierFocus(event) {
            clearTimeout(this.cpSuggestBlurTimer);
            this.nameSuggestClose();
            this.categorySuggestClose();
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
            this.categorySuggestClose();
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
            const el = document.getElementById('supplier_name');
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
        genArticlesEmptyOnly() {
            if (typeof window.obGenArticle !== 'function') {
                window.alert('Генератор артикулов не загружен. Обновите страницу.');
                return;
            }
            const used = new Set(this.lines.map((r) => (r.article_code || '').trim()).filter(Boolean));
            let n = 0;
            this.lines.forEach((row) => {
                if ((row.article_code || '').trim() !== '') return;
                let code = '';
                for (let k = 0; k < 60; k++) {
                    code = window.obGenArticle();
                    if (!used.has(code)) break;
                }
                row.article_code = code;
                used.add(code);
                n++;
            });
            if (n === 0) window.alert('Нет строк с пустым артикулом.');
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
            const dateEl = document.getElementById('document_date');
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
            article_code: '',
            name: '',
            barcode: '',
            category: '',
            unit: 'шт.',
            quantity: '',
            unit_price: '',
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
        }));

        const u = urls && typeof urls === 'object' ? urls : {};
        const goodsSearchUrl = typeof u.goodsSearch === 'string' ? u.goodsSearch : '';
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
            lineSum(row) {
                const q = parseFloat(String(row.quantity ?? '').replace(/\s/g, '').replace(',', '.'));
                const p = parseFloat(String(row.unit_price ?? '').replace(/\s/g, '').replace(',', '.'));
                if (!Number.isFinite(q) || !Number.isFinite(p)) return '';
                return (q * p).toFixed(2);
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
            closeAllSuggests() {
                this.nameSuggestClose();
                this.counterpartySuggestClose();
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
            onBuyerFocus(event) {
                clearTimeout(this.cpSuggestBlurTimer);
                this.nameSuggestClose();
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
                const dateEl = document.getElementById('document_date');
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
            orgSuffixSingle() {
                return this.printOrgId != null && this.printOrgId !== ''
                    ? '?organization_id=' + encodeURIComponent(String(this.printOrgId))
                    : '';
            },
            orgSuffixMerged() {
                return this.printOrgId != null && this.printOrgId !== ''
                    ? '&organization_id=' + encodeURIComponent(String(this.printOrgId))
                    : '';
            },
            openPrint() {
                if (this.selectedIds.length === 0 || !this.invoiceBase) return;
                if (this.selectedIds.length === 1) {
                    window.open(
                        this.invoiceBase +
                            '/' +
                            String(this.selectedIds[0]) +
                            '/print' +
                            this.orgSuffixSingle(),
                        '_blank'
                    );
                    return;
                }
                if (!this.mergedPrint) return;
                const q = new URLSearchParams();
                this.selectedIds.forEach((id) => q.append('sale_ids[]', String(id)));
                window.open(this.mergedPrint + '?' + q.toString() + this.orgSuffixMerged(), '_blank');
            },
            openPdf() {
                if (this.selectedIds.length === 0 || !this.invoiceBase) return;
                if (this.selectedIds.length === 1) {
                    window.location.href =
                        this.invoiceBase +
                        '/' +
                        String(this.selectedIds[0]) +
                        '/pdf' +
                        this.orgSuffixSingle();
                    return;
                }
                if (!this.mergedPdf) return;
                const q = new URLSearchParams();
                this.selectedIds.forEach((id) => q.append('sale_ids[]', String(id)));
                window.location.href = this.mergedPdf + '?' + q.toString() + this.orgSuffixMerged();
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
        }));

        const u = urls && typeof urls === 'object' ? urls : {};
        const goodsSearchUrl = typeof u.goodsSearch === 'string' ? u.goodsSearch : '';
        const counterpartySearchUrl = typeof u.counterpartySearch === 'string' ? u.counterpartySearch : '';
        const counterpartyQuickUrl = typeof u.counterpartyQuick === 'string' ? u.counterpartyQuick : '';
        const branchName = typeof init.branchName === 'string' ? init.branchName : '';
        const warehouseName = typeof init.warehouseName === 'string' ? init.warehouseName : '';

        return {
            lines,
            selectedRow: 0,
            moreOpen: false,
            warehouseId,
            supplierName: typeof initialSupplierName === 'string' ? initialSupplierName : '',
            goodsSearchUrl,
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
            goodsSuggestHasReturnHint(item) {
                if (!item || typeof item !== 'object') return false;
                const q = item.stock_quantity;
                const c = item.opening_unit_cost;
                const s = item.sale_price;
                return (q != null && q !== '') || (c != null && c !== '') || (s != null && s !== '');
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
                this.nameSuggestClose();
                this.counterpartySuggestClose();
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
                const el = document.getElementById('supplier_name');
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
                const dateEl = document.getElementById('document_date');
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

    Alpine.data('retailPosForm', () => {
        const c =
            typeof window !== 'undefined' && window.__retailPosInit && typeof window.__retailPosInit === 'object'
                ? window.__retailPosInit
                : {};
        const goodsSearchUrl = typeof c.goodsSearchUrl === 'string' ? c.goodsSearchUrl : '';
        const warehouseId =
            typeof c.warehouseId === 'number' && !Number.isNaN(c.warehouseId)
                ? c.warehouseId
                : parseInt(String(c.warehouseId ?? '0'), 10) || 0;
        const editMode = c.editMode === true;
        const serviceRequestMode = c.serviceRequestMode === true;
        const initialCartRaw = Array.isArray(c.initialCart) ? c.initialCart : [];

        return {
            query: '',
            searchOpen: false,
            loading: false,
            results: [],
            cart: [],
            clientPaid: '',
            goodsSearchUrl,
            warehouseId,
            editMode,
            serviceRequestMode,
            init() {
                if (initialCartRaw.length > 0) {
                    this.cart = initialCartRaw.map((row) => ({
                        good_id: row.good_id,
                        article_code: row.article_code || '',
                        name: row.name || '',
                        quantity: String(row.quantity ?? '1'),
                        unit_price:
                            row.unit_price != null && row.unit_price !== '' ? String(row.unit_price) : '',
                        stock_quantity: row.stock_quantity != null ? row.stock_quantity : null,
                        is_service: row.is_service === true || row.is_service === 1,
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
                            this.addProduct(r);
                            return;
                        }
                    }
                } catch (e) {
                    this.results = [];
                } finally {
                    this.loading = false;
                }
            },
            addProduct(row) {
                if (!row || row.id == null) return;
                const id = row.id;
                const idx = this.cart.findIndex((c) => c.good_id === id);
                const isService = row.is_service === true || row.is_service === 1;
                const stock =
                    isService
                        ? null
                        : row.stock_quantity != null && row.stock_quantity !== ''
                          ? this.parseMoney(row.stock_quantity)
                          : null;
                if (idx >= 0) {
                    let q = this.parseMoney(this.cart[idx].quantity);
                    if (!Number.isFinite(q)) q = 0;
                    q += 1;
                    const lineIsService =
                        this.cart[idx].is_service === true ||
                        this.cart[idx].is_service === 1 ||
                        isService;
                    if (!lineIsService && stock != null && Number.isFinite(stock) && q > stock + 1e-9) q = stock;
                    this.cart[idx].quantity = String(q);
                } else {
                    let price = '';
                    if (row.sale_price != null && row.sale_price !== '') price = String(row.sale_price);
                    this.cart.push({
                        good_id: id,
                        article_code: row.article_code || '',
                        name: row.name || '',
                        quantity: '1',
                        unit_price: price,
                        stock_quantity: isService ? null : row.stock_quantity,
                        is_service: isService,
                    });
                }
                this.searchOpen = false;
                this.query = '';
                this.results = [];
            },
            removeLine(i) {
                this.cart.splice(i, 1);
            },
            incQty(i) {
                const line = this.cart[i];
                if (!line) return;
                let q = this.parseMoney(line.quantity);
                if (!Number.isFinite(q)) q = 0;
                const stock =
                    line.is_service === true || line.is_service === 1
                        ? null
                        : line.stock_quantity != null && line.stock_quantity !== ''
                          ? this.parseMoney(line.stock_quantity)
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
        const quickKind = cfg.quickKind === 'buyer' ? 'buyer' : 'supplier';
        const allowKinds =
            quickKind === 'buyer'
                ? new Set(['buyer', 'other'])
                : new Set(['supplier', 'other']);
        const initialId = parseInt(String(cfg.initialId ?? '0'), 10) || 0;
        const initialLabel = typeof cfg.initialLabel === 'string' ? cfg.initialLabel : '';
        const quickTitle = quickKind === 'buyer' ? 'Новый клиент' : 'Новый поставщик';
        const quickBtnAdd = quickKind === 'buyer' ? 'Добавить клиента…' : 'Добавить поставщика…';

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
                if (!this.counterpartyId || this.counterpartyId <= 0) {
                    e.preventDefault();
                    window.alert(
                        'Укажите контрагента: введите от 2 букв и выберите строку в списке или создайте нового контрагента.',
                    );
                }
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
                    stockQty: r.stock_qty != null && r.stock_qty !== '' ? r.stock_qty : null,
                    articleManual: String(r.article_manual ?? ''),
                    nameManual: String(r.name_manual ?? ''),
                    unitManual: String(r.unit_manual ?? 'шт.'),
                    results: [],
                    open: false,
                    loading: false,
                }));
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
        },
        removeLine(i) {
            if (i < 0 || i >= this.rows.length) {
                return;
            }
            this.rows.splice(i, 1);
        },
        switchToManual(i) {
            const row = this.rows[i];
            row.goodId = '';
            row.query = '';
            row.name = '';
            row.article = '';
            row.unit = '';
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
                row.open = row.results.length > 0;
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
            row.articleManual = '';
            row.nameManual = '';
            row.unitManual = 'шт.';
            row.results = [];
            row.open = false;
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
        scanQuery: '',
        scanResults: [],
        scanOpen: false,
        scanLoading: false,
        rows: [],
        maxRows: 500,
        init() {
            const initial = Array.isArray(config.initialRows) ? config.initialRows : null;
            if (initial && initial.length > 0) {
                this.rows = initial.map((r) => ({
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
                }));
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
            });
            this.$nextTick(() => this.$refs.scanEl?.focus());
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
                this.scanQuery = '';
                this.focusQtyInput(idx);
                return;
            }
            if (this.rows.length >= this.maxRows) {
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
                qty: '',
                results: [],
                open: false,
                loading: false,
            });
            this.scanQuery = '';
            const last = this.rows.length - 1;
            this.focusQtyInput(last);
        },
        addManualLine() {
            if (this.rows.length >= this.maxRows) {
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
        validateAuditSubmit(e) {
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
                e.preventDefault();
                window.alert('Добавьте хотя бы одну строку: выберите товар и укажите фактическое количество.');
            }
        },
    }));
});

Alpine.start();
