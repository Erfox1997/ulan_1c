<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CashShift;
use Illuminate\Http\RedirectResponse;

trait RequiresOpenCashShift
{
    protected function redirectIfNoOpenCashShift(): ?RedirectResponse
    {
        $user = auth()->user();
        if ($user === null || $user->branch_id === null) {
            return redirect()
                ->route('admin.dashboard')
                ->withErrors(['shift' => 'Нет привязки к филиалу.']);
        }

        $branchId = (int) $user->branch_id;
        if (CashShift::query()->where('branch_id', $branchId)->where('user_id', $user->id)->open()->exists()) {
            return null;
        }

        return redirect()
            ->route('admin.dashboard')
            ->withErrors(['shift' => 'Сначала откройте свою кассовую смену на странице «Главное».']);
    }
}
