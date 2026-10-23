import { useEffect, useState } from 'react';

import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { formatar_data_hora } from '../../utils/dates';
import { Empty, Filters, Input, Loading, Money, More, Select, Statement, StatementRow } from './styles';

const carteiras = ['Saldo', 'Promoção esportes', 'Promoção cassino'];

// extrato do cliente pelo painel (spec 019, FR-015; GET /clientes/{id}/transacoes da spec 002): data,
// origem, carteira, valor, saldo depois, autor e observação, com filtros de data e carteira e
// "carregar mais" (o extrato da área do cliente tem outro formato e outras colunas)
export default function ClientStatement({ cliente, token }) {
    const [filtros, definir_filtros] = useState({ data_inicial: '', data_final: '', carteira: '' });
    const [itens, definir_itens] = useState([]);
    const [pagina, definir_pagina] = useState({ atual: 1, ultima: 1 });
    const [carregando, definir_carregando] = useState(true);

    const carregar = async (numero, somar) => {
        definir_carregando(true);

        try {
            const parametros = Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor));
            const { data } = await api.get(`/clientes/${cliente.id}/transacoes`, { params: { ...parametros, page: numero }, headers: { Authorization: `Bearer ${token}` } });
            definir_itens((atuais) => (somar ? [...atuais, ...data.data] : data.data));
            definir_pagina({ atual: data.meta.current_page, ultima: data.meta.last_page });
        } catch (erro) {
            alerta_erro(erro);
        } finally {
            definir_carregando(false);
        }
    };

    useEffect(() => {
        carregar(1, false);
    }, [cliente.id, filtros]);

    const mudar = (mudanca) => definir_filtros((atual) => ({ ...atual, ...mudanca }));

    return (
        <>
            <Filters>
                <Input type="date" aria-label="Data inicial" value={filtros.data_inicial} onChange={(evento) => mudar({ data_inicial: evento.target.value })} />
                <Input type="date" aria-label="Data final" value={filtros.data_final} onChange={(evento) => mudar({ data_final: evento.target.value })} />
                <Select aria-label="Carteira" value={filtros.carteira} onChange={(evento) => mudar({ carteira: evento.target.value })}>
                    <option value="">Todas as carteiras</option>
                    {carteiras.map((carteira) => <option key={carteira} value={carteira}>{carteira}</option>)}
                </Select>
            </Filters>
            {!itens.length && !carregando && <Empty>Nenhuma transação no filtro.</Empty>}
            <Statement>
                {itens.map((item) => (
                    <StatementRow key={item.id}>
                        <div>
                            <strong>{item.origem}</strong>
                            <span>{`${formatar_data_hora(item.created_at)} · ${item.carteira}`}</span>
                            {(item.autor || item.observacao) && <span>{[item.autor?.nome, item.observacao].filter(Boolean).join(' · ')}</span>}
                        </div>
                        <div>
                            <Money $sinal={item.tipo === 'Crédito' ? 1 : -1}>{`${item.tipo === 'Crédito' ? '+' : '-'}${formatar_valor(item.valor)}`}</Money>
                            <span>{`Saldo ${formatar_valor(item.saldo_posterior)}`}</span>
                        </div>
                    </StatementRow>
                ))}
            </Statement>
            {carregando && <Loading><Spinner tamanho={24} cor="currentColor" /></Loading>}
            {!carregando && pagina.atual < pagina.ultima && <More type="button" onClick={() => carregar(pagina.atual + 1, true)}>Carregar mais</More>}
        </>
    );
}
