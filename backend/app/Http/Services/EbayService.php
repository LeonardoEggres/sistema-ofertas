<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayService
{
    private string $baseUrl = 'https://api.ebay.com/buy/browse/v1';
    private EbayAuthService $authService;
    private string $marketplaceId;
    private ?float $taxaCambio = null;

    public function __construct(EbayAuthService $authService)
    {
        $this->authService = $authService;
        $this->marketplaceId = config('services.ebay.marketplace_id');
    }

    public function buscarOfertas(array $filtros = []): array
    {
        try {
            $accessToken = $this->authService->getValidAccessToken();

            if (!$accessToken) {
                Log::warning('Sem access token eBay, usando dados de exemplo');
                return $this->retornarProdutosExemplo(50);
            }

            $page = max(1, (int)($filtros['page'] ?? 1));
            $perPage = max(1, (int)($filtros['per_page'] ?? ($filtros['limit'] ?? 25)));
            $termo = $filtros['q'] ?? null;

            $offset = ($page - 1) * $perPage;

            if ($termo) {
                $desiredTotal = (int) max(ceil($perPage * 2.0), 10); // pedir mais para compensar filtro
                $resultado = $this->buscarPorTermo($accessToken, $termo, $desiredTotal, false); // false = apenas com desconto

                $produtos = $resultado['produtos'] ?? [];

                $termo_lower = strtolower($termo);
                $produtos_filtrados = array_filter($produtos, function ($p) use ($termo_lower) {
                    $nome_lower = strtolower($p['nome'] ?? '');
                    return strpos($nome_lower, $termo_lower) !== false;
                });
                $produtos_filtrados = array_values($produtos_filtrados);

                usort($produtos_filtrados, function ($a, $b) {
                    return ($b['percentual_desconto'] ?? 0) <=> ($a['percentual_desconto'] ?? 0);
                });

                $sliced = array_slice($produtos_filtrados, $offset, $perPage);

                return [
                    'sucesso' => $resultado['sucesso'] ?? true,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => count($sliced),
                    'produtos' => array_values($sliced),
                ];
            }

            return $this->buscarMultiplasCategorias($accessToken, $offset, $perPage);
        } catch (\Exception $e) {
            Log::error('Exceção ao buscar ofertas eBay', [
                'erro' => $e->getMessage()
            ]);
            return $this->retornarProdutosExemplo(50);
        }
    }

    private function buscarMultiplasCategorias(string $accessToken, int $offset, int $perPage): array
    {
        $page = (int) floor($offset / $perPage) + 1;
        if ($page <= 2) {
            $categorias = ['smartphone', 'notebook', 'smart tv'];
        } else {
            $categorias = $this->getCategoriasDefault();
        }

        $todosProdutos = [];

        $desiredTotal = (int) max(ceil($perPage * 1.5), 5);

        $catCount = count($categorias) ?: 1;
        $produtosPorCategoria = (int) max(ceil(($desiredTotal / $catCount) * 1.5), 5);

        $seenIds = [];

        foreach ($categorias as $categoria) {
            try {
                $resultado = $this->buscarPorTermo($accessToken, $categoria, $produtosPorCategoria, false);

                if ($resultado['sucesso'] && !empty($resultado['produtos'])) {
                    foreach ($resultado['produtos'] as $p) {
                        $id = $p['id_externo'] ?? null;
                        if ($id && isset($seenIds[$id])) {
                            continue;
                        }

                        if (isset($p['percentual_desconto']) && (float)$p['percentual_desconto'] > 0) {
                            $todosProdutos[] = $p;
                            if ($id) $seenIds[$id] = true;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Erro ao buscar categoria eBay', ['categoria' => $categoria, 'erro' => $e->getMessage()]);
            }
        }

        $todosProdutos = array_values($todosProdutos);
        usort($todosProdutos, function ($a, $b) {
            return ($b['percentual_desconto'] ?? 0) <=> ($a['percentual_desconto'] ?? 0);
        });


        $sliced = array_slice($todosProdutos, $offset, $perPage);

        return [
            'sucesso' => true,
            'page' => (int) floor($offset / $perPage) + 1,
            'per_page' => $perPage,
            'total' => count($sliced),
            'produtos' => array_values($sliced),
        ];
    }

    private function buscarPorTermo(string $accessToken, string $termo, int $limit, bool $allowWithoutDiscount = false): array
    {
        Log::info('Buscando no eBay', ['termo' => $termo, 'limit' => $limit]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'X-Ebay-C-Marketplace-Id' => $this->marketplaceId,
        ])->withOptions([
            'timeout' => 8,
            'connect_timeout' => 3,
        ])->get("{$this->baseUrl}/item_summary/search", [
            'q' => $termo,
            'limit' => $limit,
        ]);

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

        $produtosFiltrados = array_filter($produtos, function ($produto) {
            return isset($produto['marketingPrice']) && isset($produto['marketingPrice']['originalPrice']);
        });

        if (empty($produtosFiltrados)) {
            Log::info("Nenhum produto com desconto encontrado para o termo: {$termo}");
        }

        $produtosFormatados = array_map(function ($produto) {
            return $this->formatarProduto($produto);
        }, $produtosFiltrados);

        $produtosFormatados = array_filter($produtosFormatados, function ($p) {
            return isset($p['percentual_desconto']) && (float)$p['percentual_desconto'] > 0;
        });

        $produtosFormatados = array_values($produtosFormatados);

        return [
            'sucesso' => true,
            'total' => count($produtosFormatados),
            'produtos' => $produtosFormatados,
        ];
    }

    private function buscarCategoriasDisponiveis(): array
    {
        try {
            $accessToken = $this->authService->getValidAccessToken();

            if (!$accessToken) {
                Log::warning('Sem access token para buscar categorias');
                return $this->getCategoriasDefault();
            }

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'X-Ebay-C-Marketplace-Id' => $this->marketplaceId,
            ])->withOptions([
                'timeout' => 8,
                'connect_timeout' => 3,
            ])->get('https://api.ebay.com/commerce/taxonomy/v1/category_tree/EBAY-BR/get_category_suggestions', [
                'q' => '',
            ]);

            if ($response->failed()) {
                Log::warning('Erro ao buscar categorias da API eBay', ['status' => $response->status()]);
                return $this->getCategoriasDefault();
            }

            return $this->getCategoriasDefault();
        } catch (\Exception $e) {
            Log::warning('Exceção ao buscar categorias', ['erro' => $e->getMessage()]);
            return $this->getCategoriasDefault();
        }
    }

    public function buscarCategorias(): array
    {
        $raw = $this->buscarCategoriasDisponiveis();

        if (!empty($raw) && is_array($raw) && isset($raw[0]) && is_array($raw[0]) && isset($raw[0]['id'])) {
            return [
                'sucesso' => true,
                'categorias' => $raw,
            ];
        }

        $categorias = [];
        foreach ($raw as $idx => $name) {
            $categorias[] = [
                'id' => (string) ($idx + 1),
                'name' => $name,
            ];
        }

        return [
            'sucesso' => true,
            'categorias' => $categorias,
        ];
    }

    private function getCategoriasDefault(): array
    {
        return [
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
    }

    private function obterTaxaCambio(): float
    {
        if ($this->taxaCambio !== null) {
            return $this->taxaCambio;
        }

        try {
            $response = Http::withOptions([
                'timeout' => 5,
                'connect_timeout' => 3,
            ])->get('https://api.exchangerate-api.com/v4/latest/USD');

            if ($response->successful()) {
                $data = $response->json();
                $taxa = $data['rates']['BRL'] ?? 5.0;
                $this->taxaCambio = (float) $taxa;
                return $this->taxaCambio;
            }
        } catch (\Exception $e) {
            Log::warning('Erro ao obter taxa de câmbio', ['erro' => $e->getMessage()]);
        }

        $this->taxaCambio = 5.0;
        return $this->taxaCambio;
    }

    private function converterParaReal(float $valorEmDolar): float
    {
        $taxa = $this->obterTaxaCambio();
        return round($valorEmDolar * $taxa, 2);
    }

    public function testarConexao(): bool
    {
        try {
            $token = $this->authService->getValidAccessToken();
            return $token !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function formatarProduto(array $produto): array
    {
        $precoAtualUsd = isset($produto['price']['value'])
            ? (float) $produto['price']['value']
            : 0;

        $precoOriginalUsd = isset($produto['marketingPrice']['originalPrice']['value'])
            ? (float) $produto['marketingPrice']['originalPrice']['value']
            : $precoAtualUsd;

        $precoAtual = $this->converterParaReal($precoAtualUsd);
        $precoOriginal = $this->converterParaReal($precoOriginalUsd);

        $percentualDesconto = $this->calcularDesconto($precoOriginal, $precoAtual);
        $economia = $precoOriginal - $precoAtual;

        return [
            'id_externo' => $produto['itemId'] ?? uniqid(),
            'nome' => $produto['title'] ?? 'Sem título',
            'descricao' => $produto['shortDescription'] ?? null,
            'preco_atual' => $precoAtual,
            'preco_original' => $precoOriginal,
            'moeda' => 'BRL',
            'preco_original_usd' => $precoOriginalUsd,
            'preco_atual_usd' => $precoAtualUsd,
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

    private function calcularDesconto(float $precoOriginal, float $precoAtual): float
    {
        if ($precoOriginal <= 0 || $precoAtual >= $precoOriginal) {
            return 0;
        }
        return round((($precoOriginal - $precoAtual) / $precoOriginal) * 100, 2);
    }

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
