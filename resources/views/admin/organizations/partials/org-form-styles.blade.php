{{-- Локальные акценты для форм организаций (не трогает глобальный cp-theme) --}}
<style>
    .org-form-scope .org-panel {
        border-radius: 1rem;
        border: 1px solid rgb(186 230 253 / 0.85);
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        box-shadow:
            0 1px 2px rgb(14 165 233 / 0.06),
            0 12px 40px -12px rgb(14 165 233 / 0.12);
    }
    .org-form-scope .org-panel .cp-toolbar {
        border-bottom-color: rgb(186 230 253 / 0.7);
        background: linear-gradient(90deg, #f8fafc 0%, #ecfeff 45%, #f0fdfa 100%);
    }
    .org-form-scope .org-titlebar {
        border-bottom: 1px solid rgb(167 243 208 / 0.65);
        background: linear-gradient(105deg, #ecfdf5 0%, #f0fdfa 35%, #ecfeff 100%);
    }
    .org-form-scope .org-subhead {
        border-bottom: 1px solid rgb(186 230 253 / 0.55);
        background: linear-gradient(90deg, rgba(236, 253, 245, 0.95) 0%, rgba(240, 249, 255, 0.5) 100%);
        padding: 0.55rem 1rem;
        font-size: 12px;
        font-weight: 700;
        color: #0f766e;
        letter-spacing: 0.02em;
    }
    .org-form-scope .org-grid-block {
        border-bottom: 1px solid rgb(203 213 225 / 0.65);
        background: linear-gradient(180deg, rgba(248, 250, 252, 0.65) 0%, rgba(255, 255, 255, 0.4) 100%);
    }
    .org-form-scope .org-bank-zone {
        border-bottom: 1px solid rgb(203 213 225 / 0.65);
        background: linear-gradient(165deg, #f0fdfa 0%, #f8fafc 40%, #fff 100%);
    }
    .org-form-scope .org-foot {
        border-top: 1px solid rgb(167 243 208 / 0.5);
        background: linear-gradient(180deg, #fafafa 0%, #ecfdf5 100%);
    }
    .org-form-scope .cp-field,
    .org-form-scope select.cp-field,
    .org-form-scope textarea.cp-field {
        border-color: #cbd5e1;
        border-radius: 0.375rem;
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }
    .org-form-scope .cp-field:focus,
    .org-form-scope select.cp-field:focus,
    .org-form-scope textarea.cp-field:focus {
        border-color: #34d399;
        outline: none;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.18);
    }
    .org-form-scope .org-bank-card {
        border-radius: 0.75rem;
        border: 1px solid rgb(186 230 253 / 0.75);
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 2px 8px rgb(14 165 233 / 0.06);
        border-left: 3px solid #34d399;
    }
    .org-form-scope .org-bank-card:hover {
        box-shadow: 0 4px 16px rgb(14 165 233 / 0.1);
    }
    .org-form-scope .org-btn-ghost {
        border-color: rgb(125 211 252 / 0.9);
        background: linear-gradient(180deg, #ffffff 0%, #ecfeff 100%);
    }
    .org-form-scope .org-btn-ghost:hover {
        border-color: rgb(56 189 248 / 0.95);
        background: linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%);
    }
    .org-form-scope .org-titlebar .cp-title {
        margin: 0;
    }
    .org-form-scope .cp-foot.org-foot {
        padding-top: 0.875rem;
        padding-bottom: 0.875rem;
    }

    /* Справочники: списки организаций / складов — без «серой сетки 1С» */
    .cp-root table.cp-directory-table {
        border-collapse: separate;
        border-spacing: 0;
        overflow: hidden;
        border-radius: 0 0 0.75rem 0.75rem;
    }
    .cp-root table.cp-directory-table thead th {
        background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 45%, #e0f2fe 100%);
        border-color: rgb(167 243 208 / 0.65);
        color: #0f766e;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
        padding: 0.55rem 0.75rem;
        vertical-align: middle;
    }
    .cp-root table.cp-directory-table thead th:first-child {
        border-left: none;
    }
    .cp-root table.cp-directory-table thead th:last-child {
        border-right: none;
    }
    .cp-root table.cp-directory-table tbody td {
        border-color: rgb(226 232 240);
        padding: 0.6rem 0.75rem;
        vertical-align: middle;
        background: #fff;
    }
    .cp-root table.cp-directory-table tbody tr:nth-child(even) td {
        background: rgb(248 250 252 / 0.85);
    }
    .cp-root table.cp-directory-table tbody tr:hover td {
        background: rgb(236 253 245 / 0.65);
    }
    .cp-root table.cp-directory-table tbody tr:last-child td:first-child {
        border-bottom-left-radius: 0.65rem;
    }
    .cp-root table.cp-directory-table tbody tr:last-child td:last-child {
        border-bottom-right-radius: 0.65rem;
    }
    .cp-root .cp-directory-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        padding: 0.2rem 0.55rem;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.03em;
        border: 1px solid rgb(167 243 208 / 0.95);
        background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
        color: #065f46;
        box-shadow: 0 1px 2px rgb(16 185 129 / 0.12);
    }
    .cp-root .cp-directory-pill-muted {
        color: rgb(148 163 184);
        font-size: 12px;
        font-weight: 600;
    }
</style>
