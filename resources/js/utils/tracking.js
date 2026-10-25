import api from './api';
import { gravar_json, ler_json } from './storage';

// Google Tag Manager das páginas do apostador (spec 021, FR-009 e FR-010, research R-14 e R-15):
// o contêiner é carregado uma vez pelo site e os eventos vão para o dataLayer nos mesmos pontos do
// sistema antigo (cadastro, Pix gerado e depósito confirmado)

// depósitos cujo "purchase" já foi enviado, para não repetir (últimos 50)
const chave_compras = 'wssports.compras_enviadas';
const MAXIMO_COMPRAS = 50;

// consulta do depósito pendente depois que o modal fecha
const INTERVALO_COMPRA = 10000;

let gtm_carregado = false;
const acompanhando = new Set();

export const carregar_gtm = (id) => {
    if (gtm_carregado || !id) return;

    gtm_carregado = true;
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(id)}`;
    document.head.appendChild(script);
};

// sem GTM carregado (sem GTM-ID ou fora das páginas do apostador), não envia nada
export const enviar_evento = (evento, dados = {}) => {
    if (!gtm_carregado) return;

    window.dataLayer.push({ event: evento, ...dados });
};

// valor do depósito em reais com 2 casas, como número ("50.00" → 50)
const valor_em_reais = (valor) => Number(Number(valor).toFixed(2));

// "purchase" uma vez por depósito, mesmo que a confirmação chegue de novo
export const enviar_compra = (codigo, valor) => {
    if (!gtm_carregado || !codigo) return;

    const enviadas = ler_json(chave_compras) ?? [];

    if (enviadas.includes(codigo)) return;

    gravar_json(chave_compras, [...enviadas, codigo].slice(-MAXIMO_COMPRAS));
    enviar_evento('purchase', { amount: valor_em_reais(valor) });
};

export const enviar_pix_gerado = (valor) => enviar_evento('addtocart', { amount: valor_em_reais(valor) });

// depósito ainda pendente quando o modal fecha: continua consultando enquanto a página estiver
// aberta, até aprovar (envia o "purchase"), terminar de outro jeito ou expirar
export const acompanhar_compra = (codigo, valor, expira_em = null) => {
    if (!gtm_carregado || !codigo || acompanhando.has(codigo)) return;

    acompanhando.add(codigo);
    const limite = expira_em ? new Date(expira_em).getTime() : Date.now() + 60 * 60 * 1000;

    const intervalo = window.setInterval(async () => {
        const parar = () => {
            window.clearInterval(intervalo);
            acompanhando.delete(codigo);
        };

        if (Date.now() > limite) {
            parar();
            return;
        }

        try {
            const { data } = await api.get(`/area-cliente/depositos/${codigo}`);
            const situacao = data.data.situacao;

            if (situacao === 'Aprovado') enviar_compra(codigo, valor);
            if (!['Pendente', 'Em análise'].includes(situacao)) parar();
        } catch {
            // sem conexão: tenta de novo na próxima volta
        }
    }, INTERVALO_COMPRA);
};
