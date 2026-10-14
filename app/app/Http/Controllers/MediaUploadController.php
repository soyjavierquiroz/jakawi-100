<?php

namespace App\Http\Controllers;

use App\Services\MediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MediaUploadController extends Controller
{
    public function store(Request $request, MediaUploadService $media)
    {
        $request->validate(['image' => ['required', 'file', 'max:10240']]);
        try {
            return response()->json(['token' => $media->temporary($request->file('image'), $request->user()->id)]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['image' => 'No pudimos procesar la imagen. Inténtalo de nuevo.']);
        }
    }
}
