<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /** Aplikasi bisa di-boot dan endpoint health check merespons. */
    public function test_the_application_boots_and_health_check_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
