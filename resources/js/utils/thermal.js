import { formatar_data_hora, formatar_dia_mes, formatar_hora } from './dates';
import { formatar_real, para_centavos } from './money';

// texto ESC/POS do bilhete e da tabela para a impressora térmica, no formato do print_ticket_mobile
// e do print_table_mobile do sistema antigo (spec 006, R-06)

const INICIAR = '\u001B@';
const NEGRITO = '\u001BE1';
const CENTRALIZAR = '\u001Ba1';
const DUPLO = '\u001D!\u0001';
const FUNDO_PRETO = '\u001DB1';
const SEM_FUNDO_PRETO = '\u001DB0';
const LINHA = '\n';

// 48 colunas na bobina de 80 mm e 32 na de 58 mm
const colunas_da_largura = (largura) => (Number(largura) === 80 ? 48 : 32);

// a impressora não tem acentos: troca por letras simples e o resto que não for ASCII por "?"
const sem_acentos = (texto) => String(texto ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^\x20-\x7E]/g, '?');

// "rótulo ...... valor" ocupando a linha inteira (spacePrinter do antigo)
const linha_rotulo_valor = (rotulo, valor, colunas, separador = ' ') => {
    const esquerda = sem_acentos(rotulo);
    const direita = sem_acentos(valor);
    const espacos = Math.max(colunas - (esquerda.length + direita.length), 0);

    return `${esquerda}${separador.repeat(espacos)}${direita}`;
};

const tracejado = (colunas) => '-'.repeat(colunas);

const para_bytes = (texto) => new TextEncoder().encode(texto);

const real = (valor) => formatar_real(para_centavos(valor));

// bloco de um palpite: jogos com times e campeonato; especial com a categoria (como no antigo)
const texto_palpite = (palpite, colunas) => {
    const especial = palpite.esporte === 'ESPECIAL';
    const palpite_texto = palpite.jogador ? `${palpite.jogador} (${palpite.jogador_tipo})` : palpite.mercado;
    const partes = [
        `${NEGRITO}${CENTRALIZAR}${sem_acentos(especial ? `Vencedor: ${palpite.time_fora}` : `${palpite.time_casa} x ${palpite.time_fora}`)}${LINHA}${INICIAR}`,
        `${linha_rotulo_valor('Palpite', palpite_texto, colunas)}${LINHA}${INICIAR}`,
        `${linha_rotulo_valor('Cotacao', Number(palpite.cotacao).toFixed(2), colunas)}${LINHA}${INICIAR}`,
        `${linha_rotulo_valor('Horario', formatar_data_hora(palpite.data_inicio), colunas)}${LINHA}${INICIAR}`,
    ];

    if (!especial) {
        partes.push(`${linha_rotulo_valor('Campeonato', String(palpite.campeonato ?? '').substring(0, colunas === 32 ? 20 : 50), colunas)}${LINHA}${INICIAR}`);
    }

    partes.push(`${linha_rotulo_valor('Tipo', palpite.esporte, colunas)}${LINHA}${INICIAR}`);
    partes.push(`${tracejado(colunas)}${LINHA}${INICIAR}`);

    return partes.join('');
};

// bilhete cadastrado ou validado pelo vendedor
export const texto_bilhete = (comprovante, largura) => {
    const colunas = colunas_da_largura(largura);
    const vendedor_paga = para_centavos(comprovante.premio_liquido) !== para_centavos(comprovante.total_a_pagar);

    let texto = `${LINHA}${CENTRALIZAR}${NEGRITO}${DUPLO}${sem_acentos(comprovante.nome_sistema)}${LINHA}${LINHA}${INICIAR}`;
    texto += `${tracejado(colunas)}${LINHA}${INICIAR}`;
    texto += `${linha_rotulo_valor('Codigo', comprovante.codigo.toUpperCase(), colunas)}${LINHA}${INICIAR}`;
    texto += `${linha_rotulo_valor('Cliente', comprovante.nome ?? '', colunas)}${LINHA}${INICIAR}`;
    texto += `${linha_rotulo_valor('Vendedor', comprovante.vendedor ?? '', colunas)}${LINHA}${INICIAR}`;
    texto += `${linha_rotulo_valor('Horario', formatar_data_hora(comprovante.criada_em), colunas)}${LINHA}${INICIAR}`;
    texto += `${tracejado(colunas)}${LINHA}${INICIAR}`;
    texto += comprovante.palpites.map((palpite) => texto_palpite(palpite, colunas)).join('');
    texto += `${linha_rotulo_valor('Qtde. jogos', String(comprovante.palpites.length), colunas)}${LINHA}${INICIAR}`;
    texto += `${linha_rotulo_valor('Cotacao', Number(comprovante.cotacao_total).toFixed(2), colunas)}${LINHA}${INICIAR}`;
    texto += `${linha_rotulo_valor('Valor', real(comprovante.valor), colunas)}${LINHA}${INICIAR}`;
    texto += `${linha_rotulo_valor('Premio', real(comprovante.total_a_pagar), colunas)}${LINHA}${INICIAR}`;

    if (vendedor_paga) texto += `${linha_rotulo_valor('Vendedor Paga', real(comprovante.premio_liquido), colunas)}${LINHA}${INICIAR}`;

    texto += `${LINHA}${sem_acentos('E obrigatorio a apresentacao desse comprovante para retirada da premiacao.')}${LINHA}${INICIAR}`;
    if (comprovante.assinatura) texto += `${LINHA}${sem_acentos(comprovante.assinatura)}${LINHA}${INICIAR}`;
    if (comprovante.mensagem_bilhete) texto += `${LINHA}${sem_acentos(comprovante.mensagem_bilhete)}${LINHA}${LINHA}${LINHA}${INICIAR}`;
    texto += '\n\r';

    return para_bytes(texto);
};

