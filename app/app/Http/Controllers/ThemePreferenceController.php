<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ThemePreferenceController extends Controller
{
    public function update(Request $request): Response
    {
        $data = $request->validate(['theme_preference' => ['required', 'string', 'in:system,light,dark']]);
        $request->user()->update($data);

        return response()->noContent();
    }
}
