<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MembershipBankQr
{
    private const KEY = 'membership_bank_qr';

    private const DISK = 'minio-media';

    public function current(): ?array
    {
        $value = AppSetting::where('key', self::KEY)->first()?->value;
        if (! is_array($value)
            || ! preg_match('~\Amemberships/bank-qr/[a-f0-9-]{36}\.(png|jpg)\z~', $value['path'] ?? '')
            || ! in_array($value['mime'] ?? null, ['image/png', 'image/jpeg'], true)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $value['sha256'] ?? '')
            || ! is_int($value['size'] ?? null) || $value['size'] < 1 || $value['size'] > 10 * 1024 * 1024) {
            return null;
        }
        try {
            $disk = Storage::disk(self::DISK);
            if (! $disk->exists($value['path']) || $disk->size($value['path']) !== $value['size']) {
                return null;
            }
            // Verify the exact original before enabling collection or serving it.
            $bytes = $disk->get($value['path']);
            if (! hash_equals($value['sha256'], hash('sha256', $bytes))) {
                return null;
            }

            return [...$value, 'bytes' => $bytes];
        } catch (Throwable) {
            // A missing/unavailable/corrupt object never enables collection.
            return null;
        }
    }

    public function ready(): bool
    {
        return $this->current() !== null;
    }

    public function recipient(): string
    {
        return AppSetting::where('key', 'membership_bank_qr_recipient')->first()?->value['name']
            ?? 'Javier Enrrique Marcos Quiroz Sueldo';
    }

    public function saveRecipient(string $name, User $admin): void
    {
        abort_unless($admin->is_admin, 403);
        DB::transaction(function () use ($name, $admin) {
            $setting = AppSetting::updateOrCreate(['key' => 'membership_bank_qr_recipient'], ['value' => ['name' => trim($name)]]);
            AuditLog::create(['actor_user_id' => $admin->id, 'action' => 'membership_bank_qr_recipient_saved',
                'subject_type' => AppSetting::class, 'subject_id' => $setting->id]);
        });
    }

    public function response(bool $download = false)
    {
        $image = $this->current();
        abort_unless($image, 404);

        return response($image['bytes'], 200, [
            'Content-Type' => $image['mime'], 'Content-Length' => (string) $image['size'],
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="qr-membresia.'.($image['mime'] === 'image/png' ? 'png' : 'jpg').'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function save(UploadedFile $file, User $admin): void
    {
        abort_unless($admin->is_admin, 403);
        $size = $file->getSize();
        $dimensions = @getimagesize($file->getRealPath());
        if (! $size || $size > 10 * 1024 * 1024 || ! $dimensions
            || ! in_array($dimensions[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)
            || $dimensions[0] < 128 || $dimensions[1] < 128
            || $dimensions[0] > 4096 || $dimensions[1] > 4096) {
            throw ValidationException::withMessages(['qr_image' => 'Sube un PNG/JPG válido de 128 a 4096 píxeles por lado y máximo 10 MB.']);
        }
        $bytes = file_get_contents($file->getRealPath());
        $decoded = @imagecreatefromstring($bytes);
        if ($decoded === false) {
            throw ValidationException::withMessages(['qr_image' => 'La imagen está dañada o no se puede abrir.']);
        }
        unset($decoded); // Decode only to validate; never re-encode or resize the original.
        $mime = $dimensions[2] === IMAGETYPE_PNG ? 'image/png' : 'image/jpeg';
        $path = 'memberships/bank-qr/'.Str::uuid().($mime === 'image/png' ? '.png' : '.jpg');
        $value = ['path' => $path, 'mime' => $mime, 'size' => strlen($bytes),
            'width' => $dimensions[0], 'height' => $dimensions[1], 'sha256' => hash('sha256', $bytes)];
        try {
            $disk = Storage::disk(self::DISK);
            $disk->put($path, $bytes, ['visibility' => 'private', 'ContentType' => $mime]);
            if (! hash_equals($value['sha256'], hash('sha256', $disk->get($path)))) {
                throw new \RuntimeException('QR integrity mismatch');
            }
            DB::transaction(function () use ($value, $admin) {
                $setting = AppSetting::updateOrCreate(['key' => self::KEY], ['value' => $value]);
                AuditLog::create(['actor_user_id' => $admin->id, 'action' => 'membership_bank_qr_saved',
                    'subject_type' => AppSetting::class, 'subject_id' => $setting->id,
                    'metadata' => ['sha256' => $value['sha256'], 'mime' => $value['mime'], 'size' => $value['size']]]);
            });
        } catch (Throwable $error) {
            try {
                Storage::disk(self::DISK)->delete($path);
            } catch (Throwable) {
            }
            Log::warning('Membership bank QR save failed.', ['exception_type' => get_class($error)]);
            throw ValidationException::withMessages(['qr_image' => 'No se pudo guardar la imagen. La configuración anterior se conserva; vuelve a intentarlo.']);
        }
    }
}
