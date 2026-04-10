<?php

namespace Tests\Feature;

use App\Exceptions\LicenseDeniedException;
use App\Exceptions\LicenseExpiredException;
use App\Exceptions\LicenseSuspendedException;
use App\Exceptions\LicenseSystemUnavailableException;
use App\Services\LicenseService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseUrl = rtrim(config('services.subscription.url', 'https://managed.cyberteconline.com'), '/');
    }

    // ─── authorize() ────────────────────────────────────────────────────────

    public function test_authorize_returns_data_when_allowed(): void
    {
        Http::fake([
            $this->baseUrl . '/api/internal/authorize' => Http::response([
                'allowed'   => true,
                'reason'    => null,
                'limit'     => 10,
                'current'   => 3,
                'remaining' => 7,
                'status'    => 'active',
            ], 200),
        ]);

        $service = app(LicenseService::class);
        $data = $service->authorize('create_user', 1);

        $this->assertTrue($data['allowed']);
        $this->assertEquals(7, $data['remaining']);
    }

    public function test_authorize_throws_license_denied_when_not_allowed(): void
    {
        Http::fake([
            $this->baseUrl . '/api/internal/authorize' => Http::response([
                'allowed' => false,
                'reason'  => 'Límite de usuarios activos alcanzado.',
                'status'  => 'active',
            ], 200),
        ]);

        $this->expectException(LicenseDeniedException::class);

        app(LicenseService::class)->authorize('create_user', 1);
    }

    public function test_authorize_throws_license_expired_when_status_expired(): void
    {
        Http::fake([
            $this->baseUrl . '/api/internal/authorize' => Http::response([
                'allowed' => false,
                'reason'  => null,
                'status'  => 'expired',
            ], 200),
        ]);

        $this->expectException(LicenseExpiredException::class);

        app(LicenseService::class)->authorize('create_user', 1);
    }

    public function test_authorize_throws_license_suspended_when_status_suspended(): void
    {
        Http::fake([
            $this->baseUrl . '/api/internal/authorize' => Http::response([
                'allowed' => false,
                'reason'  => null,
                'status'  => 'suspended',
            ], 200),
        ]);

        $this->expectException(LicenseSuspendedException::class);

        app(LicenseService::class)->authorize('create_area', 1);
    }

    public function test_authorize_throws_system_unavailable_when_server_returns_500(): void
    {
        Http::fake([
            $this->baseUrl . '/api/internal/authorize' => Http::response([], 500),
        ]);

        $this->expectException(LicenseSystemUnavailableException::class);

        app(LicenseService::class)->authorize('create_user', 1);
    }

    public function test_authorize_throws_system_unavailable_on_connection_error(): void
    {
        Http::fake([
            $this->baseUrl . '/api/internal/authorize' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $this->expectException(\Throwable::class);

        app(LicenseService::class)->authorize('create_user', 1);
    }

    // ─── reportUserActive() ─────────────────────────────────────────────────

    public function test_report_user_active_calls_usage_endpoint(): void
    {
        Http::fake([
            $this->baseUrl . '/api/internal/usage' => Http::response(['ok' => true], 200),
        ]);

        app(LicenseService::class)->reportUserActive();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/internal/usage')
                && $request->data()['metric'] === 'user_active'
                && $request->data()['value'] === 1;
        });
    }

    public function test_report_user_active_does_not_throw_on_server_error(): void
    {
        Http::fake([
            $this->baseUrl . '/api/internal/usage' => Http::response([], 500),
        ]);

        // Should not throw — a failed usage report is logged but does not break the flow
        app(LicenseService::class)->reportUserActive();

        $this->assertTrue(true);
    }
}
