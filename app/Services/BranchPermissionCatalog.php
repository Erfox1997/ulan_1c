<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Список разделов для «галочек» при настройке роли: метка + шаблон имени маршрута (как в routeIs).
 */
class BranchPermissionCatalog
{
    /**
     * @return list<array{group: string, label: string, pattern: string}>
     */
    public function items(): array
    {
        $items = [];

        $items[] = ['group' => 'Общее', 'label' => 'Главная', 'pattern' => 'admin.dashboard'];
        $items[] = ['group' => 'Общее', 'label' => 'Смена кассы', 'pattern' => 'admin.cash-shifts.*'];
        $items[] = ['group' => 'Общее', 'label' => 'API: поиск товаров и категорий', 'pattern' => 'admin.goods.*'];
        $items[] = ['group' => 'Общее', 'label' => 'API: контрагенты поиск и быстрый ввод', 'pattern' => 'admin.counterparties.search'];
        $items[] = ['group' => 'Общее', 'label' => 'API: подсказки должников (розница)', 'pattern' => 'admin.retail-sales.debtor-hints'];
        $items[] = ['group' => 'Общее', 'label' => 'Розница: единая оплата долга по группе', 'pattern' => 'admin.retail-sales.pay-debt-group'];
        $items[] = ['group' => 'Общее', 'label' => 'Розница: возврат по чеку из истории (данные)', 'pattern' => 'admin.retail-sales.return-data'];
        $items[] = ['group' => 'Общее', 'label' => 'Розница: провести возврат по чеку', 'pattern' => 'admin.retail-sales.return-from-sale'];
        $items[] = ['group' => 'Общее', 'label' => 'API: быстрый ввод контрагента', 'pattern' => 'admin.counterparties.quick-store'];
        $items[] = ['group' => 'Общее', 'label' => 'API: автомобили клиента (заявки на продажу)', 'pattern' => 'admin.customer-vehicles.*'];

        foreach (config('branch_menu', []) as $section) {
            $group = $section['label'] ?? '—';

            if (($section['type'] ?? null) === 'route') {
                $route = $section['route'] ?? null;
                if ($route) {
                    $items[] = [
                        'group' => $group,
                        'label' => $section['label'] ?? $route,
                        'pattern' => $this->inferPatternFromRouteName($route),
                    ];
                }

                continue;
            }

            foreach ($section['children'] ?? [] as $child) {
                $label = $child['label'] ?? '';
                if (isset($child['route_is'])) {
                    $routeIs = $child['route_is'];
                    $patterns = is_array($routeIs) ? $routeIs : [$routeIs];
                    foreach ($patterns as $p) {
                        if (! is_string($p) || $p === '') {
                            continue;
                        }
                        $items[] = ['group' => $group, 'label' => $label, 'pattern' => $p];
                    }

                    continue;
                }
                if (isset($child['route'])) {
                    $items[] = [
                        'group' => $group,
                        'label' => $label,
                        'pattern' => $this->inferPatternFromRouteName($child['route']),
                    ];

                    continue;
                }
                if (isset($child['key'])) {
                    $items[] = [
                        'group' => $group,
                        'label' => $label,
                        'pattern' => 'placeholder:'.$child['key'],
                    ];
                }
            }
        }

        $uniq = [];
        $out = [];
        foreach ($items as $row) {
            $k = $row['pattern'];
            if (isset($uniq[$k])) {
                continue;
            }
            $uniq[$k] = true;
            $out[] = $row;
        }

        return $out;
    }

    private function inferPatternFromRouteName(string $routeName): string
    {
        if (Str::contains($routeName, '*')) {
            return $routeName;
        }

        if (substr_count($routeName, '.') <= 1) {
            return $routeName;
        }

        return Str::beforeLast($routeName, '.').'.*';
    }
}
