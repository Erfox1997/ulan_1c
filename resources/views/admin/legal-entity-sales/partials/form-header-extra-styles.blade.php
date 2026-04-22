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
</style>
