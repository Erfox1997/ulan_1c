{{-- Только страница «Главное»: перекрывает bank-1c после 1c-form-document-styles --}}
<style>
    .dashboard-cash-shell.bank-1c-scope {
        color: #0f172a;
    }
    .dashboard-cash-shell .bank-1c-doc {
        border: 1px solid rgb(186 230 253 / 0.85);
        border-radius: 1rem;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 4px 24px rgb(14 165 233 / 0.08);
    }
    .dashboard-cash-shell .bank-1c-titlebar {
        border-top: none;
        border-bottom: 1px solid rgb(167 243 208 / 0.65);
        background: linear-gradient(105deg, #ecfdf5 0%, #f0fdfa 40%, #ecfeff 100%);
        padding: 0.85rem 1.1rem;
    }
    .dashboard-cash-shell .bank-1c-titlebar h2 {
        font-size: 15px;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: 0.01em;
    }
    .dashboard-cash-shell .bank-1c-toolbar {
        background: linear-gradient(180deg, #ecfdf5 0%, #f0fdfa 50%, #f8fafc 100%);
        border-bottom: 1px solid rgb(167 243 208 / 0.55);
        padding: 0.55rem 1rem;
        gap: 8px;
    }
    .dashboard-cash-shell .bank-1c-tb-btn {
        border-radius: 0.375rem;
        border: 1px solid rgb(186 230 253 / 0.95);
        background: linear-gradient(180deg, #fff 0%, #f0fdfa 100%);
        font-weight: 600;
        min-height: 28px;
        padding: 4px 14px;
    }
    .dashboard-cash-shell .bank-1c-tb-btn:hover {
        background: linear-gradient(180deg, #f0fdfa 0%, #e0f2fe 100%);
        border-color: rgb(125 211 252);
    }
    .dashboard-cash-shell .bank-1c-tb-btn-primary {
        border-color: rgb(5 150 105 / 0.95);
        background: linear-gradient(180deg, #34d399 0%, #059669 100%);
        color: #fff;
        font-weight: 700;
        box-shadow: 0 1px 2px rgb(16 185 129 / 0.25);
    }
    .dashboard-cash-shell .bank-1c-tb-btn-primary:hover {
        background: linear-gradient(180deg, #6ee7b7 0%, #047857 100%);
        color: #fff;
    }
    .dashboard-cash-shell .bank-1c-info-panel {
        background: linear-gradient(180deg, rgb(240 253 250 / 0.9) 0%, #fff 100%);
        border-bottom: 1px solid rgb(226 232 240);
        padding: 1rem 1.1rem;
        font-size: 13px;
        color: #334155;
    }
    .dashboard-cash-shell .bank-1c-info-panel .bank-1c-info-title {
        color: #0f766e;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .dashboard-cash-shell .bank-1c-embed-section {
        background: rgb(248 250 252 / 0.95);
        border-bottom: 1px solid rgb(226 232 240);
        padding: 1rem 1.1rem;
    }
    .dashboard-cash-shell .bank-1c-table-panel {
        border: 1px solid rgb(226 232 240);
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .dashboard-cash-shell .bank-1c-data-table th {
        background: linear-gradient(180deg, #ecfdf5 0%, #e0f2fe 100%);
        border-color: rgb(226 232 240);
        color: #0f766e;
        font-weight: 700;
    }
    .dashboard-cash-shell .bank-1c-data-table td,
    .dashboard-cash-shell .bank-1c-data-table th {
        border-color: rgb(226 232 240);
    }
    .dashboard-cash-shell .bank-1c-data-table tbody tr:hover {
        background: rgb(236 253 245 / 0.5);
    }
    .dashboard-cash-shell .bank-1c-data-table tfoot td {
        background: rgb(241 245 249 / 0.95);
        border-color: rgb(226 232 240);
    }
    .dashboard-cash-shell .bank-1c-data-table tfoot td.bank-1c-tfoot-note {
        background: rgb(254 252 232 / 0.6);
        color: #713f12;
    }
    .dashboard-cash-shell .bank-1c-shift-form {
        background: linear-gradient(180deg, #fff 0%, rgb(248 250 252 / 0.85) 100%);
        border-bottom: 1px solid rgb(226 232 240);
        padding: 1.1rem 1.15rem;
    }
    .dashboard-cash-shell .bank-1c-shift-form .bank-1c-field-label {
        color: #334155;
        font-size: 12px;
        margin-bottom: 6px;
    }
    .dashboard-cash-shell .bank-1c-shift-form input[type='text'],
    .dashboard-cash-shell .bank-1c-shift-form textarea {
        border: 1px solid rgb(203 213 225);
        border-radius: 0.5rem;
        padding: 8px 10px;
        background: #fff;
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }
    .dashboard-cash-shell .bank-1c-shift-form input:focus,
    .dashboard-cash-shell .bank-1c-shift-form textarea:focus {
        outline: none;
        border-color: #34d399;
        box-shadow: 0 0 0 2px rgb(52 211 153 / 0.2);
    }
    .dashboard-cash-shell .bank-1c-banner-warn {
        border-radius: 0.75rem;
        border: 1px solid rgb(251 191 36 / 0.65);
        background: linear-gradient(90deg, rgb(254 252 232 / 0.95) 0%, #fff 100%);
        color: #713f12;
    }
    .dashboard-cash-shell .bank-1c-foot {
        padding: 0.85rem 1.1rem;
        background: linear-gradient(180deg, rgb(248 250 252 / 0.8) 0%, rgb(236 253 245 / 0.35) 100%);
        border-top: 1px solid rgb(167 243 208 / 0.45);
    }
    .dashboard-cash-shell .bank-1c-foot a {
        font-size: 12px;
        font-weight: 600;
        color: #0d9488;
        text-decoration: none;
    }
    .dashboard-cash-shell .bank-1c-foot a:hover {
        color: #0f766e;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
</style>
