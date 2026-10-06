<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramApplication extends Model
{
    public const SUBMITTED = 'SUBMITTED';
    public const CONTACTED = 'CONTACTED';
    public const QUALIFIED = 'QUALIFIED';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';

    protected $guarded = [];

    public static function statuses(): array { return [self::SUBMITTED, self::CONTACTED, self::QUALIFIED, self::APPROVED, self::REJECTED]; }
    public static function openStatuses(): array { return [self::SUBMITTED, self::CONTACTED, self::QUALIFIED]; }
    public static function programTypes(): array { return [ProgramEnrollment::TYPE_AFFILIATE, ProgramEnrollment::TYPE_CREATOR, ProgramEnrollment::TYPE_PROMOTER]; }
    protected function casts(): array { return ['attribution_snapshot' => 'array']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function attributionTouch(): BelongsTo { return $this->belongsTo(AttributionTouch::class); }
}
