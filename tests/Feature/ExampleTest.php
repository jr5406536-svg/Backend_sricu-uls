<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_redirige_a_la_documentacion(): void
    {
        $this->get('/')->assertRedirect('/api/documentation');
    }

    public function test_la_documentacion_swagger_responde(): void
    {
        $this->get('/api/documentation')
            ->assertOk()
            ->assertSee('swagger-ui', false);

        $this->get('/api/openapi.yaml')
            ->assertOk()
            ->assertSee('openapi: 3.0.3', false);
    }
}
