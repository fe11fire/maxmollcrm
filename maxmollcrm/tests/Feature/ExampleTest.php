<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_api_returns_a_successful_response(): void
    {
        $this->get('/api/v1/test')->assertOk()->assertSeeText('hello');
    }
}
