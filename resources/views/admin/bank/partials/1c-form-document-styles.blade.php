<style>
    .bank-1c-scope {
        font-family: Tahoma, Arial, 'Segoe UI', sans-serif;
        font-size: 12px;
        color: #000;
    }
    .bank-1c-doc {
        border: 1px solid #7a7a7a;
        background: #fff;
        box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.12);
    }
    .bank-1c-titlebar {
        border-bottom: 1px solid #7a7a7a;
        border-top: 3px solid #ffcc00;
        background: linear-gradient(180deg, #fffff5 0%, #f5f5f0 100%);
        padding: 12px 18px;
    }
    .bank-1c-titlebar h2 {
        margin: 0;
        font-size: 13px;
        font-weight: 700;
        color: #1a1a1a;
    }
    .bank-1c-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
        background: #ece9d8;
        border-bottom: 1px solid #aca899;
        padding: 6px 14px;
    }
    .bank-1c-tb-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 2px 12px;
        min-height: 22px;
        font-size: 11px;
        line-height: 1.2;
        border: 1px solid #a0a0a0;
        background: linear-gradient(180deg, #fff 0%, #e8e8e8 100%);
        color: #000;
        cursor: pointer;
        white-space: nowrap;
    }
    .bank-1c-tb-btn:hover {
        background: linear-gradient(180deg, #fafafa 0%, #dedede 100%);
    }
    .bank-1c-tb-btn:active {
        background: #d0d0d0;
    }
    .bank-1c-tb-btn-primary {
        border-color: #2f6f32;
        background: linear-gradient(180deg, #4caf50 0%, #2e7d32 100%);
        color: #fff;
        font-weight: 600;
    }
    .bank-1c-tb-btn-primary:hover {
        background: linear-gradient(180deg, #66bb6a 0%, #2e7d32 100%);
        color: #fff;
    }
    a.bank-1c-tb-btn {
        text-decoration: none;
        color: inherit;
        box-sizing: border-box;
    }
    a.bank-1c-tb-btn-primary,
    a.bank-1c-tb-btn-primary:hover {
        color: #fff;
    }
    .bank-1c-header {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 18px;
        padding: 10px 12px;
        background: #faf8f3;
        border-bottom: 1px solid #aca899;
    }
    @media (max-width: 720px) {
        .bank-1c-header {
            grid-template-columns: 1fr;
        }
    }
    /* Банк/касса: широкая сетка (приход, расход поставщику и т.п.) */
    .bank-1c-header--bank-doc-wide {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px 28px;
        padding: 16px 20px;
    }
    .bank-1c-header--bank-doc-wide .bank-1c-span-full {
        grid-column: 1 / -1;
    }
    @media (max-width: 1023px) {
        .bank-1c-header--bank-doc-wide {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .bank-1c-header--bank-doc-wide .bank-1c-amount-below-pair {
            grid-column: 1 / -1;
        }
    }
    @media (max-width: 640px) {
        .bank-1c-header--bank-doc-wide {
            grid-template-columns: 1fr;
        }
        .bank-1c-header--bank-doc-wide .bank-1c-amount-below-pair {
            grid-column: auto;
        }
    }
    .bank-1c-header label,
    .bank-1c-field-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #333;
        margin-bottom: 3px;
    }
    .bank-1c-header input[type='date'],
    .bank-1c-header input[type='text'],
    .bank-1c-header select,
    .bank-1c-header textarea {
        width: 100%;
        padding: 3px 6px;
        border: 1px solid #7a7a7a;
        font: inherit;
        background: #fff;
        border-radius: 0;
    }
    .bank-1c-header textarea {
        resize: vertical;
        min-height: 52px;
    }
    .bank-1c-header input:focus,
    .bank-1c-header select:focus,
    .bank-1c-header textarea:focus {
        outline: 1px solid #316ac5;
        outline-offset: 0;
    }
    .bank-1c-cp-wrap input[type='text'] {
        width: 100%;
        padding: 3px 6px;
        border: 1px solid #7a7a7a;
        font: inherit;
        background: #fff;
        border-radius: 0;
    }
    .bank-1c-dd {
        max-height: 20rem;
        overflow-y: auto;
        border: 1px solid #7a7a7a;
        background: #fff;
        box-shadow: 2px 2px 6px rgba(0, 0, 0, 0.2);
    }
    .bank-1c-dd button.cp-row {
        display: flex;
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 1px;
        padding: 5px 8px;
        text-align: left;
        font: inherit;
        border: 0;
        border-bottom: 1px solid #e8e8e8;
        background: #fff;
        cursor: pointer;
        color: #000;
    }
    .bank-1c-dd button.cp-row:hover,
    .bank-1c-dd button.cp-row:focus {
        background: #316ac5;
        color: #fff;
    }
    .bank-1c-dd button.cp-row:hover .cp-kind,
    .bank-1c-dd button.cp-row:focus .cp-kind {
        color: #e0e8ff;
    }
    .bank-1c-dd .cp-kind {
        font-size: 10px;
        color: #666;
    }
    .bank-1c-dd .cp-foot {
        padding: 6px 8px;
        font-size: 11px;
        background: #f5f5f0;
        border-top: 1px solid #aca899;
    }
    .bank-1c-dd .cp-quick {
        padding: 8px;
        border-top: 1px solid #aca899;
        background: #faf8f3;
    }
    .bank-1c-dd .cp-quick select {
        width: 100%;
        margin-top: 4px;
        padding: 3px 6px;
        border: 1px solid #7a7a7a;
        font: inherit;
        background: #fff;
    }
    .bank-1c-foot {
        padding: 10px 18px;
        background: #ece9d8;
        border-top: 1px solid #aca899;
    }
    .bank-1c-foot a {
        font-size: 11px;
        color: #1a5276;
    }
    .bank-1c-foot a:hover {
        text-decoration: underline;
    }
    /* Переводы: дата + два счёта в ряд, сумма и комментарий ниже */
    .bank-1c-header--transfer-wide {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px 28px;
        padding: 16px 20px;
    }
    .bank-1c-header--transfer-wide .bank-1c-span-full {
        grid-column: 1 / -1;
    }
    .bank-1c-header--transfer-wide .bank-1c-span-2 {
        grid-column: span 2;
    }
    @media (max-width: 1023px) {
        .bank-1c-header--transfer-wide {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .bank-1c-header--transfer-wide .bank-1c-span-2 {
            grid-column: 1 / -1;
        }
    }
    @media (max-width: 640px) {
        .bank-1c-header--transfer-wide {
            grid-template-columns: 1fr;
        }
        .bank-1c-header--transfer-wide .bank-1c-span-2 {
            grid-column: auto;
        }
    }
    /* Панель фильтров (отчёты): без сетки, в ряд */
    .bank-1c-header.bank-1c-header--filters {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 16px 24px;
        padding: 12px 18px;
    }
    .bank-1c-header--filters .bank-1c-filter-field {
        min-width: 10rem;
    }
    .bank-1c-report-body {
        padding: 14px 18px 18px;
        background: #faf8f3;
        border-top: 1px solid #aca899;
    }
    .bank-1c-section-title {
        margin: 0 0 8px;
        font-size: 11px;
        font-weight: 700;
        color: #333;
    }
    .bank-1c-section-title:not(:first-child) {
        margin-top: 20px;
    }
    .bank-1c-table-panel {
        border: 1px solid #7a7a7a;
        background: #fff;
        overflow: hidden;
    }
    .bank-1c-data-table {
        width: 100%;
        border-collapse: collapse;
        font: inherit;
    }
    .bank-1c-data-table th,
    .bank-1c-data-table td {
        border: 1px solid #aca899;
        padding: 6px 8px;
        text-align: left;
        vertical-align: top;
    }
    .bank-1c-data-table th {
        background: #ece9d8;
        font-weight: 700;
        font-size: 11px;
        color: #222;
    }
    .bank-1c-data-table th.bank-1c-num {
        text-align: right;
    }
    .bank-1c-data-table td.bank-1c-num {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .bank-1c-data-table td.bank-1c-num-pos {
        color: #0d5c0d;
    }
    .bank-1c-data-table td.bank-1c-num-neg {
        color: #8b1538;
    }
    .bank-1c-data-table tbody tr:hover {
        background: #fffef0;
    }
    .bank-1c-data-table tfoot td {
        background: #ece9d8;
        font-weight: 700;
        font-size: 11px;
    }
    .bank-1c-data-table tfoot td.bank-1c-tfoot-note {
        background: #faf8f3;
        font-weight: 600;
    }
    .bank-1c-banner-warn {
        border: 1px solid #aca899;
        background: #fff8e1;
        padding: 10px 14px;
        font-size: 12px;
        color: #5d4037;
        margin-bottom: 12px;
    }
    /* Главное / кассовая смена */
    .bank-1c-info-panel {
        padding: 12px 18px;
        background: #fffef5;
        border-bottom: 1px solid #aca899;
        font-size: 12px;
        color: #000;
    }
    .bank-1c-info-panel .bank-1c-info-title {
        margin: 0 0 8px;
        font-size: 12px;
        font-weight: 700;
    }
    .bank-1c-info-panel ul {
        margin: 0;
        padding-left: 1.25em;
    }
    .bank-1c-info-panel li {
        margin-top: 4px;
    }
    .bank-1c-embed-section {
        padding: 14px 18px;
        background: #faf8f3;
        border-bottom: 1px solid #aca899;
    }
    .bank-1c-shift-form {
        padding: 16px 20px;
        background: #faf8f3;
        border-bottom: 1px solid #aca899;
    }
    .bank-1c-shift-form .bank-1c-field-row {
        margin-bottom: 14px;
    }
    .bank-1c-shift-form .bank-1c-field-row:last-child {
        margin-bottom: 0;
    }
    .bank-1c-shift-form input[type='text'],
    .bank-1c-shift-form textarea {
        width: 100%;
        padding: 3px 6px;
        border: 1px solid #7a7a7a;
        font: inherit;
        background: #fff;
        border-radius: 0;
    }
    .bank-1c-shift-form textarea {
        min-height: 52px;
        resize: vertical;
    }
    .bank-1c-shift-form input:focus,
    .bank-1c-shift-form textarea:focus {
        outline: 1px solid #316ac5;
        outline-offset: 0;
    }
    /* Справочники: организация, склады */
    .bank-1c-muted {
        margin: 0 0 12px;
        font-size: 11px;
        color: #555;
        line-height: 1.45;
    }
    .bank-1c-header--org-form {
        padding: 16px 20px;
        gap: 14px 20px;
    }
    .bank-1c-header--org-form .bank-1c-span-full {
        grid-column: 1 / -1;
    }
    .bank-1c-scope .bank-1c-input,
    .bank-1c-scope select.bank-1c-input {
        display: block;
        width: 100%;
        padding: 3px 6px;
        border: 1px solid #7a7a7a;
        font: inherit;
        font-size: 12px;
        background: #fff;
        border-radius: 0;
        box-sizing: border-box;
    }
    .bank-1c-scope .bank-1c-input:focus,
    .bank-1c-scope select.bank-1c-input:focus,
    .bank-1c-scope textarea.bank-1c-input:focus {
        outline: 1px solid #316ac5;
        outline-offset: 0;
    }
    .bank-1c-scope textarea.bank-1c-input {
        min-height: 52px;
        resize: vertical;
    }
    .bank-1c-scope .bank-1c-pill {
        display: inline-block;
        margin-top: 4px;
        padding: 1px 6px;
        font-size: 10px;
        font-weight: 700;
        border: 1px solid #aca899;
        background: #fffde7;
        color: #333;
    }
    .bank-1c-scope .bank-1c-table-link {
        font-weight: 600;
        color: #1a5276;
        text-decoration: none;
        font-size: 12px;
    }
    .bank-1c-scope .bank-1c-table-link:hover {
        text-decoration: underline;
    }
    .bank-1c-scope .bank-1c-table-danger {
        font-weight: 600;
        color: #b71c1c;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        font-size: 12px;
        font-family: inherit;
    }
    .bank-1c-scope .bank-1c-table-danger:hover {
        text-decoration: underline;
    }
    .bank-1c-help-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        padding: 0;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid #a0a0a0;
        background: linear-gradient(180deg, #fff 0%, #e8e8e8 100%);
        color: #333;
        cursor: pointer;
    }
    .bank-1c-help-pop {
        position: absolute;
        right: 0;
        z-index: 30;
        margin-top: 6px;
        width: min(100vw - 2rem, 22rem);
        border: 1px solid #aca899;
        background: #fffef5;
        padding: 10px 12px;
        text-align: left;
        font-size: 11px;
        line-height: 1.45;
        color: #333;
        box-shadow: 2px 2px 6px rgba(0, 0, 0, 0.15);
    }
    .bank-1c-bank-row {
        border: 1px solid #aca899;
        background: #fff;
        padding: 12px 14px;
        margin-bottom: 10px;
    }
    .bank-1c-bank-row-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 10px;
        font-size: 12px;
    }
    .bank-1c-row-remove {
        font-size: 11px;
        font-weight: 600;
        color: #b71c1c;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        font-family: inherit;
    }
    .bank-1c-row-remove:hover {
        text-decoration: underline;
    }
    .bank-1c-add-line {
        font-size: 11px;
        font-weight: 600;
        color: #1a5276;
        background: none;
        border: none;
        cursor: pointer;
        padding: 0;
        margin-right: 14px;
        font-family: inherit;
    }
    .bank-1c-add-line:hover {
        text-decoration: underline;
    }
    .bank-1c-foot.bank-1c-foot--actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
    }
</style>
