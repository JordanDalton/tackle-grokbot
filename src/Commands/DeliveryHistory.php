<?php

namespace Grokbot\Commands;

use Grokbot\Models\Delivery;
use Illuminate\Console\Command;

class DeliveryHistory extends Command
{
    protected $signature = 'grokbot:history {--bot= : Bot ID, including removed bots} {--page=1}';

    protected $description = 'Show paginated delivery history including sanitized payloads';

    public function handle(): int
    {
        $query = Delivery::query();
        if ($this->option('bot') !== null) {
            $query->where('grokbot_id', $this->option('bot'));
        }
        $rows = $query->latest('id')->forPage(max(1, (int) $this->option('page')), 25)->get();
        $this->table(['ID', 'Bot ID', 'Status', 'HTTP', 'Payload', 'Error', 'Created'], $rows->map(fn ($d) => [$d->id, $d->grokbot_id, $d->status, $d->http_status, json_encode($d->payload), $d->error, (string) $d->created_at])->all());

        return self::SUCCESS;
    }
}
