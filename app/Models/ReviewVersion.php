<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['body', 'instruction', 'model'])]
class ReviewVersion extends Model {}
