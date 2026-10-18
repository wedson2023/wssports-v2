import { useCallback, useEffect, useState } from 'react';

import { alerta_erro } from '../utils/alerts';

// lista paginada da API com "Carregar mais", usada nas abas do painel do afiliado (spec 012);
// requisitar recebe a config do axios e devolve o corpo da resposta paginada do Laravel
export default function usePaginacao(requisitar, url, parametros) {
    const [itens, definir_itens] = useState([]);
    const [pagina, definir_pagina] = useState(1);
    const [ultima_pagina, definir_ultima_pagina] = useState(1);
    const [carregando, definir_carregando] = useState(true);
    // os filtros mudam de objeto a cada render: a chave em texto só recarrega quando o conteúdo muda
    const chave = JSON.stringify(parametros);

    const carregar = useCallback(async (numero) => {
        definir_carregando(true);

        try {
            const resposta = await requisitar({ url, params: { ...JSON.parse(chave), page: numero } });

            definir_itens((atuais) => (numero === 1 ? resposta.data : [...atuais, ...resposta.data]));
            definir_pagina(numero);
            definir_ultima_pagina(resposta.meta?.last_page ?? 1);
        } catch (erro) {
            // 401/403 já encerram a sessão em quem requisita
            if (![401, 403].includes(erro.response?.status)) alerta_erro(erro);
        } finally {
            definir_carregando(false);
        }
    }, [requisitar, url, chave]);

    useEffect(() => {
        carregar(1);
    }, [carregar]);

    return { itens, carregando, tem_mais: pagina < ultima_pagina, carregar_mais: () => carregar(pagina + 1) };
}
