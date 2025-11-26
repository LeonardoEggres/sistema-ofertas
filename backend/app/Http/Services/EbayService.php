<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayService
{
    private string $baseUrl = 'https://api.ebay.com/buy/browse/v1';
    private EbayAuthService $authService;
    private string $marketplaceId;

    public function __construct(EbayAuthService $authService)
    {
        $this->authService = $authService;
        $this->marketplaceId = config('services.ebay.marketplace_id');
    }

    /**
     * Buscar ofertas de múltiplas categorias
     */
    public function buscarOfertas(array $filtros = []): array
    {
        try {
            $accessToken = $this->authService->getValidAccessToken();

            if (!$accessToken) {
                Log::warning('Sem access token eBay, usando dados de exemplo');
                return $this->retornarProdutosExemplo(50);
            }

            $limit = $filtros['limit'] ?? 50;
            $termo = $filtros['q'] ?? null;

            // Se tem termo específico, buscar apenas esse
            if ($termo) {
                return $this->buscarPorTermo($accessToken, $termo, $limit);
            }

            // Se não tem termo, buscar de múltiplas categorias
            return $this->buscarMultiplasCategorias($accessToken, $limit);
        } catch (\Exception $e) {
            Log::error('Exceção ao buscar ofertas eBay', [
                'erro' => $e->getMessage()
            ]);
            return $this->retornarProdutosExemplo(50);
        }
    }

    /**
     * Buscar produtos de múltiplas categorias e mesclar resultados
     */
    private function buscarMultiplasCategorias(string $accessToken, int $limitTotal): array
    {
        $categorias = [
            'smartphone',
            'notebook',
            'smart tv',
            'gaming console',
            'book',
            'sporting goods',
            'camera',
            'headphones',
            'watch',
            'tablet'
        ];

        $todosProdutos = [];

        $produtosPorCategoria = 15;

        foreach ($categorias as $categoria) {
            try {

                $resultado = $this->buscarPorTermo($accessToken, $categoria, $produtosPorCategoria);

                if ($resultado['sucesso'] && !empty($resultado['produtos'])) {
                    $todosProdutos = array_merge($todosProdutos, $resultado['produtos']);
                }
            } catch (\Exception $e) {
                // ...
            }
        }

        $todosProdutos = array_slice($todosProdutos, 0, $limitTotal);

        usort($todosProdutos, function ($a, $b) {
            return $b['percentual_desconto'] <=> $a['percentual_desconto'];
        });

        return [
            'sucesso' => true,
            'total' => count($todosProdutos),
            'produtos' => $todosProdutos,
        ];
    }

    /**
     * Buscar por termo específico
     */
    private function buscarPorTermo(string $accessToken, string $termo, int $limit): array
    {
        Log::info('Buscando no eBay', ['termo' => $termo, 'limit' => $limit]);

        $url = "{$this->baseUrl}/item_summary/search?q=" . urlencode($termo) . "&limit={$limit}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'X-Ebay-C-Marketplace-Id' => $this->marketplaceId,
        ])->get("{$this->baseUrl}/item_summary/search", [
            'q' => $termo,
            'limit' => $limit,
        ]);

        $produtos = $dados['itemSummaries'] ?? [];

        $produtosComDesconto = array_filter($produtos, function ($produto) {
            return isset($produto['marketingPrice']);
        });

        $produtosFormatados = array_map(function ($produto) {
            return $this->formatarProduto($produto);
        }, $produtosComDesconto);

        if ($response->failed()) {
            Log::error('Erro na API eBay', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [
                'sucesso' => false,
                'produtos' => [],
            ];
        }

        $dados = $response->json();
        $produtos = $dados['itemSummaries'] ?? [];

        if (empty($produtos)) {
            return [
                'sucesso' => true,
                'total' => 0,
                'produtos' => [],
            ];
        }

        $produtosComDesconto = array_filter($produtos, function ($produto) {
            return isset($produto['marketingPrice']);
        });

        if (empty($produtosComDesconto)) {
            Log::info("Nenhum produto com 'marketingPrice' encontrado para o termo: {$termo}");
        }

        // Formatar produtos
        $produtosFormatados = array_map(function ($produto) {
            return $this->formatarProduto($produto);
        }, $produtosComDesconto);

        return [
            'sucesso' => true,
            'total' => count($produtosFormatados),
            'produtos' => $produtosFormatados,
        ];
    }

    /**
     * Buscar categorias
     */
    public function buscarCategorias(): array
    {
        return [
            'sucesso' => true,
            'categorias' => [
                ['id' => '15032', 'name' => 'Celulares e Smartphones'],
                ['id' => '171485', 'name' => 'Computadores e Notebooks'],
                ['id' => '11071', 'name' => 'TVs'],
                ['id' => '139973', 'name' => 'Games e Consoles'],
                ['id' => '267', 'name' => 'Livros'],
                ['id' => '888', 'name' => 'Artigos Esportivos'],
            ],
        ];
    }

    /**
     * Testar conexão
     */
    public function testarConexao(): bool
    {
        try {
            $token = $this->authService->getValidAccessToken();
            return $token !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Formatar produto
     */
    private function formatarProduto(array $produto): array
    {
        $precoAtual = isset($produto['price']['value'])
            ? (float) $produto['price']['value']
            : 0;

        $precoOriginal = isset($produto['marketingPrice']['originalPrice']['value'])
            ? (float) $produto['marketingPrice']['originalPrice']['value']
            : $precoAtual;

        $percentualDesconto = $this->calcularDesconto($precoOriginal, $precoAtual);
        $economia = $precoOriginal - $precoAtual;

        return [
            'id_externo' => $produto['itemId'] ?? uniqid(),
            'nome' => $produto['title'] ?? 'Sem título',
            'descricao' => $produto['shortDescription'] ?? null,
            'preco_atual' => $precoAtual,
            'preco_original' => $precoOriginal,
            'percentual_desconto' => $percentualDesconto,
            'economia' => $economia,
            'url' => $produto['itemWebUrl'] ?? null,
            'imagem' => $produto['image']['imageUrl'] ?? 'https://via.placeholder.com/300',
            'categoria_id_externo' => $produto['categories'][0]['categoryId'] ?? null,
            'em_estoque' => true,
            'quantidade_disponivel' => null,
            'quantidade_vendida' => null,
            'condicao' => $produto['condition'] ?? 'NEW',
            'frete_gratis' => isset($produto['shippingOptions'][0])
                && $produto['shippingOptions'][0]['shippingCostType'] === 'FREE',
            'avaliacao_media' => null,
            'total_avaliacoes' => null,
            'seller_id' => $produto['seller']['username'] ?? null,
            'seller_nome' => $produto['seller']['username'] ?? null,
            'atributos' => [],
        ];
    }

    /**
     * Calcular desconto
     */
    private function calcularDesconto(float $precoOriginal, float $precoAtual): float
    {
        if ($precoOriginal <= 0 || $precoAtual >= $precoOriginal) {
            return 0;
        }
        return round((($precoOriginal - $precoAtual) / $precoOriginal) * 100, 2);
    }

    /**
     * Produtos de exemplo
     */
    private function retornarProdutosExemplo(int $limit): array
    {
        $exemplos = [
            [
                'id_externo' => 'EBAY001',
                'nome' => 'iPhone 15 Pro Max 256GB',
                'descricao' => 'Último modelo Apple',
                'preco_atual' => 7999.99,
                'preco_original' => 9999.99,
                'percentual_desconto' => 20.0,
                'economia' => 2000.00,
                'url' => 'https://www.ebay.com.br',
                'imagem' => 'https://i.ebayimg.com/images/g/iphone.jpg',
                'categoria_id_externo' => '15032',
                'em_estoque' => true,
                'quantidade_disponivel' => 10,
                'quantidade_vendida' => 100,
                'condicao' => 'NEW',
                'frete_gratis' => true,
                'avaliacao_media' => 4.8,
                'total_avaliacoes' => 200,
                'seller_id' => 'applestore',
                'seller_nome' => 'Apple Store',
                'atributos' => [],
            ],
        ];

        return [
            'sucesso' => true,
            'total' => count($exemplos),
            'produtos' => array_slice($exemplos, 0, $limit),
            'aviso' => 'Dados de exemplo - API do eBay temporariamente indisponível',
        ];
    }
}
