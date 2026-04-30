{{-- Доп. стили шапки реализации юрлицу (ИНН, дата) — поверх form-document-styles --}}
<style>
    .ob-1c-header .les-header-primary-row {
        grid-column: 1 / -1;
    }
    .ob-1c-header .les-comment-row {
        grid-column: 1 / -1;
    }
    .ob-1c-header .les-buyer-pin-field {
        flex: 0 0 auto;
        width: 11.5rem;
        min-width: 0;
    }
    @media (min-width: 640px) {
        .ob-1c-header .les-buyer-pin-field {
            width: 12.5rem;
        }
    }

    /* Верхняя карточка create: ровная сетка, одна высота контролов */
    .les-doc-top-fields {
        display: grid;
        grid-template-columns: 1fr;
        align-items: stretch;
        gap: 0.75rem 1rem;
    }
    @media (min-width: 640px) {
        .les-doc-top-fields {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (min-width: 1024px) {
        .les-doc-top-fields {
            grid-template-columns: minmax(10rem, 1fr) minmax(0, 2.2fr) minmax(9.5rem, 0.85fr) minmax(10rem, 1fr);
            gap: 0.75rem 1rem;
        }
    }
    .les-doc-top-fields > form {
        min-width: 0;
    }
    .les-doc-top-fields .les-field-cell {
        min-width: 0;
    }
    .les-doc-top-fields .les-field-input,
    .les-doc-top-fields select.les-field-input {
        width: 100%;
        min-height: 2.75rem;
        box-sizing: border-box;
        padding: 0.5rem 0.75rem;
        border: 1px solid rgb(203 213 225);
        border-radius: 0.5rem;
        font-size: 0.875rem;
        line-height: 1.35;
        color: #0f172a;
        background: #fff;
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }
    .les-doc-top-fields .les-field-input::placeholder {
        color: rgb(148 163 184);
    }
    .les-doc-top-fields .les-field-input:focus,
    .les-doc-top-fields select.les-field-input:focus {
        outline: none;
        border-color: #34d399;
        box-shadow: 0 0 0 2px rgb(52 211 153 / 0.2);
    }
    .les-doc-top-fields select.les-field-input {
        padding-right: 2.25rem;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.25em 1.25em;
        appearance: none;
    }
    .les-doc-top-fields input[type='date'].les-field-input {
        min-height: 2.75rem;
        padding-inline: 0.65rem;
    }

    .les-doc-top-card textarea.les-doc-comment {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid rgb(203 213 225);
        border-radius: 0.5rem;
        font: inherit;
        font-size: 0.875rem;
        background: #fff;
        line-height: 1.35;
        min-height: 2.75rem;
        max-height: 10rem;
        resize: vertical;
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }
    .les-doc-top-card textarea.les-doc-comment:focus {
        outline: none;
        border-color: #34d399;
        box-shadow: 0 0 0 2px rgb(52 211 153 / 0.2);
    }
    .les-doc-top-card textarea.les-doc-comment::placeholder {
        color: rgb(148 163 184);
    }

    .ob-1c-header textarea.les-doc-comment {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid rgb(203 213 225);
        border-radius: 0.5rem;
        font: inherit;
        background: #fff;
        line-height: 1.35;
        min-height: 2.25rem;
        max-height: 5rem;
        resize: vertical;
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }
    .ob-1c-header textarea.les-doc-comment:focus {
        outline: none;
        border-color: #34d399;
        box-shadow: 0 0 0 2px rgb(52 211 153 / 0.2);
    }
    .ob-1c-header .les-date-doc-wrap {
        flex: 0 0 auto;
        min-width: 0;
    }
    .ob-1c-header .les-date-doc-wrap input[type='date'] {
        width: auto;
        min-width: 8.75rem;
        max-width: 10.25rem;
        box-sizing: border-box;
    }

    /* Таблица строк реализации юрлицу: узкие числовые колонки, остаток — наименование */
    .ob-1c-table.les-sale-lines-table {
        table-layout: fixed;
        width: 100%;
    }
    .ob-1c-table.les-sale-lines-table th {
        white-space: nowrap;
        vertical-align: middle;
        line-height: 1.2;
    }
    .ob-1c-table.les-sale-lines-table td.les-col-name {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    /* Удаление строки (как в поступлении) */
    .ob-1c-table.les-sale-lines-table th.pr-line-remove-col,
    .ob-1c-table.les-sale-lines-table td.pr-line-remove-cell {
        width: 2.25rem;
        min-width: 2.25rem;
        max-width: 2.75rem;
        text-align: center;
        vertical-align: middle;
        padding: 2px 4px;
    }
    .ob-1c-table.les-sale-lines-table .pr-line-remove-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.5rem;
        height: 1.5rem;
        padding: 0;
        border: none;
        border-radius: 0.25rem;
        background: transparent;
        color: rgb(100 116 139);
        cursor: pointer;
        line-height: 1;
    }
    .ob-1c-table.les-sale-lines-table .pr-line-remove-btn:hover:not(:disabled) {
        background: rgb(254 226 226 / 0.9);
        color: rgb(185 28 28);
    }
    .ob-1c-table.les-sale-lines-table .pr-line-remove-btn:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }
</style>
