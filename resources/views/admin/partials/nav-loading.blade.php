{{-- Глобальная индикация перехода: боковое меню, шапка, формы, внутренние ссылки --}}
<style>
    #admin-nav-page-loading {
        position: fixed;
        inset: 0;
        z-index: 300;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        background: rgba(15, 23, 42, 0.48);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: opacity 0.22s ease, visibility 0.22s;
    }
    #admin-nav-page-loading.admin-nav-page-loading--visible {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }
    .admin-nav-spinner-ring {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: 3px solid rgba(45, 212, 191, 0.22);
        border-top-color: #14b8a6;
        border-right-color: #5eead4;
        animation: admin-nav-spin 0.72s linear infinite;
        box-shadow: 0 0 24px rgba(20, 184, 166, 0.35);
    }
    @keyframes admin-nav-spin {
        to {
            transform: rotate(360deg);
        }
    }
    .admin-nav-page-loading__text {
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0.02em;
        color: rgb(226 232 240);
        text-shadow: 0 1px 2px rgb(15 23 42 / 0.8);
    }
</style>
<div
    id="admin-nav-page-loading"
    aria-hidden="true"
    role="status"
    aria-live="polite"
>
    <div class="admin-nav-spinner-ring" aria-hidden="true"></div>
    <p class="admin-nav-page-loading__text">Загрузка…</p>
</div>
<script>
    (function () {
        function adminNavShowLoading() {
            var el = document.getElementById('admin-nav-page-loading');
            if (!el) {
                return;
            }
            el.classList.add('admin-nav-page-loading--visible');
            el.setAttribute('aria-hidden', 'false');
        }
        function adminNavHideLoading() {
            var el = document.getElementById('admin-nav-page-loading');
            if (!el) {
                return;
            }
            el.classList.remove('admin-nav-page-loading--visible');
            el.setAttribute('aria-hidden', 'true');
        }

        function adminNavShouldShowForLink(anchor) {
            if (anchor.hasAttribute('data-no-nav-loading')) {
                return false;
            }
            if (anchor.target === '_blank') {
                return false;
            }
            if (anchor.hasAttribute('download')) {
                return false;
            }
            var href = anchor.getAttribute('href');
            if (!href || href.trim() === '' || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) {
                return false;
            }
            try {
                var u = new URL(anchor.href, window.location.href);
                if (u.origin !== window.location.origin) {
                    return false;
                }
                if (
                    u.pathname === window.location.pathname &&
                    u.search === window.location.search &&
                    u.hash !== ''
                ) {
                    return false;
                }
            } catch (e) {
                return false;
            }
            return true;
        }

        document.addEventListener(
            'click',
            function (e) {
                var a = e.target.closest('a');
                if (!a || !adminNavShouldShowForLink(a)) {
                    return;
                }
                adminNavShowLoading();
            },
            true
        );

        document.addEventListener(
            'submit',
            function (e) {
                var form = e.target;
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }
                if (form.hasAttribute('data-no-nav-loading')) {
                    return;
                }
                adminNavShowLoading();
            },
            true
        );

        window.addEventListener('pageshow', function () {
            adminNavHideLoading();
        });
    })();
</script>
