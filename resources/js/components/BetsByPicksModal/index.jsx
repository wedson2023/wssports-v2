import { useEffect, useMemo, useState } from 'react';
import { useTheme } from 'styled-components';

import CashModal, { Hint } from '../CashModal';
import ChartCanvas from '../ChartCanvas';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { cores_graficos, opcoes_padrao } from '../../utils/charts';
import { texto_periodo } from '../../utils/periods';
import { Empty } from './styles';

// gráfico do vendedor (spec 017, FR-029; modals/graphic do antigo): quantidade de apostas do período
// por número de palpites, de 1 a 10 e "Mais de 10", em barras
export default function BetsByPicksModal({ vendedor, periodo, token, ao_fechar }) {
    const tema = useTheme();
    const [estado, definir_estado] = useState({ faixas: null, carregando: true, erro: null });

    useEffect(() => {
        if (!vendedor) return undefined;

        let ativo = true;
        definir_estado({ faixas: null, carregando: true, erro: null });

        api.get(`/caixa/vendedores/${vendedor.id}/grafico`, { params: periodo, headers: { Authorization: `Bearer ${token}` } })
            .then(({ data }) => ativo && definir_estado({ faixas: data.faixas, carregando: false, erro: null }))
            .catch((erro) => ativo && definir_estado({ faixas: null, carregando: false, erro }));

        return () => {
            ativo = false;
        };
    }, [vendedor?.id, periodo.de, periodo.ate]);

    const dados = useMemo(() => estado.faixas && ({
        labels: estado.faixas.map((faixa) => faixa.palpites),
        datasets: [{ data: estado.faixas.map((faixa) => faixa.apostas), backgroundColor: cores_graficos(tema).principal, borderRadius: 3 }],
    }), [estado.faixas, tema]);

    const opcoes = useMemo(() => opcoes_padrao('bar', tema), [tema]);
    const total = estado.faixas?.reduce((soma, faixa) => soma + faixa.apostas, 0) ?? 0;

    return (
        <CashModal
            aberto={Boolean(vendedor)}
            icone="insert_chart_outlined"
            titulo={vendedor ? `Apostas por palpites · ${vendedor.nome}` : 'Apostas por palpites'}
            descricao="Quantidade de apostas do período por número de palpites."
            ao_fechar={ao_fechar}
            largura={640}
        >
            <Hint>{`Período: ${texto_periodo(periodo)}`}</Hint>
            {estado.carregando && <Empty><Spinner tamanho={30} cor="currentColor" /></Empty>}
            {!estado.carregando && estado.erro && <Empty>{estado.erro.response?.data?.message ?? 'Não foi possível carregar o gráfico.'}</Empty>}
            {!estado.carregando && !estado.erro && total === 0 && <Empty>Nenhuma aposta no período.</Empty>}
            {!estado.carregando && dados && total > 0 && <ChartCanvas tipo="bar" dados={dados} opcoes={opcoes} altura={260} />}
        </CashModal>
    );
}
