import { useCallback, useEffect, useRef, useState } from 'react';

import ModalCard, { ModalButton } from '../ModalCard';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Badge, Empty, Item, List, Name, Summary, When } from './styles';

// "Minhas indicações" (spec 007, FR-044c): quem se cadastrou pelo link, quem já depositou e quem
// está pendente; rolagem infinita, sem dados pessoais dos indicados
export default function ReferralsModal({ aberto, ao_fechar }) {
    const [itens, definir_itens] = useState([]);
    const [resumo, definir_resumo] = useState(null);
    const [pagina, definir_pagina] = useState(0);
    const [fim, definir_fim] = useState(false);
    const [carregando, definir_carregando] = useState(false);
    const sentinela = useRef(null);

    const carregar = useCallback(async (numero) => {
        definir_carregando(true);

        try {
            const { data } = await api.get('/area-cliente/indicacoes', { params: { pagina: numero, por_pagina: 30 } });
            definir_itens((atuais) => (numero === 1 ? data.data : [...atuais, ...data.data]));
            definir_resumo(data.resumo);
            definir_pagina(numero);
            definir_fim(data.meta.current_page >= data.meta.last_page);
        } catch (erro) {
            alerta_erro(erro);
        } finally {
            definir_carregando(false);
        }
    }, []);

    useEffect(() => {
        if (aberto) carregar(1);
    }, [aberto, carregar]);

    useEffect(() => {
        if (!aberto || fim || !sentinela.current) return undefined;

        const observador = new IntersectionObserver(([entrada]) => {
            if (entrada.isIntersecting && !carregando) carregar(pagina + 1);
        });

        observador.observe(sentinela.current);

        return () => observador.disconnect();
    }, [aberto, fim, carregando, pagina, carregar]);

    return (
        <ModalCard aberto={aberto} titulo="Minhas indicações" ao_fechar={ao_fechar} rodape={<ModalButton type="button" onClick={ao_fechar}>Fechar</ModalButton>}>
            {resumo && (
                <Summary>
                    <div><strong>{resumo.validas}</strong><span>válidas</span></div>
                    <div><strong>{resumo.faltam_para_proximo}</strong><span>para o próximo bônus</span></div>
                    <div><strong>{resumo.bonus_recebidos}</strong><span>bônus recebidos</span></div>
                </Summary>
            )}
            <List>
                {itens.map((item, indice) => (
                    <Item key={`${item.nome}-${item.cadastrado_em}-${indice}`}>
                        <div>
                            <Name>{item.nome}</Name>
                            <When>Cadastro em {item.cadastrado_em.split('-').reverse().join('/')}</When>
                        </div>
                        <Badge $valida={item.situacao === 'Depositou'}>{item.situacao}</Badge>
                    </Item>
                ))}
                {!itens.length && !carregando && <Empty>Ninguém se cadastrou pelo seu link ainda.</Empty>}
                {carregando && <Empty><Spinner tamanho={24} cor="#999" /></Empty>}
                {!fim && <div ref={sentinela} />}
            </List>
        </ModalCard>
    );
}
