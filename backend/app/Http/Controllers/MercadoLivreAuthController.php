<?php

namespace App\Http\Controllers;

use App\Http\Services\MercadoLivreAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MercadoLivreAuthController extends Controller
{
    protected MercadoLivreAuthService $authService;

    public function __construct(MercadoLivreAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function authorize()
    {
        $url = $this->authService->getAuthorizationUrl();
        return redirect($url);
    }

    public function callback(Request $request): JsonResponse
    {
        $code = $request->input('code');

        if (!$code) {
            return response()->json([
                'erro' => 'Código de autorização não fornecido'
            ], 400);
        }

        $result = $this->authService->getAccessTokenFromCode($code);

        if (!$result) {
            return response()->json([
                'erro' => 'Erro ao obter access token'
            ], 500);
        }

        return response()->json([
            'sucesso' => true,
            'mensagem' => '✅ Autenticação realizada com sucesso!',
            'access_token' => substr($result['access_token'], 0, 20) . '...',
            'expires_in' => $result['expires_in'],
            'user_id' => $result['user_id'],
        ]);
    }

    public function getToken(): JsonResponse
    {
        $token = $this->authService->getValidAccessToken();

        if (!$token) {
            return response()->json([
                'erro' => 'Nenhum token válido. Autorize primeiro.',
                'authorize_url' => route('ml.authorize')
            ], 401);
        }

        return response()->json([
            'sucesso' => true,
            'access_token' => substr($token, 0, 20) . '...',
            'mensagem' => 'Token válido encontrado!',
        ]);
    }
}
