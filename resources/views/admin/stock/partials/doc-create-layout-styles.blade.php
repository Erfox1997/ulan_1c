{{-- Двухколоночный макет «поиск товара | таблица строк» как у trade.purchase-in/create --}}
<style>
    @media (min-width: 640px) {
        .stock-doc-create-page {
            grid-template-columns: minmax(20rem, 0.55fr) minmax(18rem, 0.72fr);
            column-gap: 1.35rem;
        }
    }
    @media (min-width: 1024px) {
        .stock-doc-create-page {
            column-gap: 1.6rem;
        }
    }
    .stock-doc-create-page .pr-panel-shell,
    .stock-doc-create-page .stock-doc-lines-card {
        border-radius: 0.75rem;
    }
    .stock-doc-create-page .stock-doc-lines-card {
        border-color: rgb(15 118 110 / 0.14);
        box-shadow: 0 4px 24px -8px rgb(15 23 42 / 0.12);
    }
    .stock-doc-create-page .pr-panel-header-teal {
        box-sizing: border-box;
        display: flex;
        align-items: center;
        min-height: 3.375rem;
        padding: 0.625rem 1rem;
        background: #008b8b;
        font-size: 0.9375rem;
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: 0.01em;
        color: #fff;
    }
    .stock-doc-create-page .pr-panel-header-teal label {
        margin: 0;
        cursor: text;
        color: inherit;
        font: inherit;
    }
    .stock-doc-create-page table.ob-1c-table thead th {
        background: #e0f2f1 !important;
        color: #008b8b !important;
        font-weight: 700;
        letter-spacing: 0.02em;
        border-color: rgb(203 213 225) !important;
    }
    .stock-doc-create-page table.ob-1c-table tbody tr {
        background-color: #fffceb;
    }
    .stock-doc-create-page table.ob-1c-table tbody tr:nth-child(even) {
        background-color: #fffbeb;
    }
    .stock-doc-create-page table.ob-1c-table tbody tr.ob-row-active,
    .stock-doc-create-page table.ob-1c-table tbody tr.ob-row-active .ob-inp {
        background-color: #fffceb !important;
        box-shadow: none !important;
    }
    .stock-doc-create-page table.ob-1c-table tbody tr:nth-child(even).ob-row-active,
    .stock-doc-create-page table.ob-1c-table tbody tr:nth-child(even).ob-row-active .ob-inp {
        background-color: #fffbeb !important;
    }
    .stock-doc-create-page table.ob-1c-table .ob-inp:focus {
        background: rgb(255 252 235 / 0.98) !important;
    }
</style>
