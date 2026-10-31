<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MembershipBankImport
{
    public function import(UploadedFile $file, string $account, User $admin): array
    {
        abort_unless($admin->is_admin, 403);
        $hash = hash_file('sha256', $file->getRealPath());
        if (DB::table('membership_bank_imports')->where('sha256', $hash)->exists()) {
            return ['imported' => 0, 'duplicate' => true];
        }
        $handle = fopen($file->getRealPath(), 'r');
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if ($header) {
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
            }
            if ($header !== ['date', 'time', 'reference', 'description', 'amount', 'direction']) {
                $this->invalid('Cabecera requerida: date,time,reference,description,amount,direction');
            }
            $rows = [];
            $line = 1;
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if ($row === [null]) {
                    continue;
                }
                if (count($row) !== 6 || $line > 10001) {
                    $this->invalid("Fila $line inválida o límite de 10.000 movimientos excedido.");
                }
                [$date,$time,$ref,$description,$amount,$direction] = array_map('trim', $row);
                if (! in_array($direction, ['credit', 'debit'], true)) {
                    $this->invalid("Fila $line: direction debe ser credit o debit.");
                }
                if ($direction === 'debit') {
                    continue;
                }
                if (! preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) || ! CarbonImmutable::hasFormat($date, 'Y-m-d')
                    || ($time !== '' && (! preg_match('/\A\d{2}:\d{2}(:\d{2})?\z/', $time) || ! CarbonImmutable::hasFormat($time, strlen($time) === 5 ? 'H:i' : 'H:i:s')))
                    || ! preg_match('/\A\d{1,10}(\.\d{1,2})?\z/', $amount) || (float) $amount <= 0 || mb_strlen($ref) > 255 || mb_strlen($description) > 2000) {
                    $this->invalid("Fila $line: fecha, hora o importe inválido. Use YYYY-MM-DD, HH:MM[:SS] y punto decimal.");
                }
                $ref = MembershipBankCheckout::reference($ref);
                $amount = number_format((float) $amount, 2, '.', '');
                $time = $time === '' ? null : (strlen($time) === 5 ? $time.':00' : $time);
                // Reference/date/amount identifies the same credit across overlapping exports.
                // Without reference retain description/time; indistinguishable rows collapse conservatively.
                $identity = $ref !== null ? [$account, $date, $ref, $amount] : [$account, $date, $time, $description, $amount];
                $rows[] = ['fingerprint' => hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR)),
                    'paid_date' => $date, 'paid_time' => $time, 'reference' => $ref, 'description' => $description, 'amount' => $amount];
            }
        } finally {
            fclose($handle);
        }
        $path = 'statements/'.$hash.'.csv';
        Storage::disk('membership-private')->put($path, file_get_contents($file->getRealPath()));

        return DB::transaction(function () use ($rows, $hash, $account, $admin, $path) {
            $created = DB::table('membership_bank_imports')->insertOrIgnore(['sha256' => $hash, 'account_key' => $account, 'path' => $path, 'admin_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
            if (! $created) {
                return ['imported' => 0, 'duplicate' => true];
            }
            $importId = DB::table('membership_bank_imports')->where('sha256', $hash)->value('id');
            $count = 0;
            foreach ($rows as $row) {
                $count += DB::table('membership_bank_movements')->insertOrIgnore([...$row, 'import_id' => $importId, 'created_at' => now(), 'updated_at' => now()]);
            }
            AuditLog::create(['actor_user_id' => $admin->id, 'action' => 'membership_bank_imported', 'subject_type' => 'membership_bank_imports', 'subject_id' => $importId, 'metadata' => ['imported' => $count, 'duplicates' => count($rows) - $count]]);

            return ['imported' => $count, 'duplicate' => false];
        });
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['statement' => $message]);
    }
}
