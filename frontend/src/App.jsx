import { useState, useEffect, useRef, useMemo } from "react";
import Navbar from "./components/Navbar";
import CategoryFilter from "./components/CategoryFilter";
import ProductCard from "./components/ProductCard";
import ofertasService from "./services/ofertasService";
import { Loader2, TrendingDown, Zap, AlertCircle } from "lucide-react";

function App() {
  const [produtos, setProdutos] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedCategory, setSelectedCategory] = useState("all");
  const [busca, setBusca] = useState("");
  const [sortOption, setSortOption] = useState("maior_desconto"); // default
  const searchTimeout = useRef(null);

  useEffect(() => {
    buscarProdutos();
  }, [selectedCategory]);

  const buscarProdutos = async (overrideBusca = null) => {
    setLoading(true);
    setError(null);

    try {
      const termoLocal = overrideBusca !== null ? overrideBusca : busca;

      let termoBuscaEbay = termoLocal;

      if (!termoBuscaEbay && selectedCategory !== "all") {
        const categoriaMap = {
          MLB1055: "smartphone",
          MLB1196: "notebook",
          MLB1002: "smart tv",
          MLB1144: "gaming console",
          MLB1384: "book",
          MLB1430: "sporting goods",
        };
        termoBuscaEbay = categoriaMap[selectedCategory] || "";
      }

      const filtros = {
        limit: 100,
        ...(termoBuscaEbay && { q: termoBuscaEbay }),
      };

      const data = await ofertasService.buscarOfertas(filtros);

      if (data.sucesso) {
        setProdutos(data.produtos || []);
      } else {
        setError(data.erro || "Erro ao buscar produtos");
      }
    } catch (err) {
      console.error("Erro:", err);
      setError(
        "Erro ao conectar com o servidor. Verifique se o backend está rodando."
      );
    } finally {
      setLoading(false);
    }
  };

  const handleBusca = (termoBusca) => {
    clearTimeout(searchTimeout.current);
    setBusca(termoBusca);
    searchTimeout.current = setTimeout(() => {
      buscarProdutos(termoBusca);
    }, 500);
  };

  const produtosOrdenados = useMemo(() => {
    if (!produtos || produtos.length === 0) return [];

    const copia = [...produtos];

    if (sortOption === "maior_desconto") {
      copia.sort(
        (a, b) => (b.percentual_desconto || 0) - (a.percentual_desconto || 0)
      );
    } else if (sortOption === "menor_preco") {
      copia.sort((a, b) => (a.preco_atual || 0) - (b.preco_atual || 0));
    } else if (sortOption === "mais_vendidos") {
      copia.sort(
        (a, b) => (b.quantidade_vendida || 0) - (a.quantidade_vendida || 0)
      );
    }

    return copia;
  }, [produtos, sortOption]);

  const maiorDescontoPercentual = useMemo(() => {
    if (!produtos || produtos.length === 0) return 0;
    const max = produtos.reduce((acc, p) => {
      const valor = Number(p.percentual_desconto) || 0;
      return valor > acc ? valor : acc;
    }, 0);
    return Math.round(max);
  }, [produtos]);

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
      <Navbar onBusca={handleBusca} />

      <div className="bg-gradient-to-r from-primary-600 to-secondary-600 text-white py-12">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center">
            <h1 className="text-4xl md:text-5xl font-bold mb-4 flex items-center justify-center space-x-3">
              <span>Ofertas Imperdíveis!</span>
            </h1>
            <p className="text-xl text-blue-100 max-w-2xl mx-auto">
              Encontre as melhores ofertas dos maiores Marketplaces em um só
              lugar!
            </p>

            <div className="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl mx-auto">
              <div className="bg-white/10 backdrop-blur-lg rounded-lg p-4">
                <div className="text-3xl font-bold">{produtos.length}</div>
                <div className="text-sm text-blue-100">Ofertas Ativas</div>
              </div>
              <div className="bg-white/10 backdrop-blur-lg rounded-lg p-4">
                <div className="text-3xl font-bold flex items-center justify-center">
                  <TrendingDown className="w-8 h-8 mr-2" />
                  <span>até {maiorDescontoPercentual}%</span>
                </div>
                <div className="text-sm text-blue-100">de Desconto</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <CategoryFilter
          selectedCategory={selectedCategory}
          onSelectCategory={setSelectedCategory}
        />

        {error && (
          <div className="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
            <div className="flex items-center">
              <AlertCircle className="w-6 h-6 text-red-500 mr-3" />
              <div>
                <p className="text-red-800 font-semibold">
                  Erro ao carregar ofertas
                </p>
                <p className="text-red-600 text-sm">{error}</p>
              </div>
            </div>
            <button
              onClick={buscarProdutos}
              className="mt-3 text-red-700 underline text-sm hover:text-red-900"
            >
              Tentar novamente
            </button>
          </div>
        )}

        {loading && (
          <div className="flex flex-col items-center justify-center py-20">
            <Loader2 className="w-12 h-12 text-primary-600 animate-spin mb-4" />
            <p className="text-gray-600 text-lg">
              Carregando ofertas incríveis...
            </p>
          </div>
        )}

        {!loading && !error && produtos.length > 0 && (
          <>
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-2xl font-bold text-gray-900">
                {produtos.length} ofertas encontradas
              </h2>
              <select
                value={sortOption}
                onChange={(e) => setSortOption(e.target.value)}
                className="px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-primary-500"
              >
                <option value="maior_desconto">Maior desconto</option>
                <option value="menor_preco">Menor preço</option>
                <option value="mais_vendidos">Mais vendidos</option>
              </select>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              {produtosOrdenados.map((produto) => (
                <ProductCard
                  key={produto.id_externo || produto.id}
                  produto={produto}
                />
              ))}
            </div>
          </>
        )}

        {!loading && !error && produtos.length === 0 && (
          <div className="text-center py-20">
            <h3 className="text-2xl font-bold text-gray-900 mb-2">
              Nenhuma oferta encontrada
            </h3>
            <p className="text-gray-600 mb-4">
              Tente selecionar outra categoria ou volte mais tarde
            </p>
            <button
              onClick={() => {
                setSelectedCategory("all");
                setBusca("");
                buscarProdutos();
              }}
              className="btn-primary"
            >
              Ver todas as ofertas
            </button>
          </div>
        )}
      </div>

      <footer className="bg-gray-900 text-white py-8 mt-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <p className="text-gray-400">
            © 2025 EconomizAI - Encontre as melhores ofertas
          </p>
        </div>
      </footer>
    </div>
  );
}

export default App;
