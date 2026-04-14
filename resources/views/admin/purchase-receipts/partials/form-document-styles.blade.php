{{-- Табличная форма поступления (create/edit) — в духе opening-balances, без серой «1С» --}}
<style>
    .ob-1c-scope {
        font-family: Tahoma, 'Segoe UI', Arial, sans-serif;
        font-size: 12px;
        color: #0f172a;
    }
    .ob-1c-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        margin-bottom: 0;
        border: 1px solid rgb(167 243 208 / 0.55);
        border-left: 0;
        border-right: 0;
        background: linear-gradient(180deg, #ecfdf5 0%, #f0fdfa 45%, #f8fafc 100%);
        padding: 0.55rem 0.65rem;
    }
    .ob-tb-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-height: 24px;
        padding: 3px 11px;
        font-size: 11px;
        line-height: 1.2;
        font-weight: 600;
        color: #0f172a;
        white-space: nowrap;
        cursor: pointer;
        border: 1px solid rgb(186 230 253 / 0.95);
        border-radius: 0.375rem;
        background: linear-gradient(180deg, #fff 0%, #f0fdfa 100%);
        box-shadow: 0 1px 0 rgb(255 255 255 / 0.85) inset;
    }
    .ob-tb-btn:hover {
        background: linear-gradient(180deg, #f0fdfa 0%, #e0f2fe 100%);
        border-color: rgb(125 211 252);
    }
    .ob-tb-btn:active {
        background: #e0f2fe;
    }
    .ob-tb-btn-icon {
        padding: 3px 7px;
        min-width: 26px;
    }
    .ob-1c-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
        background: #fff;
    }
    .ob-1c-table th,
    .ob-1c-table td {
        border: 1px solid rgb(226 232 240);
        padding: 0;
        vertical-align: middle;
    }
    .ob-1c-table th {
        background: linear-gradient(180deg, #ecfdf5 0%, #e0f2fe 100%);
        font-weight: 700;
        text-align: left;
        padding: 6px 8px;
        white-space: nowrap;
        color: #0f766e;
        font-size: 11px;
        letter-spacing: 0.02em;
    }
    .ob-1c-table th.ob-num,
    .ob-1c-table td.ob-num {
        text-align: center;
        width: 2.25rem;
        color: #475569;
    }
    .ob-1c-table .ob-inp {
        display: block;
        width: 100%;
        min-height: 26px;
        margin: 0;
        padding: 4px 8px;
        border: 0;
        background: transparent;
        font: inherit;
        color: #0f172a;
        outline: none;
        box-shadow: none;
    }
    .ob-1c-table .ob-inp:focus {
        background: rgb(236 253 245 / 0.95);
    }
    .ob-1c-table td.ob-numr .ob-inp {
        text-align: right;
    }
    .ob-1c-table td.ob-sum .ob-inp {
        text-align: right;
        color: #334155;
        background: rgb(248 250 252 / 0.95);
    }
    .ob-row-active {
        background: rgb(254 252 232 / 0.95) !important;
    }
    .ob-row-active .ob-inp {
        background: rgb(254 252 232 / 0.95) !important;
    }
    .ob-row-active td.ob-sum .ob-inp {
        background: rgb(254 252 232 / 0.95) !important;
    }
    .ob-more-wrap {
        position: relative;
        margin-left: auto;
    }
    .ob-more-dd {
        position: absolute;
        right: 0;
        top: 100%;
        z-index: 40;
        margin-top: 4px;
        min-width: 11rem;
        overflow: hidden;
        border-radius: 0.5rem;
        border: 1px solid rgb(186 230 253 / 0.9);
        background: #fff;
        box-shadow: 0 10px 30px -8px rgb(14 165 233 / 0.25);
    }
    .ob-more-dd button {
        display: block;
        width: 100%;
        text-align: left;
        padding: 8px 12px;
        font-size: 11px;
        font-weight: 600;
        border: 0;
        background: #fff;
        cursor: pointer;
        color: #0f172a;
    }
    .ob-more-dd button:hover {
        background: rgb(240 249 255);
    }
    .ob-1c-foot {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 0;
        padding: 0.75rem 0.85rem 0.85rem;
        border-top: 1px solid rgb(203 213 225 / 0.85);
        background: linear-gradient(180deg, rgb(248 250 252 / 0.6) 0%, #fff 100%);
    }
    .ob-btn-submit {
        min-height: 28px !important;
        padding: 5px 18px !important;
        font-size: 12px !important;
        border-color: #b8a642 !important;
        background: linear-gradient(180deg, #fffef0 0%, #f0e68c 100%) !important;
        font-weight: 700 !important;
    }
    .ob-btn-submit:hover {
        background: linear-gradient(180deg, #fffce8 0%, #e8dc7a 100%) !important;
        border-color: #a89838 !important;
    }
    .ob-1c-header {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px 20px;
        padding: 0.85rem 1rem;
        background: linear-gradient(180deg, rgb(248 250 252 / 0.9) 0%, #fff 100%);
        border-bottom: 1px solid rgb(226 232 240);
    }
    @media (max-width: 640px) {
        .ob-1c-header {
            grid-template-columns: 1fr;
        }
    }
    .ob-1c-header label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 6px;
    }
    .ob-1c-header input,
    .ob-1c-header select {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid rgb(203 213 225);
        border-radius: 0.5rem;
        font: inherit;
        background: #fff;
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }
    .ob-1c-header input:focus,
    .ob-1c-header select:focus {
        outline: none;
        border-color: #34d399;
        box-shadow: 0 0 0 2px rgb(52 211 153 / 0.2);
    }
</style>
