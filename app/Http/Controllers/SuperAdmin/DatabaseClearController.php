<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseClearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DatabaseClearController extends Controller
{
    public function show(): View
    {
        return view('superadmin.clear-database', [
            'pageTitle' => 'Очистить базу',
        ]);
    }

    public function clear(Request $request, DatabaseClearService $clearer): RedirectResponse
    {
        $request->validate([
            'confirm_text' => ['required', 'string', 'in:ОЧИСТИТЬ'],
        ], [
            'confirm_text.in' => 'Введите слово ОЧИСТИТЬ заглавными буквами для подтверждения.',
        ]);

        $clearer->clearOperationalData();

        return redirect()
            ->route('superadmin.dashboard')
            ->with('status', 'Данные удалены. Сохранены: филиалы, склады, организации, пользователи и права доступа филиалов.');
    }
}
