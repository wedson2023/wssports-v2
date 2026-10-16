import { useCallback, useEffect, useRef, useState } from 'react';

import useTelaMobile from './useTelaMobile';
import api from '../utils/api';
import { alerta_erro } from '../utils/alerts';

const sem_filtro = { provedor: null, tipo: null, busca: null };

const filtrando = (filtros) => Boolean(filtros.provedor || filtros.tipo || filtros.busca);

// jogos do cassino (spec 011, R-07): tela inicial (provedores, tipos e linhas) e a grade filtrada,
// carregada em partes conforme a rolagem, como o handleScroll do screens/casino antigo
export default function useCassino(habilitado) {
    const e_mobile = useTelaMobile();
    const aparelho = e_mobile ? 'celular' : 'computador';

    const [inicio, definir_inicio] = useState(null);
    const [indisponivel, definir_indisponivel] = useState(!habilitado);
    const [filtros, definir_filtros] = useState(sem_filtro);
    const [grade, definir_grade] = useState({ jogos: [], pagina: 0, ultima_pagina: 0 });
    // "Carregando..." no lugar da grade enquanto chega a primeira página do filtro
    const [aguardando, definir_aguardando] = useState(false);
    // item da barra (provedor ou tipo) com o indicador de carregamento
    const [carregando_item, definir_carregando_item] = useState(null);
    const buscando_mais = useRef(false);

    const tratar_erro = useCallback((erro) => {
        if (erro?.response?.status === 403) {
            definir_indisponivel(true);
            return;
        }

        alerta_erro(erro);
    }, []);

    useEffect(() => {
        if (!habilitado) return;

        api.get('/publico/cassino', { params: { aparelho } })
            .then(({ data }) => definir_inicio(data))
            .catch(tratar_erro);
    }, [habilitado, aparelho, tratar_erro]);

    const buscar_pagina = useCallback((filtros_atuais, pagina) => api.get('/publico/cassino/jogos', {
        params: {
            aparelho,
            provedor: filtros_atuais.busca ? undefined : filtros_atuais.provedor ?? undefined,
            tipo: filtros_atuais.busca ? undefined : filtros_atuais.tipo ?? undefined,
            busca: filtros_atuais.busca ?? undefined,
            pagina,
            por_pagina: 50,
        },
    }), [aparelho]);

    useEffect(() => {
        if (!habilitado || !filtrando(filtros)) return;

        definir_aguardando(true);

        buscar_pagina(filtros, 1)
            .then(({ data }) => definir_grade({ jogos: data.data, pagina: data.meta.current_page, ultima_pagina: data.meta.last_page }))
            .catch(tratar_erro)
            .finally(() => {
                definir_aguardando(false);
                definir_carregando_item(null);
            });
    }, [habilitado, filtros, buscar_pagina, tratar_erro]);

    // próxima página da grade, quando faltam 500px para o fim da rolagem
    const carregar_mais = useCallback(() => {
        if (buscando_mais.current || aguardando || grade.pagina >= grade.ultima_pagina) return;

        buscando_mais.current = true;

        buscar_pagina(filtros, grade.pagina + 1)
            .then(({ data }) => definir_grade((anterior) => ({
                jogos: [...anterior.jogos, ...data.data],
                pagina: data.meta.current_page,
                ultima_pagina: data.meta.last_page,
            })))
            .catch(tratar_erro)
            .finally(() => { buscando_mais.current = false; });
    }, [aguardando, grade, filtros, buscar_pagina, tratar_erro]);

    // a busca pelo nome substitui provedor e tipo, como no antigo
    const filtrar = useCallback((novos, item = null) => {
        definir_carregando_item(item);
        definir_filtros((anteriores) => (novos.busca ? { ...sem_filtro, busca: novos.busca } : { ...anteriores, busca: null, ...novos }));
    }, []);

    const limpar = useCallback(() => {
        definir_filtros(sem_filtro);
        definir_grade({ jogos: [], pagina: 0, ultima_pagina: 0 });
    }, []);

    return {
        inicio,
        indisponivel,
        filtros,
        com_filtro: filtrando(filtros),
        jogos_filtrados: grade.jogos,
        aguardando,
        carregando_item,
        filtrar,
        limpar,
        carregar_mais,
    };
}
