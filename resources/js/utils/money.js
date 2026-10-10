// dinheiro e cotações sem ponto flutuante (R-16): valores em centavos e cotações em centésimos,
// com BigInt, espelhando app/Services/CalculoPremio.php (prêmio truncado em centavos)

const minimo_palpites_acrescimo = 3;

// "10", "10,5", "10.50" → 1050; vazio, inválido ou negativo → 0
export const para_centavos = (texto) => {
    const limpo = String(texto ?? '').trim().replace(',', '.');

    if (!/^\d+(\.\d{0,2})?$/.test(limpo)) return 0;

    const [inteiro, decimal = ''] = limpo.split('.');

    return Number(inteiro) * 100 + Number(decimal.padEnd(2, '0'));
};

// 1050 → "10.50" (formato da API)
export const centavos_para_texto = (centavos) => {
    const absoluto = Math.abs(Math.trunc(centavos));

    return `${Math.trunc(absoluto / 100)}.${String(absoluto % 100).padStart(2, '0')}`;
};

// 1050 → "10,50" (formato da tela)
export const formatar_real = (centavos) => {
    const [inteiro, decimal] = centavos_para_texto(centavos).split('.');

    return `${Number(inteiro).toLocaleString('pt-BR')},${decimal}`;
};

// cotação (número ou texto com até 2 casas) → centésimos em BigInt: 2.1 → 210n
const cotacao_em_centesimos = (cotacao) => BigInt(para_centavos(Number(cotacao).toFixed(2)));

const menor = (...valores) => valores.reduce((atual, valor) => (valor < atual ? valor : atual));

// produto das cotações arredondado em 2 casas, meio para cima (CalculoPremio::arredondar)
export const cotacao_total = (cotacoes) => {
    if (!cotacoes.length) return '0.00';

    const produto = cotacoes.reduce((acumulado, cotacao) => acumulado * cotacao_em_centesimos(cotacao), 1n);
    const fator = 10n ** BigInt(2 * cotacoes.length - 2);
    const centesimos = (produto + fator / 2n) / fator;

    return centavos_para_texto(Number(centesimos));
};

// prêmio estimado do cupom (o valor final é do backend):
// - prêmio = menor entre (valor × cotações, truncado), valor × multiplicador e prêmio máximo;
// - acréscimo de ganho_multiplo_palpites% com 3 ou mais palpites, limitado ao prêmio máximo
export const calcular_premio = ({ valor_centavos, cotacoes, multiplicador, premio_maximo, ganho_multiplo_palpites }) => {
    const zerado = { cotacao_total: cotacao_total(cotacoes), premio_centavos: 0, acrescimo_centavos: 0, total_centavos: 0 };

    if (!cotacoes.length || valor_centavos <= 0) return zerado;

    const valor = BigInt(valor_centavos);
    const produto = cotacoes.reduce((acumulado, cotacao) => acumulado * cotacao_em_centesimos(cotacao), 1n);
    const escala = 100n ** BigInt(cotacoes.length);
    const maximo = BigInt(para_centavos(premio_maximo));

    const premio = menor(valor * produto / escala, valor * BigInt(multiplicador), maximo);

    let acrescimo = 0n;
    const ganho = BigInt(para_centavos(ganho_multiplo_palpites));

    if (cotacoes.length >= minimo_palpites_acrescimo && ganho > 0n) {
        const folga = maximo - premio;
        acrescimo = menor(premio * ganho / 10000n, folga > 0n ? folga : 0n);
    }

    return {
        cotacao_total: zerado.cotacao_total,
        premio_centavos: Number(premio),
        acrescimo_centavos: Number(acrescimo),
        total_centavos: Number(premio + acrescimo),
    };
};
