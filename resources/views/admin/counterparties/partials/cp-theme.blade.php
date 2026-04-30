{{-- Современная цветная тема карточки контрагента --}}
<style>
    .cp-root {
        --cp-accent: #0d9488;
        --cp-accent-dark: #0f766e;
        --cp-accent-soft: rgba(13, 148, 136, 0.12);
        --cp-violet: #6366f1;
        --cp-violet-soft: rgba(99, 102, 241, 0.1);
        --cp-amber: #d97706;
        --cp-slate-ink: #0f172a;
        --cp-border: rgba(148, 163, 184, 0.35);
        font-family: ui-sans-serif, system-ui, "Segoe UI", Tahoma, Arial, sans-serif;
        font-size: 13px;
        color: var(--cp-slate-ink);
        -webkit-font-smoothing: antialiased;
    }
    .cp-panel {
        border: 1px solid var(--cp-border);
        border-radius: 1rem;
        background: linear-gradient(
            165deg,
            rgba(255, 255, 255, 0.98) 0%,
            rgba(248, 250, 252, 0.95) 45%,
            rgba(240, 253, 250, 0.55) 100%
        );
        box-shadow:
            0 1px 2px rgba(15, 23, 42, 0.04),
            0 12px 32px -8px rgba(15, 23, 42, 0.1),
            0 0 0 1px rgba(255, 255, 255, 0.6) inset;
        overflow: hidden;
    }
    .cp-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 0.875rem;
        background: linear-gradient(90deg, rgba(241, 245, 249, 0.95) 0%, rgba(240, 253, 250, 0.45) 100%);
        border-bottom: 1px solid var(--cp-border);
    }
    .cp-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        min-height: 2rem;
        padding: 0.35rem 1rem;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.25;
        border-radius: 0.625rem;
        border: 1px solid rgba(148, 163, 184, 0.55);
        background: linear-gradient(180deg, #fff 0%, #f1f5f9 100%);
        color: #334155;
        text-decoration: none;
        cursor: pointer;
        box-shadow: 0 1px 0 rgba(255, 255, 255, 0.85) inset;
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease,
            transform 0.06s ease;
    }
    .cp-btn:hover {
        background: linear-gradient(180deg, #fafafa 0%, #e2e8f0 100%);
        border-color: rgba(100, 116, 139, 0.6);
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
    }
    .cp-btn:active {
        transform: translateY(1px);
    }
    .cp-btn-primary {
        border-color: transparent;
        background: linear-gradient(135deg, #0d9488 0%, #059669 48%, #0f766e 100%);
        color: #fff;
        font-weight: 700;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.2) inset,
            0 4px 14px rgba(13, 148, 136, 0.35);
    }
    .cp-btn-primary:hover {
        background: linear-gradient(135deg, #14b8a6 0%, #10b981 48%, #0d9488 100%);
        color: #fff;
        box-shadow:
            0 1px 0 rgba(255, 255, 255, 0.25) inset,
            0 6px 18px rgba(13, 148, 136, 0.4);
    }
    .cp-titlebar {
        position: relative;
        padding: 0.875rem 1rem;
        background: linear-gradient(
            120deg,
            #0f766e 0%,
            #0d9488 28%,
            #0891b2 72%,
            #6366f1 130%
        );
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 4px 20px rgba(13, 148, 136, 0.2);
    }
    .cp-titlebar::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            radial-gradient(ellipse 80% 160% at 100% -20%, rgba(255, 255, 255, 0.22), transparent 55%),
            radial-gradient(ellipse 60% 120% at 0% 120%, rgba(99, 102, 241, 0.35), transparent 50%);
        pointer-events: none;
    }
    .cp-titlebar h2,
    .cp-titlebar .cp-title {
        position: relative;
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        color: #fff;
        text-shadow: 0 1px 2px rgba(15, 23, 42, 0.2);
    }
    .cp-link {
        color: #0891b2;
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 3px;
    }
    .cp-link:hover {
        color: var(--cp-violet);
    }
    .cp-subhead {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.55rem 1rem;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #0f766e;
        background: linear-gradient(90deg, var(--cp-accent-soft) 0%, rgba(99, 102, 241, 0.06) 100%);
        border-bottom: 1px solid var(--cp-border);
    }
    .cp-subhead::before {
        content: "";
        width: 4px;
        height: 1rem;
        border-radius: 999px;
        background: linear-gradient(180deg, #0d9488, #6366f1);
        flex-shrink: 0;
    }
    .cp-section-divider {
        border-bottom: 1px solid var(--cp-border);
    }
    .cp-field {
        margin-top: 0.35rem;
        width: 100%;
        padding: 0.5rem 0.65rem;
        border: 1px solid rgba(148, 163, 184, 0.45);
        border-radius: 0.5rem;
        font: inherit;
        font-size: 13px;
        background: #fff;
        color: var(--cp-slate-ink);
        box-sizing: border-box;
        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease;
    }
    .cp-field:focus {
        outline: none;
        border-color: rgba(13, 148, 136, 0.55);
        box-shadow:
            0 0 0 3px var(--cp-accent-soft),
            0 1px 2px rgba(15, 23, 42, 0.05);
    }
    .cp-field-readonly {
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        color: #475569;
        border-style: dashed;
    }
    .cp-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        margin-bottom: 2px;
    }
    .cp-grid {
        display: grid;
        gap: 0.875rem 1.25rem;
        padding: 1rem 1rem;
        background: linear-gradient(
            180deg,
            rgba(248, 250, 252, 0.75) 0%,
            rgba(255, 255, 255, 0.55) 100%
        );
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
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .cp-table th,
    .cp-table td {
        border: 1px solid var(--cp-border);
        padding: 0.45rem 0.65rem;
        vertical-align: middle;
    }
    .cp-table thead th {
        background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
        font-size: 11px;
        font-weight: 700;
        color: #334155;
        text-align: left;
    }
    .cp-table tbody tr:hover {
        background: rgba(240, 253, 250, 0.5);
    }
    .cp-table td.cp-num {
        text-align: center;
        font-variant-numeric: tabular-nums;
    }
    .cp-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        border: 1px solid transparent;
    }
    .cp-badge-buyer {
        background: rgba(16, 185, 129, 0.15);
        border-color: rgba(16, 185, 129, 0.35);
        color: #047857;
    }
    .cp-badge-supplier {
        background: rgba(59, 130, 246, 0.12);
        border-color: rgba(59, 130, 246, 0.35);
        color: #1e40af;
    }
    .cp-badge-other {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #475569;
    }
    .cp-foot {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        align-items: center;
        gap: 0.625rem;
        padding: 0.875rem 1rem;
        border-top: 1px solid var(--cp-border);
        background: linear-gradient(180deg, rgba(241, 245, 249, 0.6) 0%, rgba(255, 255, 255, 0.95) 100%);
    }
    .cp-bank-section {
        background:
            radial-gradient(ellipse 90% 80% at 100% 0%, var(--cp-violet-soft), transparent 55%),
            linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
    }
    .cp-bank-card {
        border: 1px solid rgba(99, 102, 241, 0.2);
        background: linear-gradient(
            145deg,
            rgba(255, 255, 255, 0.98) 0%,
            rgba(238, 242, 255, 0.5) 100%
        );
        padding: 0.875rem 1rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 16px rgba(79, 70, 229, 0.06);
    }
</style>
