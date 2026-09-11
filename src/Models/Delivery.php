<?php

namespace Grokbot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $table = 'grokbot_deliveries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function grokbot(): BelongsTo
    {
        return $this->belongsTo(Grokbot::class)->withTrashed();
    }
}
