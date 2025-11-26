<?php

namespace App\Http\Controllers;

use App\Http\Services\EbayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class MarketplaceController extends Controller
{
    protected EbayService $ebayService;

    public function __construct(EbayService $ebayService)
    {
        $this->ebayService = $ebayService;
    }

    /**
     * Listar ofertas do eBay
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = [
                'q' => $request->input('q'),
                'limit' => $request->input('limit', 100),
            ];

            $resultado = $this->ebayService->buscarOfertas($filtros);

            return response()->json($resultado, 200);

        } catch (Exception $e) {
            return response()->json([
                'sucesso' => false,
                'erro' => 'Erro ao buscar ofertas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ofertas em destaque
     */
    public function destaque(): JsonResponse
    {
        try {
            $resultado = $this->ebayService->buscarOfertas([
                'q' => null,
                'limit' => 20,
            ]);

            return response()->json($resultado, 200);

        } catch (Exception $e) {
            return response()->json([
                'sucesso' => false,
                'erro' => 'Erro ao buscar destaques: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testar conexão com API do eBay
     */
    public function testar(): JsonResponse
    {
        try {
            $conectado = $this->ebayService->testarConexao();

            return response()->json([
                'conectado' => $conectado,
                'mensagem' => $conectado
                    ? '✅ Conexão com eBay OK!'
                    : '❌ Erro ao conectar com eBay'
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'conectado' => false,
                'erro' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar categorias
     */
    public function categorias(): JsonResponse
    {
        try {
            $resultado = $this->ebayService->buscarCategorias();
            return response()->json($resultado, 200);

        } catch (Exception $e) {
            return response()->json([
                'sucesso' => false,
                'erro' => 'Erro ao buscar categorias: ' . $e->getMessage()
            ], 500);
        }
    }
}
