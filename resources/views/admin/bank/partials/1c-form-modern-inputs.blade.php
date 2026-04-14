{{-- Улучшенные поля ввода для форм банка/кассы (приход, расход). Подключать после 1c-form-document-styles; на контейнер с .bank-1c-scope добавить класс bank-1c-page-modern --}}
<style>
    .bank-1c-page-modern.bank-1c-scope .bank-1c-field-label,
    .bank-1c-page-modern.bank-1c-scope .bank-1c-header label {
        font-size: 11px;
        letter-spacing: 0.02em;
        color: rgb(51 65 85);
        margin-bottom: 6px;
    }

    .bank-1c-page-modern.bank-1c-scope .bank-1c-header input[type='date'],
    .bank-1c-page-modern.bank-1c-scope .bank-1c-header input[type='text'],
    .bank-1c-page-modern.bank-1c-scope .bank-1c-header select,
    .bank-1c-page-modern.bank-1c-scope .bank-1c-header textarea {
        width: 100%;
        padding: 0.5rem 0.75rem;
        min-height: 2.5rem;
        font-size: 13px;
        line-height: 1.4;
        color: rgb(15 23 42);
        background: rgb(255 255 255);
        border: 1px solid rgb(203 213 225);
        border-radius: 0.5rem;
        box-shadow: inset 0 1px 2px rgb(15 23 42 / 0.04);
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }

    .bank-1c-page-modern.bank-1c-scope .bank-1c-header textarea {
        min-height: 5.5rem;
        resize: vertical;
        padding-top: 0.55rem;
        padding-bottom: 0.55rem;
    }

    .bank-1c-page-modern.bank-1c-scope .bank-1c-header select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.6rem center;
        background-size: 1rem 1rem;
        padding-right: 2.25rem;
    }

    .bank-1c-page-modern.bank-1c-scope .bank-1c-cp-wrap input[type='text'] {
        min-height: 2.5rem;
        padding: 0.5rem 0.75rem;
        font-size: 13px;
        line-height: 1.4;
        color: rgb(15 23 42);
        background: rgb(255 255 255);
        border: 1px solid rgb(203 213 225);
        border-radius: 0.5rem;
        box-shadow: inset 0 1px 2px rgb(15 23 42 / 0.04);
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }

    .bank-1c-page-modern.bank-1c-scope .bank-1c-header input:hover,
    .bank-1c-page-modern.bank-1c-scope .bank-1c-header select:hover,
    .bank-1c-page-modern.bank-1c-scope .bank-1c-header textarea:hover,
    .bank-1c-page-modern.bank-1c-scope .bank-1c-cp-wrap input[type='text']:hover {
        border-color: rgb(148 163 184);
    }

    .bank-1c-page-modern.bank-1c-scope .bank-1c-header input:focus,
    .bank-1c-page-modern.bank-1c-scope .bank-1c-header select:focus,
    .bank-1c-page-modern.bank-1c-scope .bank-1c-header textarea:focus,
    .bank-1c-page-modern.bank-1c-scope .bank-1c-cp-wrap input[type='text']:focus {
        outline: none;
        border-color: rgb(100 116 139);
        box-shadow:
            0 0 0 3px rgb(148 163 184 / 0.35),
            inset 0 1px 2px rgb(15 23 42 / 0.04);
    }

    /* Акцент фокуса по разделу */
    .page-income-client.bank-1c-page-modern.bank-1c-scope .bank-1c-header input:focus,
    .page-income-client.bank-1c-page-modern.bank-1c-scope .bank-1c-header select:focus,
    .page-income-client.bank-1c-page-modern.bank-1c-scope .bank-1c-header textarea:focus,
    .page-income-client.bank-1c-page-modern.bank-1c-scope .bank-1c-cp-wrap input[type='text']:focus {
        border-color: rgb(14 165 233);
        box-shadow:
            0 0 0 3px rgb(14 165 233 / 0.28),
            inset 0 1px 2px rgb(15 23 42 / 0.04);
    }

    .page-expense-supplier.bank-1c-page-modern.bank-1c-scope .bank-1c-header input:focus,
    .page-expense-supplier.bank-1c-page-modern.bank-1c-scope .bank-1c-header select:focus,
    .page-expense-supplier.bank-1c-page-modern.bank-1c-scope .bank-1c-header textarea:focus,
    .page-expense-supplier.bank-1c-page-modern.bank-1c-scope .bank-1c-cp-wrap input[type='text']:focus {
        border-color: rgb(13 148 136);
        box-shadow:
            0 0 0 3px rgb(20 184 166 / 0.3),
            inset 0 1px 2px rgb(15 23 42 / 0.04);
    }

    .page-expense-other.bank-1c-page-modern.bank-1c-scope .bank-1c-header input:focus,
    .page-expense-other.bank-1c-page-modern.bank-1c-scope .bank-1c-header select:focus,
    .page-expense-other.bank-1c-page-modern.bank-1c-scope .bank-1c-header textarea:focus {
        border-color: rgb(79 70 229);
        box-shadow:
            0 0 0 3px rgb(99 102 241 / 0.32),
            inset 0 1px 2px rgb(15 23 42 / 0.04);
    }

    .page-transfers.bank-1c-page-modern.bank-1c-scope .bank-1c-header input:focus,
    .page-transfers.bank-1c-page-modern.bank-1c-scope .bank-1c-header select:focus,
    .page-transfers.bank-1c-page-modern.bank-1c-scope .bank-1c-header textarea:focus {
        border-color: rgb(8 145 178);
        box-shadow:
            0 0 0 3px rgb(6 182 212 / 0.3),
            inset 0 1px 2px rgb(15 23 42 / 0.04);
    }

    .bank-1c-page-modern.bank-1c-scope .bank-1c-dd .cp-quick select {
        border-radius: 0.375rem;
        padding: 0.4rem 0.5rem;
        border-color: rgb(203 213 225);
        min-height: 2.25rem;
    }
</style>
