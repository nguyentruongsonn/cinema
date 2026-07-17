<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\MovieResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\SeatResource;
use App\Http\Resources\ShowtimeResource;
use App\Http\Resources\UserResource;
use App\Models\Movie;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiResourceSecurityTest extends TestCase
{
    #[Test]
    public function user_resource_does_not_expose_authentication_secrets(): void
    {
        $data = $this->resolveResource(new UserResource(new User([
            'id' => 1,
            'name' => 'Jane User',
            'email' => 'jane@example.test',
            'password' => 'hashed-password',
            'remember_token' => 'remember-me',
        ])));

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    #[Test]
    public function payment_and_order_resources_do_not_expose_raw_gateway_payloads(): void
    {
        $paymentData = $this->resolveResource(new PaymentResource(new Payment([
            'id' => 10,
            'order_id' => 20,
            'method' => 'payos',
            'transaction_code' => 'txn_123',
            'gateway_order_code' => 123456,
            'amount' => 100000,
            'payload' => [
                'signature' => 'gateway-signature',
                'access_token' => 'secret-token',
                'card_number' => '4111111111111111',
            ],
            'status' => Payment::STATUS_PENDING,
        ])));

        $orderData = $this->resolveResource(new OrderResource(new Order([
            'id' => 20,
            'code' => 'ORD-SECURE',
            'gateway_order_code' => 123456,
            'payment_provider' => 'payos',
            'total_amount' => 100000,
            'payload' => [
                'access_token' => 'secret-token',
                'card_number' => '4111111111111111',
            ],
            'payment_status' => 'pending',
        ])));

        $this->assertArrayNotHasKey('payload', $paymentData);
        $this->assertArrayNotHasKey('payload', $orderData);
        $this->assertNotContains('access_token', $this->recursiveKeys($paymentData));
        $this->assertNotContains('card_number', $this->recursiveKeys($orderData));
    }

    #[Test]
    public function media_resources_hide_internal_upload_paths_when_public_urls_are_available(): void
    {
        $movieData = $this->resolveResource(new MovieResource(new Movie([
            'id' => 1,
            'title' => 'Safe Movie',
            'poster_path' => 'movies/posters/internal.webp',
            'banner_path' => 'movies/banners/internal.webp',
            'poster_url' => 'https://cdn.example.test/poster.webp',
            'manual_override_status' => 1,
        ])));

        $this->assertArrayNotHasKey('poster_path', $movieData);
        $this->assertArrayNotHasKey('banner_path', $movieData);
        $this->assertArrayNotHasKey('manual_override_status', $movieData);
        $this->assertArrayHasKey('poster_url', $movieData);
        $this->assertArrayHasKey('banner_url', $movieData);
    }

    #[Test]
    public function newly_added_resources_resolve_without_sensitive_defaults(): void
    {
        $resources = [
            new ShowtimeResource(new Showtime(['id' => 1, 'status' => true])),
            new SeatResource(new Seat(['id' => 2, 'label' => 'A1', 'status' => true])),
            new PostResource(new Post(['id' => 3, 'title' => 'News', 'content' => '<p>Public</p>'])),
        ];

        foreach ($resources as $resource) {
            $data = $this->resolveResource($resource);

            $this->assertNotContains('password', $this->recursiveKeys($data));
            $this->assertNotContains('remember_token', $this->recursiveKeys($data));
            $this->assertNotContains('access_token', $this->recursiveKeys($data));
            $this->assertNotContains('card_number', $this->recursiveKeys($data));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveResource(JsonResource $resource): array
    {
        return $resource->resolve(Request::create('/test'));
    }

    /**
     * @return array<int, string>
     */
    private function recursiveKeys(array $data): array
    {
        $keys = [];

        array_walk_recursive($data, function (mixed $value, string|int $key) use (&$keys): void {
            $keys[] = (string) $key;
        });

        return $keys;
    }
}
