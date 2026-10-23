<?php

namespace App\Console\Commands;

use App\Services\Platform\RequestAggregates;
use Illuminate\Console\Command;
use InvalidArgumentException;

class AggregatePlatformRequests extends Command
{
    protected $signature = 'platform:aggregate-requests {--limit=5000 : At most 10000 raw request observations per invocation}';

    protected $description = 'Aggregate completed API minutes incrementally without retaining request payloads';

    public function handle(RequestAggregates $aggregates): int
    {
        if (! ctype_digit((string) $this->option('limit'))) {
            $this->error('Batch limit must be an integer.');

            return self::INVALID;
        }
        try {
            $this->line(json_encode($aggregates->run((int) $this->option('limit')), JSON_THROW_ON_ERROR));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::INVALID;
        }

        return self::SUCCESS;
    }
}
