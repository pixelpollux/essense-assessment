<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Any HTTP request a test hasn't faked with Http::fake() throws,
        // so no test can ever reach the real Essense API.
        Http::preventStrayRequests();
    }
}
