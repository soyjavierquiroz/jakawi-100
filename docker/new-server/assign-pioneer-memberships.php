<?php
// One-time, explicit COM correction authorized for memberships 1/Javier and 2/Janny.
// Execute from /var/www/html with this release loaded, after its schema migration.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Membership;
use Illuminate\Support\Facades\DB;

if (config('app.env') !== 'production' || config('database.connections.pgsql.database') !== 'jakawi_final') {
    throw new RuntimeException('This explicit correction is restricted to COM production.');
}
DB::transaction(function (): void {
    foreach ([1 => ['user' => 173, 'payment' => 3], 2 => ['user' => 176, 'payment' => 4]] as $id => $expected) {
        $m = DB::table('memberships')->where('id', $id)->lockForUpdate()->first();
        $payment = DB::table('membership_bank_payments')->where('id', $expected['payment'])->lockForUpdate()->first();
        if (! $m || ! $payment || $m->user_id !== $expected['user'] || $payment->membership_id !== $id
            || $payment->user_id !== $expected['user'] || $m->status !== 'active' || $payment->status !== 'approved'
            || $m->amount_paid !== '100.00' || $payment->amount !== '100.00') {
            throw new RuntimeException('Membership/payment preservation guard failed for '.$id);
        }
        if ($m->type === Membership::TYPE_PIONEER) {
            if (! DB::table('audit_logs')->where('subject_type', Membership::class)->where('subject_id', $id)->where('action', 'membership_type_pioneer_assigned')->exists()) {
                throw new RuntimeException('Existing pioneer assignment lacks its audit record.');
            }
            continue;
        }
        if ($m->type !== Membership::TYPE_STANDARD) throw new RuntimeException('Unexpected original type.');
        // Query builder deliberately avoids model observers and economic/CRM side effects.
        DB::table('memberships')->where('id', $id)->update(['type' => Membership::TYPE_PIONEER]);
        DB::table('audit_logs')->insert([
            'actor_user_id' => null, 'action' => 'membership_type_pioneer_assigned',
            'subject_type' => Membership::class, 'subject_id' => $id,
            'metadata' => json_encode([
                'reason' => 'Corrección Pionero autorizada por el titular de COM: membresías 1 Javier y 2 Janny; 15 meses calendario desde lanzamiento oficial aún sin fecha.',
                'executor' => 'COM release operation', 'before' => (array) $m,
                'after' => (array) DB::table('memberships')->where('id', $id)->first(),
                'historical_ends_at' => $m->ends_at, 'payment_id' => $payment->id,
                'payments_reprocessed' => false,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
});
echo "Pioneer assignments 1/2 recorded; payments and original membership fields preserved.\n";
