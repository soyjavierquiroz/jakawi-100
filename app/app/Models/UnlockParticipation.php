<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UnlockParticipation extends Model { public const INTERESTED='INTERESTED', COMMITTED='COMMITTED', UNLOCKED_PENDING_CONFIRMATION='UNLOCKED_PENDING_CONFIRMATION', CONFIRMED='CONFIRMED', FULFILLED='FULFILLED', CANCELLED_ON_TIME='CANCELLED_ON_TIME', NO_SHOW='NO_SHOW', EXPIRED='EXPIRED', REMOVED='REMOVED', WAITLISTED='WAITLISTED'; protected $guarded=[]; protected function casts(): array { return ['interested_at'=>'datetime','committed_at'=>'datetime','cancelled_at'=>'datetime']; } }
