<?php

namespace App\Services;

use App\Exceptions\LicenseDeniedException;
use App\Exceptions\LicenseExpiredException;
use App\Exceptions\LicenseSuspendedException;
use App\Exceptions\LicenseSystemUnavailableException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    private function baseUrl(): string
    {
        return rtrim(config('services.subscription.url'), '/');
    }

    private function apiKey(): string
    {
        return config('services.subscription.key') ?? '';
    }

    /**
     * Autorizar una accion contra el Management System.
     * FAIL CLOSED: si el sistema no responde, se bloquea la accion.
     *
     * @throws LicenseSystemUnavailableException
     * @throws LicenseExpiredException
     * @throws LicenseDeniedException
     */
    public function authorize(string $action, int $quantity = 1): array
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey(),
        ])->timeout(10)->post($this->baseUrl() . '/api/internal/authorize', [
            'action'   => $action,
            'quantity' => $quantity,
        ]);

        if (!$response->successful()) {
            Log::error('License system unavailable', [
                'action' => $action,
                'status' => $response->status(),
            ]);
            throw new LicenseSystemUnavailableException();
        }

        $data = $response->json();

        $status = $data['status'] ?? null;

        if ($status === 'suspended') {
            Log::warning('License suspended - action blocked', ['action' => $action]);
            throw new LicenseSuspendedException();
        }

        if ($status === 'expired') {
            Log::warning('License expired - action blocked', ['action' => $action]);
            throw new LicenseExpiredException('La suscripción ha vencido. Para continuar usando el sistema, renueva tu plan.');
        }

        if (!($data['allowed'] ?? false)) {
            $reason = $data['reason'] ?? 'El límite del plan actual ha sido alcanzado. Para continuar, actualiza tu suscripción.';
            Log::warning('License denied', ['action' => $action, 'reason' => $reason]);
            throw new LicenseDeniedException($reason);
        }

        Log::info('License authorized', [
            'action'    => $action,
            'limit'     => $data['limit'] ?? null,
            'current'   => $data['current'] ?? null,
            'remaining' => $data['remaining'] ?? null,
        ]);

        return $data;
    }

    /**
     * Verificar que la suscripcion esta activa (para login).
     * Usa create_area porque solo comprueba estado sin consumir cuota.
     *
     * @throws LicenseExpiredException           si la suscripcion esta vencida o suspendida
     * @throws LicenseSystemUnavailableException si el sistema no responde (fail closed)
     */
    public function checkSubscriptionActive(): void
    {
        try {
            $this->authorize('create_area', 1);
        } catch (LicenseSuspendedException) {
            throw new LicenseSuspendedException('No puedes iniciar sesión: la suscripción está suspendida. Contacta al administrador.');
        } catch (LicenseExpiredException) {
            throw new LicenseExpiredException('No puedes iniciar sesión: la suscripción ha vencido. Contacta al administrador para renovarla.');
        } catch (LicenseSystemUnavailableException) {
            throw new LicenseSystemUnavailableException('No es posible verificar el estado de la suscripción en este momento. Intenta de nuevo más tarde.');
        }
    }

    /**
     * Reportar +1 usuario activo al Management System.
     * Solo llamar tras crear o reactivar un usuario exitosamente.
     */
    public function reportUserActive(): void
    {
        $response = Http::withHeaders([
            'X-API-Key' => $this->apiKey(),
        ])->timeout(10)->post($this->baseUrl() . '/api/internal/usage', [
            'metric' => 'user_active',
            'value'  => 1,
        ]);

        if ($response->successful()) {
            Log::info('Usage reported: user_active +1');
        } else {
            Log::error('Usage report failed', ['status' => $response->status()]);
        }
    }
}
