<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EbayAuthService
{
    private string $base64Credentials;
    private string $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';

    public function __construct()
    {
        $this->base64Credentials = config('services.ebay.base64_credentials');
    }

    /**
     * Obter Access Token válido (do cache ou renovando)
     */
    public function getValidAccessToken(): ?string
    {
        $token = Cache::get('ebay_access_token');

        if ($token) {
            Log::info('eBay Access Token recuperado do cache');
            return $token;
        }

        return $this->requestAccessToken();
    }

    /**
     * Solicitar novo Access Token
     */
    private function requestAccessToken(): ?string
    {
        try {
            Log::info('Solicitando novo Access Token do eBay');

            $response = Http::asForm()
                ->withHeaders([
                    'Authorization' => "Basic {$this->base64Credentials}",
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ])
                ->post($this->tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'scope' => 'https://api.ebay.com/oauth/api_scope',
                ]);

            if ($response->failed()) {
                Log::error('Erro ao obter token eBay', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? 7200;

            Cache::put('ebay_access_token', $accessToken, now()->addSeconds($expiresIn - 300));

            Log::info('Access Token eBay obtido com sucesso', [
                'expires_in' => $expiresIn
            ]);

            return $accessToken;

        } catch (\Exception $e) {
            Log::error('Exceção ao obter token eBay', ['erro' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verificar se tem token válido
     */
    public function hasValidToken(): bool
    {
        return Cache::has('ebay_access_token');
    }

    /**
     * Limpar token (logout)
     */
    public function clearToken(): void
    {
        Cache::forget('ebay_access_token');
        Log::info('Token do eBay removido');
    }
}
