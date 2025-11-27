import api from "./api";

const ofertasService = {
  
  buscarOfertas: async (filtros = {}) => {
    const maxRetries = 3;
    let lastError = null;

    for (let attempt = 0; attempt < maxRetries; attempt++) {
      try {
        const params = {
          page: filtros.page || 1,
          per_page: filtros.per_page || filtros.limit || 25,
          ...(filtros.q && { q: filtros.q }),
          ...(filtros.category && { category: filtros.category }),
          ...(filtros.desconto_minimo && { desconto_minimo: filtros.desconto_minimo }),
          ...(filtros.preco_max && { preco_max: filtros.preco_max }),
        };

        const response = await api.get("/ofertas", { params });
        return response.data;
      } catch (error) {
        lastError = error;
        if (attempt < maxRetries - 1) {
          const delay = Math.pow(2, attempt) * 1000;
          console.warn(`Tentativa ${attempt + 1}/${maxRetries} falhou. Aguardando ${delay}ms antes de retry...`);
          await new Promise((resolve) => setTimeout(resolve, delay));
        }
      }
    }

    console.error("Erro ao buscar ofertas após 3 tentativas:", lastError);
    throw lastError;
  },

  buscarDestaque: async (limit = 12) => {
    try {
      const response = await api.get("/ofertas/destaque", {
        params: { limit },
      });
      return response.data;
    } catch (error) {
      console.error("Erro ao buscar destaques:", error);
      throw error;
    }
  },

  buscarCategorias: async () => {
    try {
      const response = await api.get("/ofertas/categorias");
      return response.data;
    } catch (error) {
      console.error("Erro ao buscar categorias:", error);
      throw error;
    }
  },

  testarConexao: async () => {
    try {
      const response = await api.get("/ofertas/testar");
      return response.data;
    } catch (error) {
      console.error("Erro ao testar conexão:", error);
      throw error;
    }
  },
};

export default ofertasService;
