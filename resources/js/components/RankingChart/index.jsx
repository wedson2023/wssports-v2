import { useMemo } from 'react';
import { useTheme } from 'styled-components';

import ChartCanvas from '../ChartCanvas';
import { cores_graficos, formatar_valor, opcoes_padrao, valor_grafico } from '../../utils/charts';
import { Index, List, Name, Row, Value } from './styles';

// ranking em barras horizontais e, abaixo, a lista como as tabelas do antigo (spec 014,
// contracts/paginas §8); itens: [{ nome, quantidade, valor? }]; o gráfico usa o valor quando houver
export default function RankingChart({ itens }) {
    const tema = useTheme();
    const usa_valor = itens.some((item) => item.valor !== undefined);

    const dados = useMemo(() => ({
        labels: itens.map((item) => item.nome),
        datasets: [{
            data: itens.map((item) => (usa_valor ? valor_grafico(item.valor) : item.quantidade)),
            backgroundColor: cores_graficos(tema).principal,
            borderRadius: 3,
        }],
    }), [itens, usa_valor, tema]);

    const opcoes = useMemo(() => opcoes_padrao('horizontal', tema, { dinheiro: usa_valor }), [tema, usa_valor]);

    return (
        <>
            <ChartCanvas tipo="horizontal" dados={dados} opcoes={opcoes} altura={Math.max(itens.length * 28, 120)} />
            <List>
                {itens.map((item, indice) => (
                    <Row key={`${item.nome}-${indice}`}>
                        <Index>{indice + 1}º</Index>
                        <Name title={item.nome}>{item.nome}</Name>
                        <Value>{item.quantidade}</Value>
                        {usa_valor && <Value>{formatar_valor(item.valor)}</Value>}
                    </Row>
                ))}
            </List>
        </>
    );
}
