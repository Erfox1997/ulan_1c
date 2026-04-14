{{-- Стили в духе 1С: серые панели, градиенты кнопок, чёткая сетка --}}
<style>
    .cp-root {
        font-family: Tahoma, "Segoe UI", Arial, sans-serif;
        font-size: 12px;
        color: #1a1a1a;
        -webkit-font-smoothing: antialiased;
    }
    .cp-panel {
        border: 1px solid #c0c0c0;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }
    .cp-titlebar {
        border-bottom: 1px solid #c0c0c0;
        background: linear-gradient(180deg, #fafafa 0%, #ececec 100%);
        padding: 8px 12px;
    }
    .cp-titlebar h2,
    .cp-titlebar .cp-title {
        margin: 0;
        font-size: 13px;
        font-weight: 700;
        color: #1a1a1a;
        letter-spacing: 0.01em;
    }
    .cp-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        background: #e8e8e8;
        border-bottom: 1px solid #c0c0c0;
    }
    .cp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-height: 24px;
        padding: 3px 14px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
        border: 1px solid #a0a0a0;
        border-radius: 2px;
        background: linear-gradient(180deg, #fff 0%, #e8e8e8 100%);
        color: #000;
        text-decoration: none;
        cursor: pointer;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
    }
    .cp-btn:hover {
        background: linear-gradient(180deg, #fafafa 0%, #dedede 100%);
        border-color: #909090;
    }
    .cp-btn-primary {
        border-color: #b8a642;
        background: linear-gradient(180deg, #fffef0 0%, #f0e68c 100%);
        font-weight: 700;
    }
    .cp-btn-primary:hover {
        background: linear-gradient(180deg, #fffce8 0%, #e8dc7a 100%);
        border-color: #a89838;
    }
    .cp-link {
        color: #0645ad;
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 2px;
    }
    .cp-link:hover {
        color: #0b0080;
    }
    .cp-subhead {
        border-bottom: 1px solid #c0c0c0;
        background: linear-gradient(180deg, #f5f5f5 0%, #ebebeb 100%);
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 700;
        color: #222;
    }
    .cp-field {
        margin-top: 4px;
        width: 100%;
        padding: 4px 8px;
        border: 1px solid #a0a0a0;
        border-radius: 2px;
        font: inherit;
        font-size: 12px;
        background: #fff;
        color: #000;
        box-sizing: border-box;
    }
    .cp-field:focus {
        outline: none;
        border-color: #6b9ec2;
        box-shadow: 0 0 0 1px rgba(107, 158, 194, 0.35);
    }
    .cp-field-readonly {
        background: #f5f5f5;
        color: #333;
    }
    .cp-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: #333;
        margin-bottom: 2px;
    }
    .cp-grid {
        display: grid;
        gap: 10px 16px;
        padding: 10px 12px;
        background: #fafafa;
    }
    @media (min-width: 640px) {
        .cp-grid-2 {
            grid-template-columns: 1fr 1fr;
        }
    }
    .cp-table-wrap {
        overflow-x: auto;
    }
    .cp-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
    }
    .cp-table th,
    .cp-table td {
        border: 1px solid #c0c0c0;
        padding: 5px 8px;
        vertical-align: middle;
    }
    .cp-table thead th {
        background: linear-gradient(180deg, #f0f0f0 0%, #e4e4e4 100%);
        font-size: 11px;
        font-weight: 600;
        color: #333;
        text-align: left;
    }
    .cp-table tbody tr:hover {
        background: #fffef5;
    }
    .cp-table td.cp-num {
        text-align: center;
        font-variant-numeric: tabular-nums;
    }
    .cp-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 2px;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid transparent;
    }
    .cp-badge-buyer {
        background: #e8f4ec;
        border-color: #8fbc9a;
        color: #14532d;
    }
    .cp-badge-supplier {
        background: #e8f0fc;
        border-color: #7eb0d8;
        color: #1e3a5f;
    }
    .cp-badge-other {
        background: #f0f0f0;
        border-color: #b0b0b0;
        color: #333;
    }
    .cp-foot {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-top: 1px solid #c0c0c0;
        background: linear-gradient(180deg, #f5f5f5 0%, #ebebeb 100%);
    }
    .cp-bank-card {
        border: 1px solid #c0c0c0;
        background: linear-gradient(180deg, #fafafa 0%, #fff 100%);
        padding: 10px 12px;
        border-radius: 2px;
    }
</style>
