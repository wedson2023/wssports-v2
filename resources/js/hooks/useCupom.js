import { createContext, useCallback, useContext, useEffect, useMemo, useReducer, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';

import api from '../utils/api';
import { alerta_erro, confirmar } from '../utils/alerts';
import { calcular_premio, centavos_para_texto, formatar_real, para_centavos } from '../utils/money';
import { chave_cupom, gravar_json, ler_json } from '../utils/storage';

// cupom do visitante (R-16, data-model.md seção 2): estado imutável guardado no aparelho

export const CupomContext = createContext(null);

const versao_formato = 1;
const maximo_palpites = 50;
const maximo_nome = 100;

const cupom_vazio = { versao_formato, nome: '', valor_centavos: 0, palpites: [] };

const mesmo_jogo = (palpite, tipo, confronto_id) => palpite.tipo === tipo && palpite.confronto_id === confronto_id;

const palpite_valido = (palpite) =>
    palpite !== null && typeof palpite === 'object'
    && ['pre_jogo', 'ao_vivo'].includes(palpite.tipo)
    && Number.isInteger(palpite.confronto_id)
    && typeof palpite.codigo_cotacao === 'string'
    && typeof palpite.cotacao === 'string';

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
        // um palpite por jogo: adiciona, troca de cotação ou remove a mesma (FR-034)
        case 'alternar_palpite': {
            const { palpite } = acao;
            const atual = cupom.palpites.find((item) => mesmo_jogo(item, palpite.tipo, palpite.confronto_id));

            if (atual) {
                const mesma_cotacao = atual.codigo_cotacao === palpite.codigo_cotacao && atual.jogador_id === palpite.jogador_id;

                return {
                    ...cupom,
                    palpites: mesma_cotacao
                        ? cupom.palpites.filter((item) => item !== atual)
                        : cupom.palpites.map((item) => (item === atual ? palpite : item)),
                };
            }

            if (cupom.palpites.length >= maximo_palpites) return cupom;

            return { ...cupom, palpites: [...cupom.palpites, palpite] };
        }
        case 'remover_palpite':
            return { ...cupom, palpites: cupom.palpites.filter((item) => !mesmo_jogo(item, acao.tipo_jogo, acao.confronto_id)) };
        case 'definir_valor':
            return { ...cupom, valor_centavos: Math.max(0, Math.trunc(acao.valor_centavos)) };
        case 'definir_nome':
            return { ...cupom, nome: acao.nome.slice(0, maximo_nome) };
        // cotações atuais devolvidas pelo backend no 409 (cotação alterada)
        case 'atualizar_cotacoes':
            return {
                ...cupom,
                palpites: cupom.palpites.map((item, indice) => {
                    const alteracao = acao.alteracoes.find((alterada) => alterada.indice === indice);

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

// corpo do POST /api/publico/apostas (data-model.md seção 3)
const montar_envio = (cupom, nome) => ({
    chave_idempotencia: crypto.randomUUID(),
    nome,
    valor: centavos_para_texto(cupom.valor_centavos),
    aceitar_alteracoes: 'Nenhuma',
    palpites: cupom.palpites.map((palpite) => ({
        [palpite.tipo === 'ao_vivo' ? 'confrontos_ao_vivo_id' : 'confrontos_id']: palpite.confronto_id,
        codigo_cotacao: palpite.codigo_cotacao,
        ...(palpite.codigo_cotacao === 'jogador' ? { confrontos_jogadores_id: palpite.jogador_id } : {}),
        cotacao_vista: palpite.cotacao,
    })),
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

    // o cupom salvo é restaurado sem alterações (FR-039) e gravado a cada mudança
    useEffect(() => gravar_json(chave_cupom, cupom), [cupom]);

    // cupom esvaziado (palpites removidos ou "Limpar"): sai da validação do código
    useEffect(() => {
        if (!cupom.palpites.length) definir_codigo_validacao(null);
    }, [cupom.palpites.length]);

    const acoes = useMemo(() => ({
        alternar_palpite: (palpite) => despachar({ tipo: 'alternar_palpite', palpite }),
        remover_palpite: (tipo_jogo, confronto_id) => despachar({ tipo: 'remover_palpite', tipo_jogo, confronto_id }),
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

            for (;;) {
                try {
                    const { status, data } = await api.post(destino, montar_envio(envio, nome));
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

                    const novo_total = formatar_real(para_centavos(total_a_pagar));

                    if (!(await confirmar(`${message} Novo prêmio: R$ ${novo_total}.`, cor_confirmar))) return null;
                }
            }
        } finally {
            enviando_agora.current = false;
            definir_enviando(false);
        }
    }, [acoes, codigo_validacao]);

    return useMemo(
        () => ({ cupom, enviando, codigo_validacao, ...acoes, enviar_cupom }),
        [cupom, enviando, codigo_validacao, acoes, enviar_cupom],
    );
};

// cupom para os componentes: estado, ações e totais estimados (FR-037, FR-037a)
export default function useCupom() {
    const contexto = useContext(CupomContext);
    const { configuracoes } = usePage().props;

    const totais = useMemo(() => calcular_premio({
        valor_centavos: contexto.cupom.valor_centavos,
        cotacoes: contexto.cupom.palpites.map(({ cotacao }) => cotacao),
        multiplicador: configuracoes?.multiplicador ?? 0,
        premio_maximo: configuracoes?.premio_maximo ?? '0.00',
        ganho_multiplo_palpites: configuracoes?.ganho_multiplo_palpites ?? '0.00',
    }), [contexto.cupom, configuracoes]);

    // "vendedor paga": mesmo valor do prêmio até existir a configuração vendedor_paga (FR-037a)
    return { ...contexto, ...totais, vendedor_paga_centavos: totais.total_centavos };
}
