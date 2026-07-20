<?php

namespace Tests\Feature;

use App\Services\Api\OpenApiService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    public function test_runtime_openapi_contract_covers_every_api_route_and_method(): void
    {
        $document = app(OpenApiService::class)->document();

        $this->assertSame('3.1.0', $document['openapi']);
        $this->assertNotEmpty($document['paths']);

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $path = '/'.preg_replace('/^api\//', '', $route->uri());

            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $this->assertArrayHasKey($path, $document['paths'], "Missing OpenAPI path {$path}");
                $this->assertArrayHasKey(strtolower($method), $document['paths'][$path], "Missing {$method} contract for {$path}");
            }
        }
    }

    public function test_openapi_endpoint_exposes_authentication_and_error_schemas(): void
    {
        $this->getJson('/api/v1/docs/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonStructure([
                'components' => [
                    'securitySchemes' => ['bearerAuth', 'cookieAuth'],
                    'schemas' => ['ApiError'],
                ],
            ]);
    }
}
