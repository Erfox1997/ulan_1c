{{-- Доп. стили шапки реализации юрлицу (ИНН, дата, ЭСФ) — поверх form-document-styles --}}
<style>
    .ob-1c-header .les-top-row {
        grid-column: 1 / -1;
    }
    .ob-1c-header .les-buyer-pin-full {
        grid-column: 1 / -1;
    }
    .ob-1c-header .les-esf-field {
        flex-shrink: 0;
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
    .ob-1c-header input[type='checkbox'].les-esf-check {
        width: 15px;
        height: 15px;
        min-width: 15px;
        max-width: 15px;
        min-height: 15px;
        max-height: 15px;
        margin: 0;
        padding: 0;
        flex-shrink: 0;
        box-sizing: border-box;
        vertical-align: middle;
        border: 1.5px solid #94a3b8;
        border-radius: 50%;
        background: #fff;
        cursor: pointer;
        appearance: none;
        -webkit-appearance: none;
        box-shadow: none;
    }
    .ob-1c-header input[type='checkbox'].les-esf-check:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgb(52 211 153 / 0.35);
    }
    .ob-1c-header input[type='checkbox'].les-esf-check:checked {
        background-color: #059669;
        border-color: #047857;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath fill='none' stroke='%23fff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' d='M2.5 6.2 5 8.7 9.5 3.8'/%3E%3C/svg%3E");
        background-size: 11px 11px;
        background-position: center;
        background-repeat: no-repeat;
    }
</style>
