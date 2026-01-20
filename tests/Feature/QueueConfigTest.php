<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class QueueConfigTest extends TestCase
{
    public function test_queue_connection_is_configured()
    {
        // Queue connection should be set (not null)
        $connection = Config::get('queue.default');
        $this->assertNotNull($connection);
    }

    public function test_queue_connection_is_valid()
    {
        // Should use database, redis, or sync (all valid for our use case)
        $connection = Config::get('queue.default');
        $this->assertContains($connection, ['database', 'redis', 'sync']);
    }
}
