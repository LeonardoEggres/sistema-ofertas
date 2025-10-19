<?php

namespace App\Http\Services;

use App\Http\Services\MercadoLivreAuthService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketplaceService
{
    private string $baseUrl = 'https://api.mercadolivre.com';
    private MercadoLivreAuthService $authService;

    public function __construct(MercadoLivreAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function buscarOfertas(array $filtros = []): array
    {
        try {
            // Obter token válido
            $accessToken = $this->authService->getValidAccessToken();

            $termo = $filtros['q'] ?? '';
            $limit = $filtros['limit'] ?? 20;
            $offset = $filtros['offset'] ?? 0;
            $categoriaFiltro = $filtros['category'] ?? null;

            Log::info('Buscando ofertas ML', [
                'termo' => $termo,
                'limit' => $limit,
                'tem_token' => !empty($accessToken),
                'categoria' => $categoriaFiltro
            ]);

            $params = [
                'limit' => $limit,
                'offset' => $offset,
            ];

            if (!empty($termo)) {
                $params['q'] = $termo;
            }

            if ($accessToken) {
                $params['DISCOUNT'] = $filtros['desconto_minimo'] ?? '15-100';
            }

            if (!empty($filtros['category'])) {
                $params['category'] = $filtros['category'];
            }

            if (!empty($filtros['preco_max'])) {
                $precoMin = $filtros['preco_min'] ?? 0;
                $params['price'] = "{$precoMin}-{$filtros['preco_max']}";
            }

            $httpClient = Http::timeout(10);

            if ($accessToken) {
                $httpClient = $httpClient->withToken($accessToken);
            }

            $response = $httpClient->get("{$this->baseUrl}/sites/MLB/search", $params);

            if ($response->failed()) {
                Log::warning('Erro na API ML, usando dados de exemplo', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return $this->retornarProdutosExemplo($termo, $limit, $categoriaFiltro);
            }

            $dados = $response->json();
            $produtos = $dados['results'] ?? [];

            if (empty($produtos)) {
                return [
                    'sucesso' => true,
                    'total' => 0,
                    'produtos' => [],
                    'mensagem' => 'Nenhum produto encontrado'
                ];
            }

            $produtosComDesconto = array_filter($produtos, function($p) {
                return isset($p['original_price']) && $p['original_price'] > $p['price'];
            });

            if (empty($produtosComDesconto)) {
                $produtosComDesconto = $produtos;
            }

            $produtosFormatados = array_map(function($produto) {
                return $this->formatarProduto($produto);
            }, $produtosComDesconto);

            usort($produtosFormatados, function($a, $b) {
                return $b['percentual_desconto'] <=> $a['percentual_desconto'];
            });

            Log::info('Produtos encontrados', ['total' => count($produtosFormatados)]);

            return [
                'sucesso' => true,
                'total' => count($produtosFormatados),
                'produtos' => $produtosFormatados,
            ];

        } catch (\Exception $e) {
            Log::error('Exceção ao buscar ofertas ML', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->retornarProdutosExemplo($filtros['q'] ?? 'produtos', $filtros['limit'] ?? 20, $filtros['category'] ?? null);
        }
    }


    public function buscarProdutoPorId(string $produtoId): ?array
    {
        try {
            $accessToken = $this->authService->getValidAccessToken();

            Log::info('Buscando produto por ID', ['id' => $produtoId]);

            $httpClient = Http::timeout(10);

            if ($accessToken) {
                $httpClient = $httpClient->withToken($accessToken);
            }

            $response = $httpClient->get("{$this->baseUrl}/items/{$produtoId}");

            if ($response->failed()) {
                Log::error('Erro ao buscar produto', [
                    'id' => $produtoId,
                    'status' => $response->status()
                ]);
                return null;
            }

            return $this->formatarProduto($response->json());

        } catch (\Exception $e) {
            Log::error('Exceção ao buscar produto por ID', [
                'id' => $produtoId,
                'erro' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function buscarCategorias(): array
    {
        return Cache::remember('ml_categorias', 3600, function () {
            try {
                $accessToken = $this->authService->getValidAccessToken();

                $httpClient = Http::timeout(10);

                if ($accessToken) {
                    $httpClient = $httpClient->withToken($accessToken);
                }

                $response = $httpClient->get("{$this->baseUrl}/sites/MLB/categories");

                if ($response->failed()) {
                    return $this->retornarCategoriasExemplo();
                }

                $categorias = $response->json();

                return [
                    'sucesso' => true,
                    'categorias' => $categorias,
                ];

            } catch (\Exception $e) {
                Log::error('Erro ao buscar categorias', ['erro' => $e->getMessage()]);
                return $this->retornarCategoriasExemplo();
            }
        });
    }

    public function buscarProdutosPorCategoria(string $categoriaId, int $limit = 50): array
    {
        return $this->buscarOfertas([
            'category' => $categoriaId,
            'limit' => $limit,
        ]);
    }

    public function buscarOfertasDestaque(int $limit = 12): array
    {
        return $this->buscarOfertas([
            'limit' => $limit,
            'desconto_minimo' => '30-100',
        ]);
    }

    public function buscarTendencias(string $categoriaId = null): array
    {
        try {
            $accessToken = $this->authService->getValidAccessToken();

            $url = $categoriaId
                ? "{$this->baseUrl}/trends/MLB/{$categoriaId}"
                : "{$this->baseUrl}/trends/MLB";

            $httpClient = Http::timeout(10);

            if ($accessToken) {
                $httpClient = $httpClient->withToken($accessToken);
            }

            $response = $httpClient->get($url);

            if ($response->failed()) {
                return ['sucesso' => false, 'tendencias' => []];
            }

            return [
                'sucesso' => true,
                'tendencias' => $response->json(),
            ];

        } catch (\Exception $e) {
            Log::error('Erro ao buscar tendências', ['erro' => $e->getMessage()]);
            return ['sucesso' => false, 'tendencias' => []];
        }
    }

    public function testarConexao(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/sites/MLB");
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Erro ao testar conexão ML', ['erro' => $e->getMessage()]);
            return false;
        }
    }

    public function calcularDesconto(float $precoOriginal, float $precoAtual): float
    {
        if ($precoOriginal <= 0 || $precoAtual >= $precoOriginal) {
            return 0;
        }
        return round((($precoOriginal - $precoAtual) / $precoOriginal) * 100, 2);
    }

    private function formatarProduto(array $produto): array
    {
        $precoAtual = $produto['price'] ?? 0;
        $precoOriginal = $produto['original_price'] ?? $precoAtual;

        if (!isset($produto['original_price'])) {
            $precoOriginal = $precoAtual;
        }

        $percentualDesconto = $this->calcularDesconto($precoOriginal, $precoAtual);
        $economia = $precoOriginal - $precoAtual;

        return [
            'id_externo' => $produto['id'] ?? null,
            'nome' => $produto['title'] ?? 'Sem título',
            'descricao' => $produto['subtitle'] ?? null,
            'preco_atual' => $precoAtual,
            'preco_original' => $precoOriginal,
            'percentual_desconto' => $percentualDesconto,
            'economia' => $economia,
            'url' => $produto['permalink'] ?? null,
            'imagem' => $produto['thumbnail'] ?? ($produto['pictures'][0]['url'] ?? null),
            'categoria_id_externo' => $produto['category_id'] ?? null,
            'em_estoque' => ($produto['available_quantity'] ?? 0) > 0,
            'quantidade_disponivel' => $produto['available_quantity'] ?? 0,
            'quantidade_vendida' => $produto['sold_quantity'] ?? 0,
            'condicao' => $produto['condition'] ?? 'new', // new, used
            'frete_gratis' => $produto['shipping']['free_shipping'] ?? false,
            'avaliacao_media' => $produto['reviews']['rating_average'] ?? null,
            'total_avaliacoes' => $produto['reviews']['total'] ?? 0,
            'seller_id' => $produto['seller']['id'] ?? null,
            'seller_nome' => $produto['seller']['nickname'] ?? null,
            'atributos' => $produto['attributes'] ?? [],
        ];
    }

    private function retornarProdutosExemplo(string $termo, int $limit, ?string $categoria = null): array
    {
        $todosExemplos = [
            [
                'id_externo' => 'MLB400000101',
                'nome' => 'Samsung Galaxy S23 128GB',
                'descricao' => 'Smartphone com câmera avançada',
                'preco_atual' => 3499.00,
                'preco_original' => 4199.00,
                'percentual_desconto' => 16.67,
                'economia' => 700.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_samsung-s23.webp',
                'categoria_id_externo' => 'MLB1055',
                'em_estoque' => true,
                'quantidade_disponivel' => 45,
                'quantidade_vendida' => 1200,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.6,
                'total_avaliacoes' => 980,
                'seller_id' => 'SELLER400',
                'seller_nome' => 'Samsung Oficial',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000102',
                'nome' => 'Xiaomi Redmi Note 12 256GB',
                'descricao' => 'Bateria de longa duração',
                'preco_atual' => 1299.90,
                'preco_original' => 1699.90,
                'percentual_desconto' => 23.53,
                'economia' => 400.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_xiaomi-redmi.webp',
                'categoria_id_externo' => 'MLB1055',
                'em_estoque' => true,
                'quantidade_disponivel' => 150,
                'quantidade_vendida' => 2100,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.4,
                'total_avaliacoes' => 540,
                'seller_id' => 'SELLER401',
                'seller_nome' => 'Xiaomi Brasil',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000103',
                'nome' => 'Motorola Moto G Power 64GB',
                'descricao' => 'Ótimo custo-benefício',
                'preco_atual' => 799.90,
                'preco_original' => 999.90,
                'percentual_desconto' => 20.0,
                'economia' => 200.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_motorola-gpower.webp',
                'categoria_id_externo' => 'MLB1055',
                'em_estoque' => true,
                'quantidade_disponivel' => 80,
                'quantidade_vendida' => 760,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.2,
                'total_avaliacoes' => 210,
                'seller_id' => 'SELLER402',
                'seller_nome' => 'Motorola Store',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000104',
                'nome' => 'Capinha Silicone para Galaxy S23',
                'descricao' => 'Proteção leve e colorida',
                'preco_atual' => 39.90,
                'preco_original' => 59.90,
                'percentual_desconto' => 33.39,
                'economia' => 20.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_case-s23.webp',
                'categoria_id_externo' => 'MLB1055',
                'em_estoque' => true,
                'quantidade_disponivel' => 500,
                'quantidade_vendida' => 420,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.4,
                'total_avaliacoes' => 120,
                'seller_id' => 'SELLER403',
                'seller_nome' => 'Acessorize',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000105',
                'nome' => 'Fone Bluetooth ANC XYZ',
                'descricao' => 'Cancelamento ativo de ruído',
                'preco_atual' => 299.90,
                'preco_original' => 499.90,
                'percentual_desconto' => 40.0,
                'economia' => 200.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_earbuds.webp',
                'categoria_id_externo' => 'MLB1055',
                'em_estoque' => true,
                'quantidade_disponivel' => 120,
                'quantidade_vendida' => 1500,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.4,
                'total_avaliacoes' => 342,
                'seller_id' => 'SELLER404',
                'seller_nome' => 'Audio Store',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000106',
                'nome' => 'Powerbank 20000mAh Fast Charge',
                'descricao' => 'Carregamento rápido para celulares',
                'preco_atual' => 99.90,
                'preco_original' => 149.90,
                'percentual_desconto' => 33.36,
                'economia' => 50.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_powerbank.webp',
                'categoria_id_externo' => 'MLB1055',
                'em_estoque' => true,
                'quantidade_disponivel' => 300,
                'quantidade_vendida' => 980,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.4,
                'total_avaliacoes' => 210,
                'seller_id' => 'SELLER405',
                'seller_nome' => 'PowerStore',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000107',
                'nome' => 'Carregador Turbo USB-C 45W',
                'descricao' => 'Carga rápida segura',
                'preco_atual' => 79.90,
                'preco_original' => 119.90,
                'percentual_desconto' => 33.36,
                'economia' => 40.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_carregador-usb.webp',
                'categoria_id_externo' => 'MLB1055',
                'em_estoque' => true,
                'quantidade_disponivel' => 220,
                'quantidade_vendida' => 640,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.3,
                'total_avaliacoes' => 140,
                'seller_id' => 'SELLER406',
                'seller_nome' => 'ChargeFast',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000108',
                'nome' => 'Película de Vidro Temperado',
                'descricao' => 'Proteção resistente para tela',
                'preco_atual' => 19.90,
                'preco_original' => 29.90,
                'percentual_desconto' => 33.44,
                'economia' => 10.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_pelicula-vidro.webp',
                'categoria_id_externo' => 'MLB1055',
                'em_estoque' => true,
                'quantidade_disponivel' => 800,
                'quantidade_vendida' => 1500,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.1,
                'total_avaliacoes' => 320,
                'seller_id' => 'SELLER407',
                'seller_nome' => 'Shield',
                'atributos' => [],
            ],

            [
                'id_externo' => 'MLB400000201',
                'nome' => 'Acer Aspire 5 i5 8GB 256GB SSD',
                'descricao' => 'Notebook leve para uso diário',
                'preco_atual' => 2399.00,
                'preco_original' => 2899.00,
                'percentual_desconto' => 17.24,
                'economia' => 500.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_acer-aspire.webp',
                'categoria_id_externo' => 'MLB1196',
                'em_estoque' => true,
                'quantidade_disponivel' => 30,
                'quantidade_vendida' => 420,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.3,
                'total_avaliacoes' => 98,
                'seller_id' => 'SELLER500',
                'seller_nome' => 'Acer Store',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000202',
                'nome' => 'ASUS Vivobook 15 Ryzen 7 16GB',
                'descricao' => 'Desempenho para produtividade',
                'preco_atual' => 4299.00,
                'preco_original' => 5499.00,
                'percentual_desconto' => 21.82,
                'economia' => 1200.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_asus-vivobook.webp',
                'categoria_id_externo' => 'MLB1196',
                'em_estoque' => true,
                'quantidade_disponivel' => 12,
                'quantidade_vendida' => 300,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.7,
                'total_avaliacoes' => 410,
                'seller_id' => 'SELLER501',
                'seller_nome' => 'ASUS BR',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000203',
                'nome' => 'MacBook Air M1 256GB',
                'descricao' => 'Performance e bateria duradoura',
                'preco_atual' => 6999.00,
                'preco_original' => 7999.00,
                'percentual_desconto' => 12.50,
                'economia' => 1000.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_macbook-air.webp',
                'categoria_id_externo' => 'MLB1196',
                'em_estoque' => true,
                'quantidade_disponivel' => 10,
                'quantidade_vendida' => 600,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.8,
                'total_avaliacoes' => 920,
                'seller_id' => 'SELLER502',
                'seller_nome' => 'Apple Reseller',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000204',
                'nome' => 'Mouse Gamer RGB 16000 DPI',
                'descricao' => 'Precisão para longas sessões',
                'preco_atual' => 179.90,
                'preco_original' => 249.90,
                'percentual_desconto' => 28.01,
                'economia' => 70.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_mouse-gamer.webp',
                'categoria_id_externo' => 'MLB1196',
                'em_estoque' => true,
                'quantidade_disponivel' => 60,
                'quantidade_vendida' => 430,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.5,
                'total_avaliacoes' => 120,
                'seller_id' => 'SELLER503',
                'seller_nome' => 'GamerShop',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000205',
                'nome' => 'Teclado Mecânico RGB Tenkeyless',
                'descricao' => 'Switches vermelhos, resposta rápida',
                'preco_atual' => 259.90,
                'preco_original' => 349.90,
                'percentual_desconto' => 25.72,
                'economia' => 90.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_teclado-mecanico.webp',
                'categoria_id_externo' => 'MLB1196',
                'em_estoque' => true,
                'quantidade_disponivel' => 75,
                'quantidade_vendida' => 310,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.6,
                'total_avaliacoes' => 210,
                'seller_id' => 'SELLER504',
                'seller_nome' => 'KeyStore',
                'atributos' => [],
            ],

            [
                'id_externo' => 'MLB400000301',
                'nome' => 'Smart TV LG 50" 4K',
                'descricao' => 'Imagem 4K com HDR',
                'preco_atual' => 2699.00,
                'preco_original' => 3299.00,
                'percentual_desconto' => 18.19,
                'economia' => 600.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_lg-50-4k.webp',
                'categoria_id_externo' => 'MLB1002',
                'em_estoque' => true,
                'quantidade_disponivel' => 25,
                'quantidade_vendida' => 540,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.5,
                'total_avaliacoes' => 620,
                'seller_id' => 'SELLER600',
                'seller_nome' => 'LG Store',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000302',
                'nome' => 'Smart TV Samsung 43" Crystal',
                'descricao' => 'Tela cristal e design fino',
                'preco_atual' => 1999.00,
                'preco_original' => 2499.00,
                'percentual_desconto' => 20.01,
                'economia' => 500.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_samsung-tv-43.webp',
                'categoria_id_externo' => 'MLB1002',
                'em_estoque' => true,
                'quantidade_disponivel' => 40,
                'quantidade_vendida' => 870,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.4,
                'total_avaliacoes' => 410,
                'seller_id' => 'SELLER601',
                'seller_nome' => 'Samsung Store',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000303',
                'nome' => 'Smart TV Philips 55" Ambilight',
                'descricao' => 'Ambilight para imersão total',
                'preco_atual' => 3299.00,
                'preco_original' => 3999.00,
                'percentual_desconto' => 17.49,
                'economia' => 700.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_philips-55.webp',
                'categoria_id_externo' => 'MLB1002',
                'em_estoque' => true,
                'quantidade_disponivel' => 18,
                'quantidade_vendida' => 210,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.6,
                'total_avaliacoes' => 150,
                'seller_id' => 'SELLER602',
                'seller_nome' => 'Philips Oficial',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000304',
                'nome' => 'Smart TV TCL 32" HD',
                'descricao' => 'Tamanho compacto para quartos',
                'preco_atual' => 899.00,
                'preco_original' => 1099.00,
                'percentual_desconto' => 18.20,
                'economia' => 200.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_tcl-32.webp',
                'categoria_id_externo' => 'MLB1002',
                'em_estoque' => true,
                'quantidade_disponivel' => 60,
                'quantidade_vendida' => 320,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.2,
                'total_avaliacoes' => 88,
                'seller_id' => 'SELLER603',
                'seller_nome' => 'TCL Store',
                'atributos' => [],
            ],

            [
                'id_externo' => 'MLB400000401',
                'nome' => 'PlayStation 5 Slim - Bundle',
                'descricao' => 'Console + 1 jogo + 1 controle',
                'preco_atual' => 3899.00,
                'preco_original' => 4799.00,
                'percentual_desconto' => 18.76,
                'economia' => 900.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_ps5-bundle.webp',
                'categoria_id_externo' => 'MLB1144',
                'em_estoque' => false,
                'quantidade_disponivel' => 0,
                'quantidade_vendida' => 2341,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.9,
                'total_avaliacoes' => 2341,
                'seller_id' => 'SELLER700',
                'seller_nome' => 'PlayStation Store',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000402',
                'nome' => 'Xbox Series S 512GB - Edição Especial',
                'descricao' => 'Compacto e rápido',
                'preco_atual' => 2199.00,
                'preco_original' => 2699.00,
                'percentual_desconto' => 18.53,
                'economia' => 500.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_xbox-series-s.webp',
                'categoria_id_externo' => 'MLB1144',
                'em_estoque' => true,
                'quantidade_disponivel' => 18,
                'quantidade_vendida' => 980,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.7,
                'total_avaliacoes' => 512,
                'seller_id' => 'SELLER701',
                'seller_nome' => 'Microsoft Store',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000403',
                'nome' => 'Controle DualSense Extra',
                'descricao' => 'Controle oficial PS5',
                'preco_atual' => 399.00,
                'preco_original' => 499.00,
                'percentual_desconto' => 20.04,
                'economia' => 100.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_dualsense.webp',
                'categoria_id_externo' => 'MLB1144',
                'em_estoque' => true,
                'quantidade_disponivel' => 140,
                'quantidade_vendida' => 980,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.8,
                'total_avaliacoes' => 610,
                'seller_id' => 'SELLER702',
                'seller_nome' => 'GamerShop',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000404',
                'nome' => 'Headset Gamer Surround 7.1',
                'descricao' => 'Áudio imersivo para jogos',
                'preco_atual' => 249.90,
                'preco_original' => 349.90,
                'percentual_desconto' => 28.58,
                'economia' => 100.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_headset-gamer.webp',
                'categoria_id_externo' => 'MLB1144',
                'em_estoque' => true,
                'quantidade_disponivel' => 90,
                'quantidade_vendida' => 430,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.5,
                'total_avaliacoes' => 220,
                'seller_id' => 'SELLER703',
                'seller_nome' => 'ProGamer',
                'atributos' => [],
            ],

            [
                'id_externo' => 'MLB400000501',
                'nome' => 'O Poder do Hábito - Charles Duhigg',
                'descricao' => 'Desenvolvimento pessoal e produtividade',
                'preco_atual' => 39.90,
                'preco_original' => 59.90,
                'percentual_desconto' => 33.39,
                'economia' => 20.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_o-poder-do-habito.webp',
                'categoria_id_externo' => 'MLB1384',
                'em_estoque' => true,
                'quantidade_disponivel' => 400,
                'quantidade_vendida' => 1500,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.8,
                'total_avaliacoes' => 760,
                'seller_id' => 'SELLER800',
                'seller_nome' => 'Livraria Central',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000502',
                'nome' => 'Sapiens - Yuval Noah Harari',
                'descricao' => 'Uma Breve História da Humanidade',
                'preco_atual' => 49.90,
                'preco_original' => 69.90,
                'percentual_desconto' => 28.61,
                'economia' => 20.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_sapiens.webp',
                'categoria_id_externo' => 'MLB1384',
                'em_estoque' => true,
                'quantidade_disponivel' => 220,
                'quantidade_vendida' => 860,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.9,
                'total_avaliacoes' => 1340,
                'seller_id' => 'SELLER801',
                'seller_nome' => 'Sebos & Livros',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000503',
                'nome' => 'Mindset - Carol Dweck',
                'descricao' => 'A psicologia do sucesso',
                'preco_atual' => 44.90,
                'preco_original' => 64.90,
                'percentual_desconto' => 30.82,
                'economia' => 20.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_mindset.webp',
                'categoria_id_externo' => 'MLB1384',
                'em_estoque' => true,
                'quantidade_disponivel' => 180,
                'quantidade_vendida' => 540,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.7,
                'total_avaliacoes' => 320,
                'seller_id' => 'SELLER802',
                'seller_nome' => 'BookStore',
                'atributos' => [],
            ],

            [
                'id_externo' => 'MLB400000601',
                'nome' => 'Tênis Nike Air Zoom Pegasus 38',
                'descricao' => 'Treino e corrida diária',
                'preco_atual' => 349.90,
                'preco_original' => 449.90,
                'percentual_desconto' => 22.23,
                'economia' => 100.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_nike-pegasus.webp',
                'categoria_id_externo' => 'MLB1430',
                'em_estoque' => true,
                'quantidade_disponivel' => 75,
                'quantidade_vendida' => 640,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.5,
                'total_avaliacoes' => 234,
                'seller_id' => 'SELLER900',
                'seller_nome' => 'Nike Oficial',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000602',
                'nome' => 'Bicicleta Caloi 21 Marchas Aro 29',
                'descricao' => 'Suspensão dianteira, ideal para trilhas leves',
                'preco_atual' => 1899.00,
                'preco_original' => 2299.00,
                'percentual_desconto' => 17.39,
                'economia' => 400.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_caloi-29.webp',
                'categoria_id_externo' => 'MLB1430',
                'em_estoque' => true,
                'quantidade_disponivel' => 20,
                'quantidade_vendida' => 120,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.1,
                'total_avaliacoes' => 87,
                'seller_id' => 'SELLER901',
                'seller_nome' => 'Caloi Store',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000603',
                'nome' => 'Suplemento Whey Protein 2kg',
                'descricao' => 'Ganhe massa muscular com fórmulas premium',
                'preco_atual' => 199.90,
                'preco_original' => 249.90,
                'percentual_desconto' => 20.01,
                'economia' => 50.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_wheyprotein.webp',
                'categoria_id_externo' => 'MLB1430',
                'em_estoque' => true,
                'quantidade_disponivel' => 130,
                'quantidade_vendida' => 980,
                'condicao' => 'new',
                'frete_gratis' => false,
                'avaliacao_media' => 4.4,
                'total_avaliacoes' => 410,
                'seller_id' => 'SELLER902',
                'seller_nome' => 'NutriShop',
                'atributos' => [],
            ],
            [
                'id_externo' => 'MLB400000604',
                'nome' => 'Caneleira Ajustável 2kg',
                'descricao' => 'Treinos de resistência e reabilitação',
                'preco_atual' => 79.90,
                'preco_original' => 99.90,
                'percentual_desconto' => 20.02,
                'economia' => 20.00,
                'url' => 'https://www.mercadolivre.com.br',
                'imagem' => 'https://http2.mlstatic.com/D_NQ_NP_2X_caneleira.webp',
                'categoria_id_externo' => 'MLB1430',
                'em_estoque' => true,
                'quantidade_disponivel' => 220,
                'quantidade_vendida' => 300,
                'condicao' => 'new',
                'frete_gratis' => true,
                'avaliacao_media' => 4.2,
                'total_avaliacoes' => 76,
                'seller_id' => 'SELLER903',
                'seller_nome' => 'SportStore',
                'atributos' => [],
            ],
        ];

        if (!empty($termo) && strtolower($termo) !== 'ofertas' && strtolower($termo) !== 'produtos') {
            $todosExemplos = array_filter($todosExemplos, function($p) use ($termo) {
                return stripos($p['nome'], $termo) !== false || stripos($p['descricao'] ?? '', $termo) !== false;
            });
            $todosExemplos = array_values($todosExemplos);
        }

        if (!empty($categoria)) {
            $todosExemplos = array_filter($todosExemplos, function($p) use ($categoria) {
                return isset($p['categoria_id_externo']) && $p['categoria_id_externo'] === $categoria;
            });
            $todosExemplos = array_values($todosExemplos);
        }

        $produtosLimitados = array_slice($todosExemplos, 0, $limit);

        return [
            'sucesso' => true,
            'total' => count($produtosLimitados),
            'produtos' => $produtosLimitados,
            'aviso' => 'Dados de exemplo - API do Mercado Livre temporariamente indisponível ou aguardando autenticação OAuth',
        ];
    }

    private function retornarCategoriasExemplo(): array
    {
        return [
            'sucesso' => true,
            'categorias' => [
                ['id' => 'MLB1051', 'name' => 'Celulares e Telefones'],
                ['id' => 'MLB1000', 'name' => 'Eletrônicos, Áudio e Vídeo'],
                ['id' => 'MLB1144', 'name' => 'Consoles e Videogames'],
                ['id' => 'MLB1196', 'name' => 'Computação'],
                ['id' => 'MLB1039', 'name' => 'Câmeras e Acessórios'],
                ['id' => 'MLB1384', 'name' => 'Livros, Revistas e Comics'],
                ['id' => 'MLB1430', 'name' => 'Esportes e Fitness'],
                ['id' => 'MLB1071', 'name' => 'Animais'],
                ['id' => 'MLB1367', 'name' => 'Beleza e Cuidado Pessoal'],
                ['id' => 'MLB1953', 'name' => 'Moda'],
            ],
            'aviso' => 'Categorias de exemplo',
        ];
    }
}
