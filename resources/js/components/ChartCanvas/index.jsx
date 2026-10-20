import { useEffect, useRef, useState } from 'react';

import { Area, Canvas, Center, CenterLabel, CenterValue, Container, Dot, Legend, LegendItem, LegendLabel, LegendValue, Skeleton } from './styles';

// tipos aceitos → tipo do Chart.js (a barra horizontal é uma barra com indexAxis 'y' nas opções)
const tipos_chart = { bar: 'bar', doughnut: 'doughnut', horizontal: 'bar' };

// percentual da fatia, sem casas decimais (0% quando o total é zero)
const percentual = (valor, total) => (total > 0 ? `${((valor / total) * 100).toFixed(0)}%` : '0%');

// gráfico do Chart.js; a biblioteca vem por import() dinâmico, num pedaço separado do build, para
// não pesar a tela principal (spec 014, FR-043). Na rosca, legenda própria em grade centralizada
// (cor, nome, quantidade e %) e o total no centro (centro = { valor, rotulo })
export default function ChartCanvas({ tipo = 'bar', dados, opcoes, altura = 220, legenda = false, centro = null }) {
    const canvas = useRef(null);
    const grafico = useRef(null);
    const [carregado, definir_carregado] = useState(false);

    useEffect(() => {
        let ativo = true;

        import('chart.js/auto').then(({ default: Chart }) => {
            if (!ativo || !canvas.current) return;

            grafico.current = new Chart(canvas.current, { type: tipos_chart[tipo], data: dados, options: opcoes });
            definir_carregado(true);
        });

        return () => {
            ativo = false;
            grafico.current?.destroy();
            grafico.current = null;
        };
    }, [tipo]);

    // dados novos (atualização da seção) entram no mesmo gráfico, sem recriar
    useEffect(() => {
        if (!grafico.current) return;

        grafico.current.data = dados;
        grafico.current.options = opcoes;
        grafico.current.update();
    }, [dados, opcoes]);

    const valores = dados.datasets[0]?.data ?? [];
    const cores = dados.datasets[0]?.backgroundColor ?? [];
    const total = valores.reduce((soma, valor) => soma + valor, 0);

    return (
        <Container>
            <Area $altura={altura}>
                {!carregado && <Skeleton />}
                <Canvas ref={canvas} $visivel={carregado} />
                {centro && carregado && (
                    <Center>
                        <CenterValue>{centro.valor}</CenterValue>
                        <CenterLabel>{centro.rotulo}</CenterLabel>
                    </Center>
                )}
            </Area>
            {legenda && (
                <Legend>
                    {dados.labels.map((rotulo, indice) => (
                        <LegendItem key={rotulo}>
                            <Dot style={{ backgroundColor: cores[indice] }} />
                            <LegendLabel>{rotulo}</LegendLabel>
                            <LegendValue>
                                {valores[indice]} · {percentual(valores[indice], total)}
                            </LegendValue>
                        </LegendItem>
                    ))}
                </Legend>
            )}
        </Container>
    );
}
