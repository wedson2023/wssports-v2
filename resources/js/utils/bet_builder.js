import { cotacao_bloco } from './money';

// Criar Aposta no cupom (spec 008): palpites do mesmo jogo do pré-jogo formam um bloco, que conta
// como um jogo. Grupos de mercado e pares redundantes vêm do backend (props
// configuracoes.criar_aposta), a fonte única da regra

const mensagem_mesmo_mercado = 'Não é possível combinar dois palpites do mesmo mercado no Criar Aposta.';

// mesmo palpite do cupom: jogo, mercado, jogador e opção especial
export const mesmo_palpite = (palpite, outro) => palpite.tipo === outro.tipo
    && palpite.confronto_id === outro.confronto_id
    && palpite.codigo_cotacao === outro.codigo_cotacao
    && palpite.jogador_id === outro.jogador_id
    && palpite.opcao_id === outro.opcao_id;

// pernas da aposta na ordem do cupom: palpites do mesmo jogo do pré-jogo juntos (bloco); ao vivo e
// especial, um por perna
export const agrupar_pernas = (palpites) => {
    const pernas = [];
    const por_jogo = new Map();

    palpites.forEach((palpite) => {
        const existente = palpite.tipo === 'pre_jogo' ? por_jogo.get(palpite.confronto_id) : null;

        if (existente) {
            existente.palpites.push(palpite);
            return;
        }

        const perna = { tipo: palpite.tipo, confronto_id: palpite.confronto_id, palpites: [palpite] };

        if (palpite.tipo === 'pre_jogo') por_jogo.set(palpite.confronto_id, perna);
        pernas.push(perna);
    });

    return pernas.map((perna) => ({ ...perna, bloco: perna.palpites.length > 1 }));
};

// cotação de cada perna (texto com 2 casas): o bloco com o desconto do público
export const cotacao_da_perna = (perna, desconto) => (perna.bloco
    ? cotacao_bloco(perna.palpites.map(({ cotacao }) => cotacao), desconto ?? '0.00')
    : Number(perna.palpites[0].cotacao).toFixed(2));

// grupo de mercado do palpite (MercadosCotacao::grupo): jogador pelo nome; código sem grupo é um
// grupo só dele
export const grupo_do_palpite = (palpite, grupos) => (palpite.codigo_cotacao === 'jogador'
    ? `jogador:${(palpite.jogador_nome ?? '').trim().toLowerCase()}`
    : grupos?.[palpite.codigo_cotacao] ?? palpite.codigo_cotacao);

// no comprovante: palpites agrupados por perna, na ordem do bilhete; os do mesmo bloco Criar Aposta
// ficam juntos, com o bloco ({ bloco, palpites }); o palpite simples vem com bloco null
export const agrupar_palpites_comprovante = (comprovante) => {
    const grupos = [];
    const por_bloco = new Map();

    comprovante.palpites.forEach((palpite) => {
        const bloco = palpite.apostas_blocos_id
            ? (comprovante.blocos ?? []).find(({ id }) => id === palpite.apostas_blocos_id) ?? null
            : null;

        if (!bloco) {
            grupos.push({ bloco: null, palpites: [palpite] });
            return;
        }

        if (por_bloco.has(bloco.id)) {
            por_bloco.get(bloco.id).palpites.push(palpite);
            return;
        }

        const grupo = { bloco, palpites: [palpite] };
        por_bloco.set(bloco.id, grupo);
        grupos.push(grupo);
    });

    return grupos;
};

// no comprovante: o bloco Criar Aposta cuja linha vem depois deste palpite (o último do bloco), ou
// null; a linha mostra a cotação do bloco
export const bloco_apos_palpite = (comprovante, palpite) => {
    if (!palpite.apostas_blocos_id) return null;

    const do_bloco = comprovante.palpites.filter((item) => item.apostas_blocos_id === palpite.apostas_blocos_id);

    return do_bloco[do_bloco.length - 1] === palpite
        ? (comprovante.blocos ?? []).find(({ id }) => id === palpite.apostas_blocos_id) ?? null
        : null;
};

const redundantes = (grupo, outro, pares) => (pares ?? [])
    .some(([primeiro, segundo]) => (grupo === primeiro && outro === segundo) || (grupo === segundo && outro === primeiro));

// o que fazer ao escolher uma cotação (research R-12): mesmo palpite → remover; mesmo mercado do
// jogo → trocar; outro mercado → somar ao bloco (se o Criar Aposta vale para o jogo); senão trocar
// como antes. Devolve { acao, alvo } ou { motivo } quando a escolha não pode entrar no bloco
export const decidir_escolha = (palpites, palpite, regras) => {
    const do_jogo = palpites.filter((item) => item.tipo === palpite.tipo && item.confronto_id === palpite.confronto_id);

    if (!do_jogo.length) return { acao: 'somar' };

    const igual = do_jogo.find((item) => mesmo_palpite(item, palpite));

    if (igual) return { acao: 'remover', alvo: igual };

    if (palpite.tipo !== 'pre_jogo' || !regras?.habilitado) return { acao: 'trocar', alvo: do_jogo[0] };

    const grupo = grupo_do_palpite(palpite, regras.grupos);
    const mesmo_grupo = do_jogo.find((item) => grupo_do_palpite(item, regras.grupos) === grupo);

    if (mesmo_grupo) return { acao: 'trocar', alvo: mesmo_grupo };

    if (do_jogo.length >= regras.quantidade_maxima) {
        return { motivo: `O Criar Aposta aceita no máximo ${regras.quantidade_maxima} palpites.` };
    }

    if (do_jogo.some((item) => redundantes(grupo, grupo_do_palpite(item, regras.grupos), regras.pares_redundantes))) {
        return { motivo: mensagem_mesmo_mercado };
    }

    return { acao: 'somar' };
};
