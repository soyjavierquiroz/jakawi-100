<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Str;
class ReferralCodeService { private const RESERVED = ['ADMIN','API','AUTH','LOGIN','REGISTER','R','SETTINGS','SUPPORT']; public function ensureFor(User $user): string { if ($user->referral_code) return $user->referral_code; $base = preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii(Str::before($user->name, ' ')))) ?: 'JAKAWI'; for ($i = 0; $i < 20; $i++) { $code = $base.($i ? random_int(10, 9999) : ''); if (in_array($code, self::RESERVED, true) || User::where('referral_code_normalized', $code)->exists()) continue; $user->forceFill(['referral_code' => $code, 'referral_code_normalized' => $code])->save(); return $code; } throw new \RuntimeException('Could not generate referral code.'); } }
