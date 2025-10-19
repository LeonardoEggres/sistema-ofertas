<?php

namespace App\Http\Controllers;

use App\Http\Services\MarketplaceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class MarketplaceController extends Controller
{
    protected MarketplaceService $mlService;

    public function __construct(MarketplaceService $mlService)
    {
        $this->mlService = $mlService;
    }
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = [
                'q' => $request->input('q'), // termo de busca
                'limit' => $request->input('limit', 20),
                'offset' => $request->input('offset', 0),
                'category' => $request->input('category'),
                'desconto_minimo' => $request->input('desconto_minimo'),
                'preco_min' => $request->input('preco_min'),
                'preco_max' => $request->input('preco_max'),
            ];

            $resultado = $this->mlService->buscarOfertas($filtros);

            return response()->json($resultado, 200);
        } catch (Exception $e) {
            return response()->json([
                'sucesso' => false,
                'erro' => 'Erro ao buscar ofertas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testar conexão com API
     */
    public function testar(): JsonResponse
    {
        try {
            $conectado = $this->mlService->testarConexao();

            return response()->json([
                'conectado' => $conectado,
                'mensagem' => $conectado
                    ? 'Conexão com Mercado Livre OK!'
                    : 'Erro ao conectar'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'conectado' => false,
                'erro' => $e->getMessage()
            ], 500);
        }
    }
}
