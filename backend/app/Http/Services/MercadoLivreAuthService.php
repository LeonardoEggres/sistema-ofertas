<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MercadoLivreAuthService
{
    private string $appId;
    private string $secretKey;
    private string $redirectUri;

    public function __construct()
    {
        $this->appId = config('services.mercadolivre.app_id');
        $this->secretKey = config('services.mercadolivre.secret_key');
        $this->redirectUri = config('services.mercadolivre.redirect_uri');
    }

    public function getAuthorizationUrl(): string
    {
        return "https://auth.mercadolivre.com.br/authorization?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->appId,
            'redirect_uri' => $this->redirectUri,
        ]);
    }

    public function getAccessTokenFromCode(string $code): ?array
    {
        try {
            Log::info('Trocando CODE por Access Token', ['code' => substr($code, 0, 20) . '...']);

            $response = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'authorization_code',
                'client_id' => $this->appId,
                'client_secret' => $this->secretKey,
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
            ]);

            if ($response->failed()) {
                Log::error('Erro ao trocar CODE', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            Cache::put('ml_access_token', $data['access_token'], now()->addSeconds($data['expires_in'] - 300));
            Cache::put('ml_refresh_token', $data['refresh_token'], now()->addDays(180));

            Log::info('Tokens obtidos com sucesso!', [
                'user_id' => $data['user_id'],
                'expires_in' => $data['expires_in']
            ]);

            return $data;

        } catch (\Exception $e) {
            Log::error('Exceção ao trocar CODE', ['erro' => $e->getMessage()]);
            return null;
        }
    }

    public function refreshAccessToken(string $refreshToken): ?array
    {
        try {
            Log::info('Renovando Access Token');

            $response = Http::asForm()->post('https://api.mercadolibre.com/oauth/token', [
                'grant_type' => 'refresh_token',
                'client_id' => $this->appId,
                'client_secret' => $this->secretKey,
                'refresh_token' => $refreshToken,
            ]);

            if ($response->failed()) {
                Log::error('Erro ao renovar token', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            Cache::put('ml_access_token', $data['access_token'], now()->addSeconds($data['expires_in'] - 300));
            Cache::put('ml_refresh_token', $data['refresh_token'], now()->addDays(180));

            Log::info('Token renovado com sucesso!');

            return $data;

        } catch (\Exception $e) {
            Log::error('Exceção ao renovar token', ['erro' => $e->getMessage()]);
            return null;
        }
    }

    public function getValidAccessToken(): ?string
    {
        $accessToken = Cache::get('ml_access_token');

        if ($accessToken) {
            Log::info('Access Token válido encontrado no cache');
            return $accessToken;
        }

        $refreshToken = Cache::get('ml_refresh_token');

        if (!$refreshToken) {
            Log::warning('Nenhum Refresh Token disponível. Necessário autorizar novamente.');
            return null;
        }

        $result = $this->refreshAccessToken($refreshToken);

        return $result['access_token'] ?? null;
    }

    public function hasValidToken(): bool
    {
        return Cache::has('ml_access_token') || Cache::has('ml_refresh_token');
    }

    public function clearTokens(): void
    {
        Cache::forget('ml_access_token');
        Cache::forget('ml_refresh_token');
        Log::info('Tokens do Mercado Livre removidos');
    }
}
