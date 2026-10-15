import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// conexão contínua do bilhete em tempo real (spec 010, R-01): Echo com o Reverb; host, porta e
// esquema chegam pela página a cada carga (props tempo_real), nunca pelo build
export const criar_conexao = (tempo_real) => {
    if (!tempo_real?.chave) return null;

    const seguro = tempo_real.esquema === 'https';

    return new Echo({
        broadcaster: 'reverb',
        Pusher,
        key: tempo_real.chave,
        wsHost: tempo_real.host,
        wsPort: tempo_real.porta,
        wssPort: tempo_real.porta,
        forceTLS: seguro,
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    });
};

// estados do pusher-js em que a conexão está de pé
const estados_conectados = ['connected'];

// avisa quando a conexão cai ou volta (true = conectada); devolve a função que para de ouvir
export const estado_conexao = (echo, ao_mudar) => {
    const conexao = echo?.connector?.pusher?.connection;

    if (!conexao) return () => {};

    const tratar = ({ current }) => ao_mudar(estados_conectados.includes(current));

    conexao.bind('state_change', tratar);
    ao_mudar(estados_conectados.includes(conexao.state));

    return () => conexao.unbind('state_change', tratar);
};