// cotação com 4 caracteres, como o odd() do antigo
const cotacao_curta = (valor) => Number(valor ?? 1).toFixed(2).substring(0, 4);

// linhas de cotações de um jogo (12 colunas em duas linhas; as três primeiras com fundo preto)
const linhas_cotacoes = (cotacoes, colunas) => {
    const c = (codigo) => cotacao_curta(cotacoes[codigo]);
    const destaque = (codigo) => `${FUNDO_PRETO}${c(codigo)}${SEM_FUNDO_PRETO}`;

    if (colunas === 48) {
        return `${destaque('odd1')}     ${destaque('odd2')}     ${destaque('odd3')}     ${c('odd4')}    ${c('odd116')}     ${c('odd10')}${LINHA}${INICIAR}`
            + `${c('odd15')}     ${c('odd17')}     ${c('odd16')}     ${c('odd7')}    ${c('odd123')}     ${c('odd13')}${LINHA}${INICIAR}`;
    }

    return `${destaque('odd1')}  ${destaque('odd2')} ${destaque('odd3')} ${c('odd4')}  ${c('odd116')}  ${c('odd10')}${LINHA}${INICIAR}`
        + `${c('odd15')}  ${c('odd17')} ${c('odd16')} ${c('odd7')}  ${c('odd123')}  ${c('odd13')}${LINHA}${INICIAR}`;
};

// cabeçalho das colunas, repetido a cada 5 campeonatos
const cabecalho_colunas = (colunas) => (colunas === 48
    ? `CASA     EMP      FORA     AMB     +2.5     DP.C${LINHA}${INICIAR}GM.C     GM.F     2GMC     N.A     -2.5     DP.F${LINHA}${LINHA}${INICIAR}`
    : `CASA  EMP  FORA  AMB  +2.5  DP.C${LINHA}${INICIAR}GMC   GMF  2GMC  N.A  -2.5  DP.F${LINHA}${LINHA}${INICIAR}`);

// tabela de jogos do vendedor
export const texto_tabela = (tabela, largura) => {
    const colunas = colunas_da_largura(largura);
    const caracteres_time = colunas === 48 ? 16 : 11;

    let texto = `${LINHA}${CENTRALIZAR}${NEGRITO}${DUPLO}${sem_acentos(tabela.nome_sistema)}${LINHA}${LINHA}${INICIAR}`;
    texto += `${LINHA}${CENTRALIZAR}${NEGRITO}ATUALIZADA: ${formatar_data_hora(tabela.atualizada_em)}${LINHA}${INICIAR}`;

    tabela.campeonatos.forEach((campeonato, indice) => {
        texto += `${tracejado(colunas)}${INICIAR}`;
        texto += `${CENTRALIZAR}${sem_acentos(campeonato.nome.substring(0, 25).toUpperCase())}${LINHA}${INICIAR}`;
        texto += `${tracejado(colunas)}${LINHA}${INICIAR}`;

        if (indice % 5 === 0) texto += cabecalho_colunas(colunas);

        campeonato.confrontos.forEach((confronto, posicao) => {
            const jogo = `${confronto.time_casa.substring(0, caracteres_time)} x ${confronto.time_fora.substring(0, caracteres_time)}`;
            const horario = colunas === 48
                ? `${formatar_dia_mes(confronto.data_inicio)} ${formatar_hora(confronto.data_inicio)}`
                : formatar_hora(confronto.data_inicio);

            texto += `${NEGRITO}${linha_rotulo_valor(jogo, horario, colunas)}${LINHA}${INICIAR}`;
            texto += linhas_cotacoes(confronto.cotacoes, colunas);

            if (posicao < campeonato.confrontos.length - 1) texto += `${tracejado(colunas)}${LINHA}${INICIAR}`;
        });
    });

    texto += `${LINHA}${LINHA}${INICIAR}\n\r`;

    return para_bytes(texto);
};
