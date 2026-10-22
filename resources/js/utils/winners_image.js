import { formatar_valor } from './charts';
import { texto_periodo } from './periods';

// imagem dos premiados do período (spec 018, R-07), desenhada com a API Canvas 2D do navegador, no
// lugar do printWinner (html2canvas) do antigo: logo da banca, título com o período e a tabela
// CÓDIGO, VENDEDOR, CLIENTE, VALOR, PRÊMIO e SITUAÇÃO nas cores do tema

const LARGURA = 600;
const MARGEM = 12;
const ALTURA_LINHA = 30;
const ALTURA_LOGO = 70;

// largura de cada coluna (somam a largura útil) e alinhamento
const colunas = [
    { titulo: 'CÓDIGO', largura: 86, chave: 'codigo' },
    { titulo: 'VENDEDOR', largura: 120, chave: 'vendedor' },
    { titulo: 'CLIENTE', largura: 120, chave: 'cliente' },
    { titulo: 'VALOR', largura: 80, chave: 'valor', direita: true },
    { titulo: 'PRÊMIO', largura: 90, chave: 'premio', direita: true },
    { titulo: 'SITUAÇÃO', largura: 80, chave: 'situacao_exibida' },
];

const carregar_imagem = (endereco) => new Promise((resolver) => {
    const imagem = new Image();

    imagem.onload = () => resolver(imagem);
    // sem logo (erro ao carregar), a imagem sai só com o nome do sistema
    imagem.onerror = () => resolver(null);
    imagem.src = endereco;
});

// corta o texto com reticências para caber na largura
const caber = (contexto, texto, largura) => {
    const valor = String(texto ?? '—');

    if (contexto.measureText(valor).width <= largura) return valor;

    let cortado = valor;

    while (cortado.length > 1 && contexto.measureText(`${cortado}…`).width > largura) {
        cortado = cortado.slice(0, -1);
    }

    return `${cortado}…`;
};

const texto_celula = (coluna, aposta) => {
    if (coluna.chave === 'valor' || coluna.chave === 'premio') return formatar_valor(aposta[coluna.chave]);
    if (coluna.chave === 'codigo') return aposta.codigo.toUpperCase();

    return aposta[coluna.chave];
};

// dados = resposta de GET /apostas/premiados; cores = { principal, fundo, texto, linha_alternada }.
// Devolve o PNG em Blob
export const gerar_imagem_premiados = async (dados, cores) => {
    const logo = await carregar_imagem(dados.url_logo);
    const altura = MARGEM + ALTURA_LOGO + 34 + ALTURA_LINHA * (dados.data.length + 1) + MARGEM;
    const canvas = document.createElement('canvas');
    const escala = 2;

    canvas.width = LARGURA * escala;
    canvas.height = altura * escala;

    const contexto = canvas.getContext('2d');
    contexto.scale(escala, escala);
    contexto.textBaseline = 'middle';

    contexto.fillStyle = cores.fundo;
    contexto.fillRect(0, 0, LARGURA, altura);

    // logo centralizado (ou o nome do sistema)
    if (logo) {
        const largura_logo = Math.min(200, (logo.width / logo.height) * ALTURA_LOGO);
        contexto.drawImage(logo, (LARGURA - largura_logo) / 2, MARGEM, largura_logo, (largura_logo * logo.height) / logo.width);
    } else {
        contexto.fillStyle = cores.texto;
        contexto.font = 'bold 22px Roboto, Arial, sans-serif';
        contexto.textAlign = 'center';
        contexto.fillText(dados.nome_sistema, LARGURA / 2, MARGEM + ALTURA_LOGO / 2);
    }

    let y = MARGEM + ALTURA_LOGO + 17;
    contexto.fillStyle = cores.texto;
    contexto.font = 'bold 15px Roboto, Arial, sans-serif';
    contexto.textAlign = 'center';
    contexto.fillText(`Premiados · ${texto_periodo(dados.periodo)}`, LARGURA / 2, y);

    // títulos das colunas na cor principal
    y += 17;
    contexto.fillStyle = cores.principal;
    contexto.fillRect(MARGEM, y, LARGURA - 2 * MARGEM, ALTURA_LINHA);

    const escrever_linha = (valores, topo, cor, fonte) => {
        let x = MARGEM;
        contexto.font = fonte;
        contexto.fillStyle = cor;

        colunas.forEach((coluna, indice) => {
            const texto = caber(contexto, valores[indice], coluna.largura - 12);
            contexto.textAlign = coluna.direita ? 'right' : 'left';
            contexto.fillText(texto, coluna.direita ? x + coluna.largura - 6 : x + 6, topo + ALTURA_LINHA / 2);
            x += coluna.largura;
        });
    };

    escrever_linha(colunas.map((coluna) => coluna.titulo), y, '#fff', 'bold 11px Roboto, Arial, sans-serif');

    dados.data.forEach((aposta, indice) => {
        y += ALTURA_LINHA;

        if (indice % 2 === 1) {
            contexto.fillStyle = cores.linha_alternada;
            contexto.fillRect(MARGEM, y, LARGURA - 2 * MARGEM, ALTURA_LINHA);
        }

        escrever_linha(colunas.map((coluna) => texto_celula(coluna, aposta)), y, cores.texto, '12px Roboto, Arial, sans-serif');
    });

    return new Promise((resolver) => canvas.toBlob(resolver, 'image/png'));
};
