<?php

/**
 * Структура левого меню администратора филиала (ТЗ п. 5.5–5.10).
 * Ключи с префиксом _ ведут на заглушку admin.placeholder.
 */
return [
    [
        'id' => 'home',
        'label' => 'Главное',
        'type' => 'route',
        'route' => 'admin.dashboard',
        'icon' => 'home',
    ],
    [
        'id' => 'reports',
        'label' => 'Отчёты',
        'icon' => 'chart',
        'children' => [
            ['label' => 'Остатки товаров', 'route' => 'admin.reports.goods-stock', 'route_is' => 'admin.reports.goods-stock'],
            ['label' => 'Остатки задним числом', 'route' => 'admin.reports.goods-stock-historical', 'route_is' => 'admin.reports.goods-stock-historical'],
            ['label' => 'Движение товаров', 'route' => 'admin.reports.goods-movement', 'route_is' => 'admin.reports.goods-movement'],
            ['label' => 'Движение денег', 'route' => 'admin.reports.cash-movement', 'route_is' => 'admin.reports.cash-movement'],
            ['label' => 'Остатки по кассе и счетам', 'route' => 'admin.reports.cash-balances', 'route_is' => 'admin.reports.cash-balances'],
            ['label' => 'Продажи по товарам', 'route' => 'admin.reports.sales-by-goods', 'route_is' => 'admin.reports.sales-by-goods'],
            ['label' => 'Продажи по клиентам', 'route' => 'admin.reports.sales-by-clients', 'route_is' => 'admin.reports.sales-by-clients'],
            ['label' => 'Валовая прибыль', 'route' => 'admin.reports.gross-profit', 'route_is' => 'admin.reports.gross-profit'],
            ['label' => 'Расходы по категориям', 'route' => 'admin.reports.expenses-by-category', 'route_is' => 'admin.reports.expenses-by-category'],
            ['label' => 'Оборотно-сальдовая ведомость', 'route' => 'admin.reports.turnover', 'route_is' => 'admin.reports.turnover'],
            ['label' => 'Характеристики товаров', 'route' => 'admin.reports.goods-characteristics', 'route_is' => 'admin.reports.goods-characteristics*'],
        ],
    ],
    [
        'id' => 'bank',
        'label' => 'Банк и касса',
        'icon' => 'bank',
        'children' => [
            ['label' => 'Приход: оплата от покупателя', 'route' => 'admin.bank.income-client', 'route_is' => 'admin.bank.income-client*'],
            ['label' => 'Расход: оплата поставщику', 'route' => 'admin.bank.expense-supplier', 'route_is' => 'admin.bank.expense-supplier*'],
            ['label' => 'Расход: прочие', 'route' => 'admin.bank.expense-other', 'route_is' => 'admin.bank.expense-other*'],
            ['label' => 'Переводы между счетами', 'route' => 'admin.bank.transfers', 'route_is' => 'admin.bank.transfers*'],
            ['label' => 'Отчёт: движение денег за период', 'route' => 'admin.bank.report-movement', 'route_is' => 'admin.bank.report-movement'],
        ],
    ],
    [
        'id' => 'trade',
        'label' => 'Закупки и продажи',
        'icon' => 'cart',
        'children' => [
            ['label' => 'Поступление от поставщика', 'route' => 'admin.purchase-receipts.index', 'route_is' => 'admin.purchase-receipts.*'],
            ['label' => 'Заявки на закупку', 'route' => 'admin.purchase-requests.index', 'route_is' => 'admin.purchase-requests.*'],
            ['label' => 'Возврат поставщику', 'route' => 'admin.purchase-returns.index', 'route_is' => 'admin.purchase-returns.*'],
            ['label' => 'Продажа физлицам', 'route' => 'admin.retail-sales.index', 'route_is' => 'admin.retail-sales.*', 'route_is_not' => 'admin.retail-sales.debts'],
            ['label' => 'Должники (физ. лица)', 'route' => 'admin.retail-sales.debts', 'route_is' => 'admin.retail-sales.debts'],
            ['label' => 'Продажа юрлицам', 'route' => 'admin.legal-entity-sales.index', 'route_is' => 'admin.legal-entity-sales.*'],
            ['label' => 'Наименование услуг', 'route' => 'admin.sale-services.index', 'route_is' => 'admin.sale-services.*'],
            // sell* — шапка, позиции, сохранение; requests* — список, правка, проведение (отдельное право в роли)
            ['label' => 'Отправка заявки', 'route' => 'admin.service-sales.sell', 'route_is' => 'admin.service-sales.sell*'],
            ['label' => 'Оформление заявок', 'route' => 'admin.service-sales.requests', 'route_is' => 'admin.service-sales.requests*'],
            ['label' => 'Возврат от покупателя', 'route' => 'admin.customer-returns.index', 'route_is' => 'admin.customer-returns.*'],
            ['label' => 'Счёт на оплату', 'route' => 'admin.trade-invoices.index', 'route_is' => 'admin.trade-invoices.*'],
            ['label' => 'ЭСФ', 'route' => 'admin.esf.index', 'route_is' => 'admin.esf.*'],
            ['label' => 'Контрагенты', 'route' => 'admin.counterparties.index', 'route_is' => 'admin.counterparties.*'],
            ['label' => 'Сверка с контрагентами', 'route' => 'admin.reconciliation.index', 'route_is' => 'admin.reconciliation.*'],
        ],
    ],
    [
        'id' => 'stock',
        'label' => 'Запасы',
        'icon' => 'box',
        'children' => [
            ['label' => 'Товары: перемещение', 'route' => 'admin.stock.move', 'route_is' => 'admin.stock.move*'],
            ['label' => 'Товары: оприходование', 'route' => 'admin.stock.incoming', 'route_is' => 'admin.stock.incoming*'],
            ['label' => 'Товары: списание', 'route' => 'admin.stock.writeoff', 'route_is' => 'admin.stock.writeoff*'],
            ['label' => 'Товары: ревизия', 'route' => 'admin.stock.audit', 'route_is' => 'admin.stock.audit*'],
            ['label' => 'Товары: остатки', 'route' => 'admin.reports.goods-stock', 'route_is' => 'admin.reports.goods-stock'],
        ],
    ],
    [
        'id' => 'payroll',
        'label' => 'Зарплата и кадры',
        'icon' => 'users',
        'children' => [
            
            ['label' => 'Сотрудники', 'route' => 'admin.settings.employees', 'route_is' => 'admin.settings.employees*'],
            ['label' => 'Зарплата', 'route' => 'admin.payroll', 'route_is' => ['admin.payroll', 'admin.payroll.show', 'admin.payroll.pay-slip', 'admin.payroll.revoke-payout']],
            ['label' => 'Авансы', 'route' => 'admin.payroll.advances.index', 'route_is' => 'admin.payroll.advances.*'],
            ['label' => 'Штрафы', 'route' => 'admin.payroll.penalties.index', 'route_is' => 'admin.payroll.penalties.*'],
        ],
    ],
    [
        'id' => 'settings',
        'label' => 'Настройки',
        'icon' => 'cog',
        'children' => [
            ['label' => 'Данные организации', 'route' => 'admin.organizations.index', 'route_is' => 'admin.organizations.*'],
            ['label' => 'Ответственные лица и доступы', 'route' => 'admin.settings.responsible', 'route_is' => 'admin.settings.responsible*'],
             
            ['label' => 'Ввод начальных остатков', 'route' => 'admin.opening-balances.index', 'route_is' => 'admin.opening-balances.*'],
            ['label' => 'Справочники: склады', 'route' => 'admin.warehouses.index', 'route_is' => 'admin.warehouses.*'],
        ],
    ],

];
