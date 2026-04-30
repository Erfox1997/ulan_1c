{{-- Страница «Новая реализация»: две карточки как у поступления, без колонок под закупку --}}
<style>
    @media (min-width: 640px) {
        .les-create-page {
            grid-template-columns: minmax(20rem, 0.55fr) minmax(18rem, 0.72fr);
            column-gap: 1.35rem;
        }
    }
    @media (min-width: 1024px) {
        .les-create-page {
            column-gap: 1.6rem;
        }
    }
    .les-create-page .pr-panel-shell,
    .les-create-page .pr-cart-card {
        border-radius: 0.75rem;
    }
    .les-create-page .pr-cart-card {
        border-color: rgb(15 118 110 / 0.14);
        box-shadow: 0 4px 24px -8px rgb(15 23 42 / 0.12);
    }
    .les-create-page .pr-panel-header-teal {
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
    .les-create-page .pr-panel-header-teal label {
        margin: 0;
        cursor: text;
        color: inherit;
        font: inherit;
    }
    .les-create-page table.ob-1c-table thead th {
        background: #e0f2f1 !important;
        color: #008b8b !important;
        font-weight: 700;
        letter-spacing: 0.02em;
        border-color: rgb(203 213 225) !important;
    }
    .les-create-page table.ob-1c-table tbody tr {
        background-color: #fffceb;
    }
    .les-create-page table.ob-1c-table tbody tr:nth-child(even) {
        background-color: #fffbeb;
    }
    .les-create-page table.ob-1c-table tbody tr.ob-row-active,
    .les-create-page table.ob-1c-table tbody tr.ob-row-active .ob-inp {
        background-color: #fffceb !important;
        box-shadow: none !important;
    }
    .les-create-page table.ob-1c-table tbody tr:nth-child(even).ob-row-active,
    .les-create-page table.ob-1c-table tbody tr:nth-child(even).ob-row-active .ob-inp {
        background-color: #fffbeb !important;
    }
    .les-create-page table.ob-1c-table .ob-inp:focus {
        background: rgb(255 252 235 / 0.98) !important;
    }
    .les-create-page table.ob-1c-table td.ob-sum .ob-inp {
        background: rgb(255 252 240 / 0.95) !important;
    }
    .les-create-page .ob-1c-foot {
        border-top: 1px solid rgb(226 232 240);
        background: #fff;
        margin-top: auto;
    }
    .les-create-page .ob-btn-submit {
        border-radius: 0.5rem !important;
        border: 1px solid #e6ac00 !important;
        box-shadow:
            0 2px 4px rgb(15 23 42 / 0.06),
            inset 0 1px 0 rgb(255 255 255 / 0.7) !important;
        background: linear-gradient(180deg, #ffdf6e 0%, #ffd740 45%, #ffcc33 100%) !important;
        font-weight: 700 !important;
        color: #171717 !important;
        min-height: 28px !important;
        padding: 0.35rem 1rem !important;
        font-size: 0.75rem !important;
        line-height: 1.25 !important;
    }
    .les-create-page .ob-btn-submit:hover {
        background: linear-gradient(180deg, #ffe57f 0%, #ffca28 100%) !important;
        border-color: #c48f00 !important;
    }
</style>
