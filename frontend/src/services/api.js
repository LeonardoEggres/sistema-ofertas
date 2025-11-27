import axios from "axios";

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE || '/api',
  timeout: 120000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

api.interceptors.request.use(
  (config) => {
    config.metadata = { startTime: new Date().getTime() };
    console.log("Requisição:", config.method.toUpperCase(), config.url, "params:", config.params);
    return config;
  },
  (error) => {
    console.error("Erro na requisição:", error);
    return Promise.reject(error);
  }
);

api.interceptors.response.use(
  (response) => {
    try {
      const start = response.config?.metadata?.startTime;
      const duration = start ? (new Date().getTime() - start) : null;
      console.log("📥 Resposta:", response.status, `(${duration}ms)` , response.data);
    } catch (err) {
      console.error('Erro ao processar interceptor de resposta:', err);
      console.log("📥 Resposta:", response.status, response.data);
    }
    return response;
  },
  (error) => {
    try {
      const start = error.config?.metadata?.startTime;
      const duration = start ? (new Date().getTime() - start) : null;
      console.error("Erro na resposta:", error.response?.data || error.message, `(duration: ${duration}ms)`);
      console.error("Erro.config:", error.config);
    } catch (err) {
      console.error('Erro ao processar interceptor de erro:', err);
      console.error("Erro na resposta:", error.response?.data || error.message);
    }
    return Promise.reject(error);
  }
);

export default api;
