// cores, formatos e opções padrão dos gráficos da Dashboard (spec 014, R-15); os valores em dinheiro
// chegam da API como texto com 2 casas ("1234.50" ou "-80.00") e nunca viram ponto flutuante na tela
import { formatar_real, para_centavos } from './money';

// "1234.50" → "R$ 1.234,50"; "-80.00" → "-R$ 80,00"
export const formatar_valor = (texto) => {
    const valor = String(texto ?? '0').trim();
    const negativo = valor.startsWith('-');
    const formatado = `R$ ${formatar_real(para_centavos(negativo ? valor.slice(1) : valor))}`;

    return negativo ? `-${formatado}` : formatado;
};

// valor curto para o eixo dos gráficos: 2500 → "R$ 2,5 mil" (o tooltip mostra o valor completo)
const formato_compacto = new Intl.NumberFormat('pt-BR', { notation: 'compact', maximumFractionDigits: 1 });
export const formatar_compacto = (numero) => `${numero < 0 ? '-' : ''}R$ ${formato_compacto.format(Math.abs(numero))}`;

// valor para o eixo do gráfico (o Chart.js só desenha números; o rótulo usa formatar_valor)
export const valor_grafico = (texto) => {
    const valor = String(texto ?? '0').trim();
    const negativo = valor.startsWith('-');
    const centavos = para_centavos(negativo ? valor.slice(1) : valor);

    return (negativo ? -centavos : centavos) / 100;
};

// cores dos gráficos a partir do tema atual (cor principal, derivada e o modo claro/escuro)
export const cores_graficos = (tema) => ({
    principal: tema.principal,
    derivada: tema.derivada,
    entradas: '#2e7d32',
    saidas: '#c62828',
    comissoes: '#f9a825',
    neutro: tema.modo === 'escuro' ? '#9e9e9e' : '#757575',
    texto: tema.texto_principal,
    grade: tema.modo === 'escuro' ? 'rgba(255, 255, 255, 0.12)' : 'rgba(0, 0, 0, 0.08)',
});

// sequência de cores das roscas (situação dos jogos e apostas)
export const cores_rosca = (tema) => {
    const cores = cores_graficos(tema);

    return [cores.principal, cores.neutro, cores.entradas, cores.comissoes, cores.saidas];
};

// opções padrão: fonte Roboto, legenda embaixo, sem proporção fixa e tooltips em R$ quando o
// gráfico mostra dinheiro
export const opcoes_padrao = (tipo, tema, { dinheiro = false } = {}) => {
    const cores = cores_graficos(tema);
    const rotulo = (valor) => (dinheiro ? formatar_valor(Number(valor).toFixed(2)) : String(valor));
    const rotulo_eixo = (valor) => (dinheiro ? formatar_compacto(Number(valor)) : String(valor));
    const fonte = { family: 'Roboto', size: 11 };

    // nomes das barras sempre na horizontal (sem inclinar em telas pequenas) e o eixo de valores
    // compacto; na barra horizontal os papéis dos eixos se invertem
    const eixo_nomes = {
        ticks: {
            color: cores.texto,
            font: fonte,
            maxRotation: 0,
            minRotation: 0,
            autoSkip: false,
            // nomes longos (jogos, vendedores) cortados no gráfico; a lista abaixo mostra o nome inteiro
            callback(valor) {
                const nome = String(this.getLabelForValue(valor));

                return nome.length > 16 ? `${nome.slice(0, 15)}…` : nome;
            },
        },
        grid: { display: false },
    };
    const eixo_valores = { ticks: { color: cores.texto, font: fonte, callback: rotulo_eixo, maxTicksLimit: 6 }, grid: { color: cores.grade } };
    const eixos = tipo === 'horizontal' ? { x: eixo_valores, y: eixo_nomes } : { x: eixo_nomes, y: eixo_valores };

    // a rosca é fina e separa as fatias com a cor do fundo; a legenda dela é a do ChartCanvas
    const rosca = tipo === 'doughnut'
        ? { cutout: '72%', elements: { arc: { borderColor: tema.fundo_pagina, borderWidth: 2 } } }
        : {};

    return {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: tipo === 'horizontal' ? 'y' : 'x',
        animation: { duration: 300 },
        ...rosca,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    // a rosca entrega o número direto; nas barras, o valor fica no eixo do tipo
                    label: (contexto) => {
                        const numeros = { doughnut: contexto.parsed, horizontal: contexto.parsed.x, bar: contexto.parsed.y };

                        return ` ${contexto.label}: ${rotulo(numeros[tipo])}`;
                    },
                },
            },
        },
        scales: tipo === 'doughnut' ? {} : eixos,
    };
};
