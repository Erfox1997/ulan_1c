<script>
    (function () {
        if (window.reserveBranchArticleCodes) {
            return;
        }
        window.reserveBranchArticleCodes = function (count) {
            count = parseInt(count, 10) || 0;
            if (count < 1) {
                return Promise.reject(new Error('Некорректное количество.'));
            }
            var tokenEl = document.querySelector('meta[name="csrf-token"]');
            var token = tokenEl ? tokenEl.getAttribute('content') : '';
            return fetch(@json(route('admin.goods.article-code-reserve')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ count: count }),
            }).then(function (res) {
                return res.json().then(function (body) {
                    if (!res.ok) {
                        var msg =
                            (body && (body.message || (body.errors && body.errors.count && body.errors.count[0]))) ||
                            res.statusText ||
                            'Ошибка сервера';
                        throw new Error(msg);
                    }
                    if (!body.codes || !body.codes.length) {
                        throw new Error('Пустой ответ.');
                    }
                    return body.codes;
                });
            });
        };
    })();
</script>
