import { useCallback, useState } from 'react';

import useSessao from './useSessao';
import api from '../utils/api';
import { alerta_atencao, alerta_erro } from '../utils/alerts';

// abre os jogos do cassino (spec 011, R-08 a R-10): com dinheiro, só o cliente logado; o demo, para
// qualquer um. Com moldura, o jogo abre no GameFrame; sem moldura (withoutFrame), na própria aba,
// e o provedor devolve ao /close.php, que volta ao cassino
export default function useJogoCassino({ ao_pedir_login }) {
    const { sessao } = useSessao();
    const [abrindo, definir_abrindo] = useState(null);
    const [jogo_aberto, definir_jogo_aberto] = useState(null);

    const abrir = useCallback(async (jogo, demo) => {
        if (!demo && !sessao) {
            await alerta_atencao('Para acessar esse jogo é necessário que você realize o login como apostador.');
            ao_pedir_login();
            return;
        }

        // vendedor e usuário do painel não jogam com dinheiro (FR-013)
        if (!demo && sessao.tipo !== 'cliente') {
            alerta_atencao('Você precisa está logado como apostador.');
            return;
        }

        definir_abrindo(jogo.id);

        try {
            const { data } = await api.post(demo ? `/publico/cassino/jogos/${jogo.id}/demo` : `/area-cliente/cassino/jogos/${jogo.id}/abrir`);

            if (data.moldura) definir_jogo_aberto({ url: data.url, nome: jogo.nome });
            else window.location.assign(data.url);
        } catch (erro) {
            alerta_erro(erro);
        } finally {
            definir_abrindo(null);
        }
    }, [sessao, ao_pedir_login]);

    const jogar = useCallback((jogo) => abrir(jogo, false), [abrir]);
    const demo = useCallback((jogo) => abrir(jogo, true), [abrir]);
    const fechar = useCallback(() => definir_jogo_aberto(null), []);

    return { abrindo, jogo_aberto, jogar, demo, fechar };
}
