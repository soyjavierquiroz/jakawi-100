<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedPreviewQaCommand extends Command
{
    protected $signature = 'preview:seed-qa';

    protected $description = 'Create the ephemeral QA accounts in the preview database.';

    public function handle(): int
    {
        if (config('database.connections.pgsql.database') !== 'jakawi_preview') {
            $this->error('This command only runs against jakawi_preview.');

            return self::FAILURE;
        }

        $partnerPassword = env('JAKAWI_QA_PARTNER_PASSWORD');
        $javierPassword = env('JAKAWI_QA_JAVIER_PASSWORD');

        if (! $partnerPassword || ! $javierPassword) {
            $this->error('Set JAKAWI_QA_PARTNER_PASSWORD and JAKAWI_QA_JAVIER_PASSWORD for this command.');

            return self::FAILURE;
        }

        $partner = Partner::where('slug', 'demo-altura-nube')->firstOrFail();
        $partnerUser = User::updateOrCreate(
            ['email' => 'qa.partner.demo@jakawi.test'],
            ['name' => 'Partner QA', 'email_verified_at' => now(), 'password' => Hash::make($partnerPassword), 'is_admin' => false],
        );
        $partnerUser->partners()->syncWithoutDetaching([$partner->id => ['role' => 'manager']]);

        $javier = User::updateOrCreate(
            ['email' => 'javierquiroztv@gmail.com'],
            ['name' => 'Javier QA', 'email_verified_at' => now(), 'password' => Hash::make($javierPassword), 'is_admin' => true],
        );
        Membership::where('user_id', $javier->id)->delete();
        Membership::create([
            'user_id' => $javier->id,
            'status' => Membership::STATUS_ACTIVE,
            'starts_at' => now(),
            'ends_at' => now()->addDays(365),
            'amount_paid' => '100.00',
            'payment_method' => 'qa',
            'notes' => 'Preview QA membership',
        ]);

        $this->info('Preview QA accounts normalized.');

        return self::SUCCESS;
    }
}
