import { formatar_data_hora } from './dates';
import { formatar_real, para_centavos } from './money';

// tempo para o navegador abrir a impressão antes de remover o quadro
const tempo_remocao_ms = 60000;

const escapar = (texto) => String(texto ?? '').replace(/[&<>"']/g, (caractere) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
})[caractere]);

const real = (valor) => `R$ ${formatar_real(para_centavos(valor))}`;

// linha "rótulo ... valor" do comprovante
const linha = (rotulo, valor, estilo = 'font-size:0.7em; margin:0; padding:0;') =>
    `<p style="${estilo}"><strong>${escapar(rotulo)}</strong><span style="float:right; font-size:0.9em;"><strong>${escapar(valor)}</strong></span></p>`;

const linha_palpite = (rotulo, valor) =>
    `<span><strong>${escapar(rotulo)}</strong><time style="float:right; font-size:0.8em;"><strong>${escapar(valor)}</strong></time></span><br>`;

// palpite especial: "Vencedor: categoria" no lugar dos times e sem campeonato (spec 006, FR-019)
const palpite_html = (palpite) => [
    '<li style="border-bottom:solid thin #000; padding:3px 0; font-size:0.85em;"><p style="font-size:0.9em; margin:0;">',
    `<strong style="text-transform:uppercase; margin-bottom:3px; font-size:0.9em; text-align:center; display:block;">${escapar(palpite.esporte === 'ESPECIAL' ? `Vencedor: ${palpite.time_fora}` : `${palpite.time_casa} x ${palpite.time_fora}`)}</strong>`,
    palpite.esporte === 'ESPECIAL' ? '' : linha_palpite('Campeonato', palpite.campeonato),
    linha_palpite('Horário', formatar_data_hora(palpite.data_inicio)),
    linha_palpite('Palpite', palpite.jogador ? `${palpite.jogador} (${palpite.jogador_tipo})` : palpite.mercado),
    linha_palpite('Cotação', Number(palpite.cotacao).toFixed(2)),
    linha_palpite('Tipo', palpite.esporte),
    '</p></li>',
].join('');

// comprovante do bilhete como o print_ticket do sistema antigo (impressão pelo navegador)
const comprovante_html = (comprovante) => {
    const vendedor_paga = para_centavos(comprovante.premio_liquido) !== para_centavos(comprovante.total_a_pagar);

    return [
        '<div style="width:93%; box-sizing:border-box; padding:5px 3.5%; font-family:arial; background:#fff;">',
        `<p align="center"><strong>${escapar(comprovante.nome_sistema)}</strong></p>`,
        linha('Código', comprovante.codigo.toUpperCase()),
        linha('Cliente', comprovante.nome),
        linha('Vendedor', comprovante.vendedor),
        linha('Horário', formatar_data_hora(comprovante.criada_em), 'font-size:0.7em; margin:0;'),
        '<ul style="margin:5px 0; padding:0; list-style:none; border-top:solid thin #000;">',
        comprovante.palpites.map(palpite_html).join(''),
        '</ul>',
        linha('Valor', real(comprovante.valor), 'font-size:0.7em; margin:0;'),
        linha('Qtde. Jogos', comprovante.palpites.length, 'font-size:0.7em; margin:0;'),
        linha('Cotação Total', Number(comprovante.cotacao_total).toFixed(2), 'font-size:0.7em; margin:0;'),
        vendedor_paga
            ? linha('Prêmio', real(comprovante.total_a_pagar), 'font-size:0.7em; margin:0;')
                + linha('Vendedor Paga', real(comprovante.premio_liquido), 'font-size:0.7em; margin:0;')
            : `<p style="margin:5px 0 0; padding:5px; border:solid thin #000; text-align:center; font-size:0.85em;"><strong>Prêmio ${escapar(real(comprovante.total_a_pagar))} Reais</strong></p>`,
        '<p style="font-size:0.7em; margin-top:15px; padding:0;">É obrigatório a apresentação desse comprovante para retirada da premiação.</p>',
        comprovante.assinatura ? `<p style="font-size:0.7em; margin-top:15px; padding:0;">${escapar(comprovante.assinatura)}</p>` : '',
        comprovante.mensagem_bilhete ? `<p style="font-size:0.7em; margin-top:15px; padding:0;">${escapar(comprovante.mensagem_bilhete)}</p>` : '',
        '</div>',
    ].join('');
};

