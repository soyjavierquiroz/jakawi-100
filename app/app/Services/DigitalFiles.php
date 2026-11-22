<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DigitalFiles
{
    public function store(UploadedFile $file, string $kind): array
    {
        $pdf = $kind === 'products' || $kind === 'statements';
        $allowed = $pdf ? ['application/pdf'] : ['application/pdf', 'image/png', 'image/jpeg'];
        if (! $file->isValid() || $file->getSize() > 25 * 1024 * 1024 || ! in_array($file->getMimeType(), $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'Archivo no válido; máximo 25 MB.']);
        }
        $bytes = file_get_contents($file->getRealPath());
        if ($file->getMimeType() === 'application/pdf' && ! str_starts_with($bytes, '%PDF-')) {
            throw ValidationException::withMessages(['file' => 'El archivo no es un PDF válido.']);
        }
        $extension = match ($file->getMimeType()) {
            'application/pdf' => 'pdf','image/png' => 'png',default => 'jpg'
        };
        $path = $kind.'/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk('digital-private');
        $disk->put($path, $bytes);
        $hash = hash('sha256', $bytes);
        if (! hash_equals($hash, hash('sha256', $disk->get($path)))) {
            throw new \RuntimeException('No se pudo verificar el archivo privado.');
        }

        return ['path' => $path, 'sha256' => $hash];
    }

    public function response(string $path, string $name, bool $download = true)
    {
        $disk = Storage::disk('digital-private');
        abort_unless($disk->exists($path), 404);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox"];

        return $download ? $disk->download($path, $name, $headers) : $disk->response($path, $name, $headers);
    }
}
