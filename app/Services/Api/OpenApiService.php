<?php

declare(strict_types=1);

namespace App\Services\Api;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class OpenApiService
{
    public function document(): array
    {
        $paths = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $path = '/'.preg_replace('/^api\//', '', $route->uri());

            foreach ($this->methods($route) as $method) {
                $paths[$path][strtolower($method)] = $this->operation($route, $method);
            }
        }

        ksort($paths);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => config('app.name').' API',
                'version' => '1.0.0',
                'description' => 'Runtime-synchronized contract for the Cinema REST API.',
            ],
            'servers' => [['url' => url('/api')]],
            'paths' => $paths,
            'components' => $this->components(),
        ];
    }

    private function operation(IlluminateRoute $route, string $method): array
    {
        $middleware = $route->gatherMiddleware();
        $authenticated = in_array('auth:api', $middleware, true)
            || collect($middleware)->contains(fn ($item) => str_contains((string) $item, 'Authenticate'));
        $tag = $this->tag($route->uri());
        $operation = [
            'tags' => [$tag],
            'operationId' => $this->operationId($route, $method),
            'summary' => Str::headline($method.' '.$tag),
            'parameters' => $this->parameters($route),
            'responses' => [
                '200' => [
                    'description' => 'Successful response',
                    'content' => [
                        'application/json' => [
                            'schema' => ['type' => 'object', 'additionalProperties' => true],
                        ],
                    ],
                ],
                '422' => [
                    'description' => 'Validation failed',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => '#/components/schemas/ApiError'],
                        ],
                    ],
                ],
            ],
        ];

        if ($authenticated) {
            $operation['security'] = [['bearerAuth' => []], ['cookieAuth' => []]];
            $operation['responses']['401'] = ['description' => 'Unauthenticated'];
            $operation['responses']['403'] = ['description' => 'Forbidden'];
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                ],
            ];
        }

        return $operation;
    }

    private function parameters(IlluminateRoute $route): array
    {
        return collect($route->parameterNames())
            ->map(fn (string $name) => [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ])
            ->values()
            ->all();
    }

    private function methods(IlluminateRoute $route): array
    {
        return array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
    }

    private function tag(string $uri): string
    {
        $segments = explode('/', preg_replace('/^api\/v\d+\//', '', $uri));

        return Str::headline($segments[0] ?: 'system');
    }

    private function operationId(IlluminateRoute $route, string $method): string
    {
        $identity = $route->getName() ?: $method.'_'.$route->uri();

        return Str::camel(preg_replace('/[^A-Za-z0-9]+/', '_', $identity));
    }

    private function components(): array
    {
        return [
            'securitySchemes' => [
                'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
                'cookieAuth' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'access_token'],
            ],
            'schemas' => [
                'ApiError' => [
                    'type' => 'object',
                    'required' => ['success', 'message'],
                    'properties' => [
                        'success' => ['type' => 'boolean', 'const' => false],
                        'message' => ['type' => 'string'],
                        'errors' => ['type' => ['object', 'null'], 'additionalProperties' => true],
                        'request_id' => ['type' => ['string', 'null']],
                    ],
                ],
            ],
        ];
    }
}
