@props([
    'catalogItems',
    /** @var list<string> $selected */
    'selected' => [],
    'idPrefix' => 'p',
])
@php
    $grouped = collect($catalogItems)->groupBy('group');
@endphp
<div class="branch-permission-fields max-h-[min(70vh,520px)] space-y-5 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/80 p-4">
    @foreach ($grouped as $group => $items)
        @php($gid = md5($idPrefix.'|'.$group))
        <div class="permission-group space-y-2" data-permission-group="{{ $gid }}">
            <div class="flex items-start gap-2">
                <input
                    type="checkbox"
                    id="{{ $idPrefix }}-grp-{{ $gid }}"
                    class="perm-group-master mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
                    autocomplete="off"
                />
                <label
                    for="{{ $idPrefix }}-grp-{{ $gid }}"
                    class="cursor-pointer select-none text-[11px] font-bold uppercase tracking-wide text-slate-500"
                >{{ $group }} <span class="font-normal normal-case text-slate-400">(все)</span></label>
            </div>
            <ul class="space-y-2 pl-1">
                @foreach ($items as $item)
                    <li class="flex gap-2 text-sm leading-snug text-slate-800">
                        <input
                            type="checkbox"
                            name="permissions[]"
                            value="{{ $item['pattern'] }}"
                            id="{{ $idPrefix }}-perm-{{ md5($item['pattern']) }}"
                            class="perm-item mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
                            @checked(in_array($item['pattern'], $selected, true))
                        />
                        <label for="{{ $idPrefix }}-perm-{{ md5($item['pattern']) }}" class="cursor-pointer select-none">{{ $item['label'] }}</label>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
<p class="mt-2 text-xs text-slate-500">Шаблоны совпадают с именами маршрутов Laravel (как в <code class="rounded bg-slate-100 px-1">routeIs</code>): <code class="rounded bg-slate-100 px-1">*</code> — любой суффикс. Галочка у названия группы отмечает или снимает все пункты в блоке.</p>
<script>
    (function () {
        function syncMaster(group) {
            var master = group.querySelector('.perm-group-master');
            var boxes = group.querySelectorAll('input[name="permissions[]"]');
            if (!master || !boxes.length) {
                return;
            }
            var n = 0;
            for (var i = 0; i < boxes.length; i++) {
                if (boxes[i].checked) {
                    n++;
                }
            }
            master.checked = n === boxes.length;
            master.indeterminate = n > 0 && n < boxes.length;
        }

        document.querySelectorAll('.branch-permission-fields').forEach(function (root) {
            root.querySelectorAll('.permission-group').forEach(function (group) {
                syncMaster(group);
                var master = group.querySelector('.perm-group-master');
                if (master) {
                    master.addEventListener('change', function () {
                        var on = master.checked;
                        group.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
                            cb.checked = on;
                        });
                        master.indeterminate = false;
                    });
                }
                group.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        syncMaster(group);
                    });
                });
            });
        });
    })();
</script>
