import { abas_cotacoes } from './market_groups';

// tela "Cotações" do painel (spec 016): grupos de mercado, cálculo da prévia e formatação

// grupos de mercado na ordem do modal de mercados do site, sem "Favoritas" (repete códigos), mais
// Jogadores e Outras (códigos sem grupo); cada código aparece uma vez só, no primeiro grupo
export const grupos_mercado = (todos_codigos) => {
    const usados = new Set();
    const grupos = [];

    abas_cotacoes.filter((aba) => aba.chave !== 'favoritas').forEach((aba) => {
        aba.categorias.forEach((categoria) => {
            const codigos = categoria.codigos.filter((codigo) => !usados.has(codigo));

            codigos.forEach((codigo) => usados.add(codigo));
            if (codigos.length) grupos.push({ chave: `${aba.chave}|${categoria.titulo}`, titulo: categoria.titulo, aba: aba.titulo, codigos });
        });
    });

    grupos.push({ chave: 'jogadores', titulo: 'JOGADORES', aba: 'Jogadores', codigos: ['jogador'] });
    usados.add('jogador');

    const outras = todos_codigos.filter((codigo) => !usados.has(codigo));

    if (outras.length) grupos.push({ chave: 'outras', titulo: 'OUTRAS', aba: 'Outras', codigos: outras });

    return grupos;
};

// texto sem acento e em minúsculas, para a busca
export const normalizar = (texto) => String(texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

// "−5,5" / "-5.5" / "+3" → número; vazio → null; inválido → NaN
export const ler_numero = (texto) => {
    const limpo = String(texto ?? '').trim().replace('−', '-').replace(',', '.').replace(/^\+/, '');

    if (limpo === '') return null;

    return /^-?\d+(\.\d+)?$/.test(limpo) ? Number(limpo) : NaN;
};

// número no padrão brasileiro com 2 casas: 1.8 → "1,80"
export const formatar_cotacao = (valor) => Number(valor ?? 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// ajuste com sinal: 3 → "+3%", -5.5 → "−5,5%"; unidade "%" ou "" (diferença de cotação)
export const formatar_ajuste = (valor, unidade = '%') => {
    if (valor === null || valor === undefined || valor === 0) return `0${unidade}`;

    const numero = Math.abs(valor).toLocaleString('pt-BR', { maximumFractionDigits: 2 });

    return `${valor > 0 ? '+' : '−'}${numero}${unidade}`;
};

// mesma regra de CalculoCotacoes::aplicar: zerada continua zerada; senão provedor + provedor ×
// soma das porcentagens ÷ 100 + valor do jogo, limitada ao teto e ao máximo, nunca menor que 1,00
export const calcular_cotacao = ({ provedor, porcentagem, jogo = 0, teto = null, maximo = null }) => {
    if (!provedor || provedor <= 0) return { valor: 0, indisponivel: true };

    let valor = provedor + (provedor * porcentagem) / 100 + jogo;
    let limitada_teto = false;

    if (teto !== null && valor > teto) {
        valor = teto;
        limitada_teto = true;
    }

    if (maximo !== null && valor > maximo) {
        valor = maximo;
        limitada_teto = true;
    }

    const limitada_minimo = valor < 1;

    return { valor: Math.round(Math.max(valor, 1) * 100) / 100, limitada_teto, limitada_minimo, indisponivel: false };
};
