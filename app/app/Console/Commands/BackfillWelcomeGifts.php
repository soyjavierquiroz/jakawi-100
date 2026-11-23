<?php

namespace App\Console\Commands;

use App\Models\{Membership, User};
use App\Services\DigitalGifts;
use Illuminate\Console\Command;

class BackfillWelcomeGifts extends Command
{
    protected $signature = 'digital-gifts:backfill-welcome {--execute : Assign one right per active paid or pioneer member}';
    protected $description = 'Assign missing welcome rights without choosing or claiming a product';

    public function handle(DigitalGifts $gifts): int
    {
        $gifts->assertKindEnabled('welcome');
        $members = Membership::active()->where(fn ($q) => $q->where('type', Membership::TYPE_PIONEER)->orWhere('amount_paid', '>', 0))->pluck('user_id')->unique();
        foreach ($members as $id) {
            if (! $this->option('execute')) { $this->line('Eligible user '.$id); continue; }
            $gift = $gifts->backfillWelcome(User::findOrFail($id));
            if ($gift) $this->line('User '.$id.': welcome right '.$gift->id.' ('.$gift->status.')');
        }
        return self::SUCCESS;
    }
}
