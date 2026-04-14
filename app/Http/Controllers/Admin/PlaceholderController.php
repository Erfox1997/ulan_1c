<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PlaceholderController extends Controller
{
    public function show(string $key): View
    {
        $title = $this->titleForKey($key);

        return view('admin.placeholder', [
            'title' => $title,
            'key' => $key,
        ]);
    }

    private function titleForKey(string $key): string
    {
        foreach (config('branch_menu', []) as $item) {
            foreach ($item['children'] ?? [] as $child) {
                if (($child['key'] ?? '') === $key) {
                    return $child['label'];
                }
            }
        }

        abort(404);
    }
}
