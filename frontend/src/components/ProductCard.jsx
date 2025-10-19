import { Heart, ExternalLink, TrendingUp, Truck } from "lucide-react";

const ProductCard = ({ produto }) => {
  return (
    <div className="bg-white rounded-xl shadow-md overflow-hidden card-hover">
      <div className="relative bg-gray-50 aspect-square flex items-center justify-center overflow-hidden">
        <img
          src={produto.imagem}
          alt={produto.nome}
          className="w-full h-full object-cover"
        />

        {produto.percentual_desconto > 0 && (
          <div className="badge-discount">-{produto.percentual_desconto.toFixed(0)}%</div>
        )}

        <button className="absolute top-3 left-3 bg-white p-2 rounded-full shadow-lg hover:bg-red-50 hover:text-red-600 transition-all">
          <Heart className="w-5 h-5" />
        </button>

        {produto.quantidade_vendida > 500 && (
          <div className="absolute bottom-3 left-3 bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full flex items-center space-x-1">
            <TrendingUp className="w-3 h-3" />
            <span>Mais Vendido</span>
          </div>
        )}
      </div>

      <div className="p-5">
        <h3 className="text-sm text-gray-800 font-medium mb-3 h-12 overflow-hidden leading-tight">
          {produto.nome}
        </h3>
        <div className="mb-4">
          {produto.preco_original > produto.preco_atual && (
            <p className="text-gray-400 line-through text-sm">
              R${" "}
              {produto.preco_original.toLocaleString("pt-BR", {
                minimumFractionDigits: 2,
              })}
            </p>
          )}
          <div className="flex items-baseline space-x-2">
            <p className="text-3xl font-bold text-green-600">
              R${" "}
              {produto.preco_atual.toLocaleString("pt-BR", {
                minimumFractionDigits: 2,
              })}
            </p>
          </div>
          {produto.economia > 0 && (
            <p className="text-sm text-gray-600 mt-1">
              Economize{" "}
              <span className="font-semibold text-green-600">
                R${" "}
                {produto.economia.toLocaleString("pt-BR", {
                  minimumFractionDigits: 2,
                })}
              </span>
            </p>
          )}
        </div>
        <div className="flex flex-wrap gap-2 mb-4">
          {produto.frete_gratis && (
            <span className="inline-flex items-center space-x-1 bg-green-100 text-green-700 text-xs font-medium px-2 py-1 rounded-full">
              <Truck className="w-3 h-3" />
              <span>Frete Grátis</span>
            </span>
          )}

          {produto.em_estoque ? (
            <span className="bg-blue-100 text-blue-700 text-xs font-medium px-2 py-1 rounded-full">
              Em estoque
            </span>
          ) : (
            <span className="bg-red-100 text-red-700 text-xs font-medium px-2 py-1 rounded-full">
              Indisponível
            </span>
          )}
        </div>
        {produto.avaliacao_media && (
          <div className="flex items-center space-x-2 mb-4 text-sm text-gray-600">
            <div className="flex items-center">
              {[...Array(5)].map((_, i) => (
                <svg
                  key={i}
                  className={`w-4 h-4 ${
                    i < Math.round(produto.avaliacao_media)
                      ? "text-yellow-400"
                      : "text-gray-300"
                  }`}
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
              ))}
            </div>
            <span className="font-medium">
              {produto.avaliacao_media?.toFixed(1)}
            </span>
            <span className="text-gray-400">({produto.total_avaliacoes})</span>
          </div>
        )}
        <a
          href={produto.url}
          target="_blank"
          rel="noopener noreferrer"
          className="btn-primary w-full flex items-center justify-center space-x-2"
        >
          <span>Ver Oferta</span>
          <ExternalLink className="w-4 h-4" />
        </a>
      </div>
    </div>
  );
};

export default ProductCard;
