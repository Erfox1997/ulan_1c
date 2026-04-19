{{-- Доп. стили шапки реализации юрлицу (ИНН, дата) — поверх form-document-styles --}}
<style>
    .ob-1c-header .les-top-row {
        grid-column: 1 / -1;
    }
    .ob-1c-header .les-buyer-pin-full {
        grid-column: 1 / -1;
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
</style>
