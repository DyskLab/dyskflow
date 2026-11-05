<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Numeric ids posted by a table's checkboxes. */
    protected function ids(Request $request): array
    {
        return collect((array) $request->input('ids', []))->filter(fn ($id) => ctype_digit((string) $id))->map(fn ($id) => (int) $id)->values()->all();
    }

    /** String keys posted by a table's checkboxes, for tables not backed by numeric ids. */
    protected function keys(Request $request): array
    {
        return collect((array) $request->input('ids', []))->filter(fn ($key) => is_string($key) && $key !== '')->values()->all();
    }
}
