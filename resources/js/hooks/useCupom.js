import { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';

import api from '../utils/api';
import { alerta_atencao, alerta_erro, confirmar } from '../utils/alerts';
import { agrupar_pernas, cotacao_da_perna, decidir_escolha, mesmo_palpite } from '../utils/bet_builder';
import { calcular_premio, centavos_para_texto, formatar_real, para_centavos } from '../utils/money';
import { chave_cupom, gravar_json, ler_json } from '../utils/storage';

// cupom do visitante (R-16, data-model.md seção 2): estado imutável guardado no aparelho

export const CupomContext = createContext(null);

const versao_formato = 1;
const maximo_palpites = 50;
const maximo_nome = 100;

const cupom_vazio = { versao_formato, nome: '', valor_centavos: 0, palpites: [] };

const palpite_valido = (palpite) =>
    palpite !== null && typeof palpite === 'object'
    && ['pre_jogo', 'ao_vivo', 'especial'].includes(palpite.tipo)
    && Number.isInteger(palpite.confronto_id)
    && typeof palpite.codigo_cotacao === 'string'
    && typeof palpite.cotacao === 'string'
    && (palpite.tipo !== 'especial' || Number.isInteger(palpite.opcao_id));

// cupom salvo inválido (formato antigo, corrompido) → cupom vazio, sem erro (FR-039)
const ler_cupom_salvo = () => {
    const salvo = ler_json(chave_cupom);

    const valido = salvo !== null && typeof salvo === 'object'
        && salvo.versao_formato === versao_formato
        && typeof salvo.nome === 'string'
        && Number.isInteger(salvo.valor_centavos) && salvo.valor_centavos >= 0
        && Array.isArray(salvo.palpites) && salvo.palpites.every(palpite_valido);

    return valido ? salvo : cupom_vazio;
};

export const cupom_reducer = (cupom, acao) => {
    switch (acao.tipo) {
        // escolha já decidida pelas regras do Criar Aposta (decidir_escolha): remove a mesma cotação,
        // troca a do mesmo jogo (ou do mesmo mercado no bloco) ou soma (FR-034; spec 008). No
        // especial, confronto_id é a categoria: um palpite por categoria (spec 006)
        case 'aplicar_escolha': {
            const { palpite, decisao } = acao;

            if (decisao.acao === 'remover') return { ...cupom, palpites: cupom.palpites.filter((item) => !mesmo_palpite(item, decisao.alvo)) };

            if (decisao.acao === 'trocar') return { ...cupom, palpites: cupom.palpites.map((item) => (mesmo_palpite(item, decisao.alvo) ? palpite : item)) };

            if (cupom.palpites.length >= maximo_palpites) return cupom;

            return { ...cupom, palpites: [...cupom.palpites, palpite] };
        }
        // um palpite (no bloco Criar Aposta, o jogo continua com os demais)
        case 'remover_palpite':
            return { ...cupom, palpites: cupom.palpites.filter((item) => !mesmo_palpite(item, acao.palpite)) };
        case 'definir_valor':
            return { ...cupom, valor_centavos: Math.max(0, Math.trunc(acao.valor_centavos)) };
        case 'definir_nome':
            return { ...cupom, nome: acao.nome.slice(0, maximo_nome) };
        // cotações atuais devolvidas pelo backend no 409 (cotação alterada); a do bloco Criar Aposta
        // não fica no cupom (é sempre calculada a partir dos palpites)
        case 'atualizar_cotacoes':
            return {
                ...cupom,
                palpites: cupom.palpites.map((item, indice) => {
                    const alteracao = acao.alteracoes.find((alterada) => !alterada.bloco && alterada.indice === indice);

                    return alteracao ? { ...item, cotacao: alteracao.cotacao_atual } : item;
                }),
            };
        // código do visitante aberto pelo vendedor: o cupom passa a ser o da simulação
        case 'carregar':
            return { ...cupom_vazio, nome: acao.nome.slice(0, maximo_nome), valor_centavos: acao.valor_centavos, palpites: acao.palpites.slice(0, maximo_palpites) };
        case 'limpar':
            return cupom_vazio;
        default:
            return cupom;
    }
};

// cotação vista de cada bloco Criar Aposta (spec 008): a calculada no cupom ou, depois de um 409, a
// devolvida pelo backend
const montar_blocos = (palpites, desconto, cotacoes_blocos) => agrupar_pernas(palpites)
    .filter(({ bloco }) => bloco)
    .map((perna) => ({
        confrontos_id: perna.confronto_id,
        cotacao_vista: cotacoes_blocos[perna.confronto_id] ?? cotacao_da_perna(perna, desconto),
    }));

// corpo do POST /api/publico/apostas (data-model.md seção 3)
const montar_envio = (cupom, nome, desconto, cotacoes_blocos = {}) => ({
    chave_idempotencia: crypto.randomUUID(),
    nome,
    valor: centavos_para_texto(cupom.valor_centavos),
    aceitar_alteracoes: 'Nenhuma',
    palpites: cupom.palpites.map((palpite) => (palpite.tipo === 'especial'
        // palpite especial: só a opção escolhida, sem confronto
        ? { especiais_opcoes_id: palpite.opcao_id, codigo_cotacao: 'especial', cotacao_vista: palpite.cotacao }
        : {
            [palpite.tipo === 'ao_vivo' ? 'confrontos_ao_vivo_id' : 'confrontos_id']: palpite.confronto_id,
            codigo_cotacao: palpite.codigo_cotacao,
            ...(palpite.codigo_cotacao === 'jogador' ? { confrontos_jogadores_id: palpite.jogador_id } : {}),
            cotacao_vista: palpite.cotacao,
        })),
    blocos: montar_blocos(cupom.palpites, desconto, cotacoes_blocos),
});

// intervalo entre as consultas da aposta "Em análise" (delay do ao vivo)
const intervalo_analise_ms = 2000;

const espera = (ms) => new Promise((resolver) => { setTimeout(resolver, ms); });

// aposta do cliente ou do vendedor no ao vivo volta "Em análise": consulta a situação (na rota de
// quem apostou) até o backend aceitar ou recusar; devolve o comprovante (Ativa) ou null (Recusada,
// Expirada ou sem resposta)
const aguardar_analise = async (rota, codigo, segundos_restantes) => {
    const limite = Date.now() + (Math.max(segundos_restantes, 0) + 30) * 1000;

    while (Date.now() < limite) {
        await espera(intervalo_analise_ms);

        const { data } = await api.get(`${rota}/${codigo}/situacao`);
        const comprovante = data.data;

        if (comprovante.situacao === 'Ativa') return comprovante;

        if (comprovante.situacao !== 'Em análise') {
            alerta_erro({ response: { status: 422, data: { message: comprovante.motivo_recusa ?? `Aposta ${comprovante.situacao.toLowerCase()}.` } } });
            return null;
        }
    }

    alerta_erro({ response: { status: 422, data: { message: 'A confirmação da aposta demorou mais que o esperado. Confira pelo código do bilhete.' } } });
    return null;
};

// estado do provedor (usado pelo PublicLayout)
export const useEstadoCupom = () => {
    const [cupom, despachar] = useReducer(cupom_reducer, undefined, ler_cupom_salvo);
    const [enviando, definir_enviando] = useState(false);
    // código pendente do visitante que o vendedor está validando (o "Finalizar" vira "Validar")
    const [codigo_validacao, definir_codigo_validacao] = useState(null);
    const enviando_agora = useRef(false);
    // desconto do Criar Aposta do público, para a cotação vista dos blocos no envio (spec 008)
    const desconto = usePage().props.configuracoes?.criar_aposta?.desconto ?? '0.00';

    // o cupom salvo é restaurado sem alterações (FR-039) e gravado a cada mudança
    useEffect(() => gravar_json(chave_cupom, cupom), [cupom]);

    // cupom esvaziado (palpites removidos ou "Limpar"): sai da validação do código
    useEffect(() => {
        if (!cupom.palpites.length) definir_codigo_validacao(null);
    }, [cupom.palpites.length]);

    const acoes = useMemo(() => ({
        aplicar_escolha: (palpite, decisao) => despachar({ tipo: 'aplicar_escolha', palpite, decisao }),
        remover_palpite: (palpite) => despachar({ tipo: 'remover_palpite', palpite }),
        definir_valor: (valor_centavos) => despachar({ tipo: 'definir_valor', valor_centavos }),
        definir_nome: (nome) => despachar({ tipo: 'definir_nome', nome }),
        atualizar_cotacoes: (alteracoes) => despachar({ tipo: 'atualizar_cotacoes', alteracoes }),
        limpar: () => despachar({ tipo: 'limpar' }),
        // simulação do código pendente (vendedor): carrega nome, valor e palpites e liga o "Validar"
        carregar_validacao: (codigo, { nome, valor_centavos, palpites }) => {
            despachar({ tipo: 'carregar', nome, valor_centavos, palpites });
            definir_codigo_validacao(codigo);
        },
    }), []);

    // envia o cupom e devolve o comprovante (ou null). "enviando" fica ligado do início ao fim,
    // inclusive na confirmação do 409, e pausa a checagem de versão e o ao vivo (FR-050a, R-15)
    // sessao: cliente logado aposta com o próprio saldo (area-cliente); vendedor logado aposta pelo
    // painel (Ativa, como no sistema antigo); os demais geram o código do visitante. ao_expirar:
    // avisa o provedor da sessão quando o token foi recusado
    const enviar_cupom = useCallback(async (cupom_atual, cor_confirmar, sessao = null, ao_expirar = null) => {
        if (enviando_agora.current) return null;

        enviando_agora.current = true;
        definir_enviando(true);

        try {
            let envio = cupom_atual;
            const e_cliente = sessao?.tipo === 'cliente';
            const e_vendedor = sessao?.tipo === 'usuario' && sessao.apostador === 'vendedor';
            let rota = '/publico/apostas';
            if (e_cliente) rota = '/area-cliente/apostas';
            if (e_vendedor) rota = '/apostas';
            // vendedor validando o código do visitante: valida em vez de criar outra aposta
            const validando = e_vendedor && codigo_validacao !== null;
            const destino = validando ? `/apostas/pendentes/${encodeURIComponent(codigo_validacao)}/validar` : rota;
            const nome = (e_cliente ? sessao.nome || envio.nome : envio.nome) ?? '';
            // cotações dos blocos Criar Aposta devolvidas no 409, usadas na confirmação
            const cotacoes_blocos = {};

            for (;;) {
                try {
                    const { status, data } = await api.post(destino, montar_envio(envio, nome, desconto, cotacoes_blocos));
                    const comprovante = status === 202 ? await aguardar_analise(rota, data.data.codigo, data.data.segundos_restantes) : data.data;

                    if (comprovante) acoes.limpar();

                    return comprovante && { ...comprovante, validado: validando };
                } catch (erro) {
                    const resposta = erro.response;

                    // token vencido ou recusado: encerra a sessão e pede para entrar de novo
                    if (resposta?.status === 401 && (e_cliente || e_vendedor)) {
                        ao_expirar?.();
                        alerta_erro({ response: { status: 401, data: { message: 'Sua sessão expirou. Entre novamente para apostar.' } } });
                        return null;
                    }

                    if (resposta?.status !== 409) {
                        alerta_erro(erro);
                        return null;
                    }

                    // cotação alterada: confirma o novo prêmio; o cupom fica com as cotações novas
                    const { message, alteracoes, total_a_pagar } = resposta.data;
                    acoes.atualizar_cotacoes(alteracoes);
                    envio = cupom_reducer(envio, { tipo: 'atualizar_cotacoes', alteracoes });
                    alteracoes.filter(({ bloco }) => bloco).forEach(({ confrontos_id, cotacao_atual }) => {
                        cotacoes_blocos[confrontos_id] = cotacao_atual;
                    });

                    const novo_total = formatar_real(para_centavos(total_a_pagar));

                    if (!(await confirmar(`${message} Novo prêmio: R$ ${novo_total}.`, cor_confirmar))) return null;
                }
            }
        } finally {
            enviando_agora.current = false;
            definir_enviando(false);
        }
    }, [acoes, codigo_validacao, desconto]);

    return useMemo(
        () => ({ cupom, enviando, codigo_validacao, ...acoes, enviar_cupom }),
        [cupom, enviando, codigo_validacao, acoes, enviar_cupom],
    );
};

// cupom para os componentes: estado, ações e totais estimados (FR-037, FR-037a). Os totais usam as
// pernas da aposta: o bloco Criar Aposta é uma cotação só, com o desconto (spec 008)
export default function useCupom() {
    const contexto = useContext(CupomContext);
    const { configuracoes } = usePage().props;
    const criar_aposta = configuracoes?.criar_aposta;
    const { cupom, aplicar_escolha } = contexto;

    const pernas = useMemo(() => agrupar_pernas(cupom.palpites), [cupom.palpites]);

    const totais = useMemo(() => calcular_premio({
        valor_centavos: cupom.valor_centavos,
        cotacoes: pernas.map((perna) => cotacao_da_perna(perna, criar_aposta?.desconto)),
        multiplicador: configuracoes?.multiplicador ?? 0,
        premio_maximo: configuracoes?.premio_maximo ?? '0.00',
        ganho_multiplo_palpites: configuracoes?.ganho_multiplo_palpites ?? '0.00',
    }), [cupom.valor_centavos, pernas, criar_aposta, configuracoes]);

    // escolher uma cotação: remove, troca, soma ao bloco ou avisa por que não pode (R-12)
    const alternar_palpite = useCallback((palpite) => {
        const decisao = decidir_escolha(cupom.palpites, palpite, criar_aposta);

        if (decisao.motivo) {
            alerta_atencao(decisao.motivo);
            return;
        }

        aplicar_escolha(palpite, decisao);
    }, [cupom.palpites, criar_aposta, aplicar_escolha]);

    // "vendedor paga": mesmo valor do prêmio até existir a configuração vendedor_paga (FR-037a)
    return {
        ...contexto,
        ...totais,
        pernas,
        desconto_criar_aposta: criar_aposta?.desconto ?? '0.00',
        alternar_palpite,
        vendedor_paga_centavos: totais.total_centavos,
    };
}
