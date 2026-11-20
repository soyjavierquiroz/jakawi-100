<?php

namespace App\Models;

use Database\Factories\ExperienceSessionFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExperienceSession extends Model
{
    /** @use HasFactory<ExperienceSessionFactory> */
    use HasFactory;

    protected $fillable = ['experience_id', 'reference_key', 'location_id', 'reservation_partner_id', 'starts_at', 'ends_at', 'capacity', 'status', 'venue_label'];

    /** @return BelongsTo<Experience, $this> */
    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function reservationPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'reservation_partner_id');
    }

    // Timestamp columns store UTC. Explicit input offsets must not be discarded.
    public function setStartsAtAttribute($value): void
    {
        $this->attributes['starts_at'] = Carbon::parse($value, config('app.timezone'))->utc()->format($this->getDateFormat());
    }

    public function setEndsAtAttribute($value): void
    {
        $this->attributes['ends_at'] = $value === null ? null : Carbon::parse($value, config('app.timezone'))->utc()->format($this->getDateFormat());
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(ExperienceReservation::class);
    }

    public function confirmedAttendees(): int
    {
        return (int) $this->reservations()->where('status', ExperienceReservation::STATUS_CONFIRMED)->sum('party_size');
    }

    public function operationalEnd(): ?CarbonInterface
    {
        if ($this->ends_at) {
            return $this->ends_at->copy();
        }

        return $this->experience?->duration_minutes > 0 ? $this->starts_at->copy()->addMinutes($this->experience->duration_minutes) : null;
    }

    public function displayTimezone(): string
    {
        $timezone = $this->location?->timezone ?: config('app.timezone');
        try {
            new \DateTimeZone($timezone);
            return $timezone;
        } catch (\Exception) {
            return config('app.timezone');
        }
    }

    /** Serialize capacity edits with confirmations; keep historical responsibility intact. */
    public function save(array $options = [])
    {
        if (! $this->exists) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options) {
            static::query()->lockForUpdate()->findOrFail($this->id);
            if ($this->isDirty('capacity') && $this->capacity !== null && $this->capacity < $this->confirmedAttendees()) {
                throw ValidationException::withMessages(['capacity' => 'La capacidad no puede ser menor al número de personas confirmadas.']);
            }
            if ($this->isDirty('reservation_partner_id') && $this->reservations()->exists()) {
                throw ValidationException::withMessages(['reservation_partner_id' => 'No puede cambiarse el responsable de una sesión con reservas. Conserva su historial y prepara otra sesión.']);
            }
            return parent::save($options);
        });
    }

    /** Guard model deletion as well as the Admin route, without changing existing records. */
    public function delete()
    {
        return DB::transaction(function () {
            static::query()->lockForUpdate()->findOrFail($this->id);
            if ($this->reservations()->exists()) {
                throw ValidationException::withMessages(['session' => 'Esta sesión tiene reservas activas o históricas y no puede eliminarse.']);
            }
            return parent::delete();
        });
    }

    /** @param Builder<ExperienceSession> $query */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', 'scheduled')->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isUpcoming(): bool
    {
        return $this->isScheduled() && $this->starts_at->gte(now());
    }

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'capacity' => 'integer'];
    }
}
