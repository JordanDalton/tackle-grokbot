<?php

namespace Grokbot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grokbot extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['webhook_url', 'bearer_token'];

    protected function casts(): array
    {
        return ['bearer_token' => 'encrypted', 'enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $bot) {
            $bot->forceFill(['bearer_token' => null, 'webhook_url' => null, 'enabled' => false])->save();
        });
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
