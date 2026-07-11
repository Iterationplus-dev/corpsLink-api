<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['institution_id', 'name', 'pickup_point', 'destination', 'departure_at', 'fare', 'capacity', 'is_active'])]
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Every vehicle always has its seats — factories, seeders, and any
        // future admin-create endpoint all get correct seats for free.
        static::created(function (Vehicle $vehicle) {
            $seats = collect(range(1, $vehicle->capacity))->map(fn (int $number) => [
                'vehicle_id' => $vehicle->id,
                'seat_number' => $number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Seat::query()->insert($seats->all());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'fare' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return HasMany<Seat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true)->where('departure_at', '>', now());
    }

    public function badge(int $filledCount): string
    {
        $remaining = $this->capacity - $filledCount;

        return match (true) {
            $remaining <= 0 => 'full',
            $remaining <= (int) ceil($this->capacity * 0.2) => 'filling_fast',
            default => 'open',
        };
    }
}
