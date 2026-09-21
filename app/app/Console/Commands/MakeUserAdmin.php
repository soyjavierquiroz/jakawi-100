<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    protected $signature = 'user:make-admin {email}';

    protected $description = 'Mark an existing user as an administrator.';

    public function handle(): int
    {
        $user = User::query()
            ->where('email', $this->argument('email'))
            ->first();

        if (! $user) {
            $this->error('No user exists with that email.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => true])->save();

        $this->info('User marked as administrator.');

        return self::SUCCESS;
    }
}