// imprime o HTML num quadro invisível (sem abrir aba nova nem esbarrar no bloqueio de pop-up)
const imprimir_html = (titulo, corpo) => {
    const quadro = document.createElement('iframe');
    quadro.setAttribute('aria-hidden', 'true');
    quadro.style.cssText = 'position:fixed; width:0; height:0; border:0; right:0; bottom:0;';
    document.body.appendChild(quadro);

    const documento = quadro.contentWindow.document;
    documento.open();
    documento.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escapar(titulo)}</title></head><body style="margin:0;">${corpo}</body></html>`);
    documento.close();

    quadro.contentWindow.focus();
    quadro.contentWindow.print();

    setTimeout(() => quadro.remove(), tempo_remocao_ms);
};

export const imprimir_bilhete = (comprovante) => imprimir_html(comprovante.codigo.toUpperCase(), comprovante_html(comprovante));

// colunas da tabela no navegador, em duas linhas por jogo, como o table_html do sistema antigo
const colunas_tabela = [
    [['CASA', 'odd1'], ['EMP', 'odd2'], ['FORA', 'odd3'], ['AMB', 'odd4'], ['+2.5', 'odd116'], ['DPC', 'odd10'], ['CGF', 'odd135']],
    [['GMC', 'odd15'], ['GMF', 'odd17'], ['2GMC', 'odd16'], ['N.A', 'odd7'], ['-2.5', 'odd123'], ['DPF', 'odd13'], ['FGC', 'odd139']],
];

// as três primeiras cotações (Casa, Empate e Fora) têm borda
const com_borda = ['odd1', 'odd2', 'odd3'];

const celula_titulo = (titulo) => `<th width="6.5%" align="center"><span style="font-size:0.6em; padding:3px 0; display:block;">${titulo}</span></th>`;

const celula_cotacao = (codigo, cotacoes) => {
    const estilo = com_borda.includes(codigo)
        ? 'padding:3px 0; border: solid thin #000; color: #000; font-size:0.6em; display:block;'
        : 'font-size:0.6em; padding:3px 0; display:block;';

    return `<td width="6.5%" align="center"><span style="${estilo}">${escapar(Number(cotacoes[codigo] ?? 1).toFixed(2))}</span></td>`;
};

const tabela_html = (tabela) => [
    '<div style="margin-left: 3px; box-sizing: border-box; font-family:Arial; width: 93%;">',
    `<p style="text-transform:uppercase; text-align:center; font-size:0.6em; padding:3px; display:block;"><strong>${escapar(tabela.nome_sistema.toUpperCase())}</strong> - ATUALIZADA: ${escapar(formatar_data_hora(tabela.atualizada_em))}</p>`,
    '<table style="width:100%; border: none;">',
    tabela.campeonatos.map((campeonato) => [
        `<tr><td colspan="8"><strong style="font-size:0.6em; text-align: center; letter-spacing:2px; font-weight: bold; background: #000; color: #fff; padding:3px; display:block;">${escapar(campeonato.nome)}</strong></td></tr>`,
        colunas_tabela.map((linha_colunas) => `<tr>${linha_colunas.map(([titulo]) => celula_titulo(titulo)).join('')}</tr>`).join(''),
        campeonato.confrontos.map((confronto) => [
            `<tr><td colspan="8" align="left"><div style="font-size:0.6em; text-transform:uppercase; font-family:Arial; padding:3px; display:block; border-bottom:solid thin #000;"><strong>${escapar(`${formatar_data_hora(confronto.data_inicio)} - ${confronto.time_casa} x ${confronto.time_fora}`)}</strong></div></td></tr>`,
            colunas_tabela.map((linha_colunas) => `<tr>${linha_colunas.map(([, codigo]) => celula_cotacao(codigo, confronto.cotacoes)).join('')}</tr>`).join(''),
        ].join('')).join(''),
    ].join('')).join(''),
    '</table></div>',
].join('');

// tabela de jogos do vendedor pela impressão do navegador (spec 006, FR-008)
export const imprimir_tabela = (tabela) => imprimir_html(`Tabela - ${tabela.nome_sistema}`, tabela_html(tabela));
