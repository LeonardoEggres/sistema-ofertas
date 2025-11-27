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

    public function index(Request $request): JsonResponse
    {
        try {
            $filtros = [
                'q' => $request->input('q'),
                'page' => max(1, (int)$request->input('page', 1)),
                'per_page' => max(1, (int)$request->input('per_page', 25)),
            ];

            $resultado = $this->ebayService->buscarOfertas($filtros);

            return $this->corsResponse($resultado, 200);

        } catch (Exception $e) {
            return $this->corsResponse([
                'sucesso' => false,
                'erro' => 'Erro ao buscar ofertas: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destaque(): JsonResponse
    {
        try {
            $resultado = $this->ebayService->buscarOfertas([
                'q' => null,
                'page' => 1,
                'per_page' => 20,
            ]);

            return $this->corsResponse($resultado, 200);

        } catch (Exception $e) {
            return $this->corsResponse([
                'sucesso' => false,
                'erro' => 'Erro ao buscar destaques: ' . $e->getMessage()
            ], 500);
        }
    }


    public function testar(): JsonResponse
    {
        try {
            $conectado = $this->ebayService->testarConexao();

            return $this->corsResponse([
                'conectado' => $conectado,
                'mensagem' => $conectado
                    ? '✅ Conexão com eBay OK!'
                    : '❌ Erro ao conectar com eBay'
            ], 200);

        } catch (Exception $e) {
            return $this->corsResponse([
                'conectado' => false,
                'erro' => $e->getMessage()
            ], 500);
        }
    }

    public function categorias(): JsonResponse
    {
        try {
            $resultado = $this->ebayService->buscarCategorias();
            return $this->corsResponse($resultado, 200);

        } catch (Exception $e) {
            return $this->corsResponse([
                'sucesso' => false,
                'erro' => 'Erro ao buscar categorias: ' . $e->getMessage()
            ], 500);
        }
    }

    private function corsResponse(array $data, int $status = 200)
    {
        return response()->json($data, $status)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With')
            ->header('Access-Control-Allow-Credentials', 'true');
    }
}
