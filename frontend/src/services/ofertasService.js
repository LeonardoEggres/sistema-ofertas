import api from "./api";

const ofertasService = {
  
  buscarOfertas: async (filtros = {}) => {
    try {
      const params = {
        limit: filtros.limit || 30,
        ...(filtros.q && { q: filtros.q }),
        ...(filtros.category && { category: filtros.category }),
        ...(filtros.desconto_minimo && {
          desconto_minimo: filtros.desconto_minimo,
        }),
        ...(filtros.preco_max && { preco_max: filtros.preco_max }),
        ...(filtros.offset && { offset: filtros.offset }),
      };

      const response = await api.get("/ofertas", { params });
      return response.data;
    } catch (error) {
      console.error("Erro ao buscar ofertas:", error);
      throw error;
    }
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
