<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The SPA always sends the user's chosen language; Indonesian is the default.
        // (Symfony's test client would otherwise send "en-us".)
        $this->withHeader('Accept-Language', 'id');
    }
}
