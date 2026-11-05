<?php

namespace Tests\Unit\Support;

use PDO;
use Tests\TestCase;

class DatabaseConfigurationTest extends TestCase
{
    public function test_postgres_emulates_prepared_statements_for_pooled_neon_connections(): void
    {
        $options = config('database.connections.pgsql.options');

        $this->assertIsArray($options);
        $this->assertTrue($options[PDO::ATTR_EMULATE_PREPARES] ?? false);
    }
}
