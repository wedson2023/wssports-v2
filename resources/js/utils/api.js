import axios from 'axios';

// cliente da API pública (sem token): detalhe do jogo, código da aposta e bilhete (R-13)
const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
});

export default api;
