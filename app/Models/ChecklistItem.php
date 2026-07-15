<?php

namespace App\Models;

use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['category', 'label', 'sort_order'])]
class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('checked_at')
            ->withTimestamps();
    }
}
