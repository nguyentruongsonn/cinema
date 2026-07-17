<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestContextService
{
    public function __construct(
        private readonly Request $request
    ) {
    }

    public function ip(): ?string
    {
        return $this->request->ip();
    }

    public function userAgent(): ?string
    {
        return $this->request->userAgent();
    }

    public function requestId(): ?string
    {
        $requestId = $this->request->header('X-Request-ID');

        if ($requestId !== null) {
            return substr((string) $requestId, 0, 36);
        }

        if (! $this->request->attributes->has('request_id')) {
            $this->request->attributes->set('request_id', (string) Str::uuid());
        }

        return $this->request->attributes->get('request_id');
    }
}
