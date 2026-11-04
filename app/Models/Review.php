<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'place_id', 'place_name', 'place_address', 'questions', 'stars', 'length', 'tone'])]
class Review extends Model
{
    protected function casts(): array
    {
        return ['questions' => 'array', 'stars' => 'integer'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ReviewVersion::class);
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ReviewVersion::class)->latestOfMany();
    }
}
