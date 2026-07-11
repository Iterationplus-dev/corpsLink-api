<?php

namespace App\Models;

use Database\Factories\NextOfKinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'full_name', 'relationship', 'phone', 'alternate_phone', 'address', 'apply_to_all_bookings'])]
class NextOfKin extends Model
{
    /** @use HasFactory<NextOfKinFactory> */
    use HasFactory;

    /**
     * Eloquent's pluralizer treats "kin" as invariant (kin/kin), so the
     * guessed table name would be "next_of_kin" instead of "next_of_kins".
     */
    protected $table = 'next_of_kins';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'apply_to_all_bookings' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
