import axios from "axios";

// Configuração base do Axios
const api = axios.create({
  baseURL: "http://localhost:8000/api",
  timeout: 10000,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// Interceptor para logs (opcional, útil para debug)
api.interceptors.request.use(
  (config) => {
    console.log("Requisição:", config.method.toUpperCase(), config.url);
    return config;
  },
  (error) => {
    console.error("Erro na requisição:", error);
    return Promise.reject(error);
  }
);

api.interceptors.response.use(
  (response) => {
    console.log("📥 Resposta:", response.status, response.data);
    return response;
  },
  (error) => {
    console.error(
      "Erro na resposta:",
      error.response?.data || error.message
    );
    return Promise.reject(error);
  }
);

export default api;
