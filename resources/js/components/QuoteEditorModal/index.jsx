import { useCallback, useEffect, useState } from 'react';
import Swal from 'sweetalert2';

import Backdrop from '../Backdrop';
import ModalHeader from '../ModalHeader';
import QuoteGrid from '../QuoteGrid';
import QuotePreview from '../QuotePreview';
import SaveBar from '../SaveBar';
import Spinner from '../Spinner';
import TargetPicker from '../TargetPicker';
import useGradeCotacoes from '../../hooks/useGradeCotacoes';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import { Body, Columns, Container, Loading, Note, Top } from './styles';

// aviso curto no canto, sem bloquear a tela
const avisar = (icone, texto) => Swal.fire({ toast: true, position: 'top-end', icon: icone, title: texto, showConfirmButton: false, timer: 2500 });

// resumo das regras para o selo da lista, a partir da resposta da API
const resumir = (regras) => {
    const com_valores = regras.filter((regra) => Object.keys(regra.valores ?? {}).length);
    const valores = com_valores.flatMap((regra) => Object.values(regra.valores).map(Number));

    return com_valores.length
        ? { quantidade: com_valores.length, alvos: [...new Set(com_valores.map((regra) => regra.alvo))], minimo: Math.min(...valores), maximo: Math.max(...valores) }
        : null;
};

// erros 422 da gravação ("valores.odd1" / "cotacoes.odd1") nos campos da grade
const erros_dos_campos = (erro) => Object.fromEntries(
    Object.entries(erro.response?.data?.errors ?? {})
        .filter(([campo]) => /^(valores|cotacoes)\./.test(campo))
        .map(([campo, mensagens]) => [campo.split('.')[1], mensagens[0]]),
);

// editor de um campeonato ou jogo em modal (spec 016, US2/US3): alvo, grade por mercado, prévia e
// Salvar; campeonato em porcentagem (pré-jogo e ao vivo) com cadeado no alvo Vendedores; jogo em
// cotação desejada (só pré-jogo). Altura fixa desde a abertura (constituição, tamanho dos modais)
export default function QuoteEditorModal({ camada, item, logado, nomes, token, cor, ao_fechar, ao_salvo }) {
    const aberto = Boolean(item);
    const e_jogo = camada === 'confrontos';
    const gestor_geral = ['Admin', 'Supervisor'].includes(logado.funcao);
    const grade = useGradeCotacoes(e_jogo ? 'jogo' : 'porcentagem');
    const [alvo, definir_alvo] = useState(null);
    const [regras, definir_regras] = useState(null);
    const [provedor, definir_provedor] = useState({});
    const [cadeados, definir_cadeados] = useState([]);
    const [foco, definir_foco] = useState('odd1');
    const [salvando, definir_salvando] = useState(false);
    const [recarga, definir_recarga] = useState(0);
    const cabecalhos = { Authorization: `Bearer ${token}` };
    const rota = e_jogo ? `/porcentagens-confrontos/${item?.id}` : `/porcentagens-campeonatos/${item?.id}`;

    const fechar = useCallback(async () => {
        if (grade.quantidade && !(await confirmar(`Você tem ${grade.quantidade} alterações não salvas. Fechar mesmo assim?`, cor))) return;

        ao_fechar();
    }, [grade.quantidade, ao_fechar, cor]);

    useVoltarFecha(aberto, fechar);

    // regras do item ao abrir; alvo inicial: Clientes (Admin e Supervisor) ou os vendedores de quem edita
    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        definir_regras(null);
        definir_alvo(gestor_geral ? { alvo: 'Clientes', usuarios_id: null, nome: null } : { alvo: 'Vendedores', usuarios_id: logado.id, nome: logado.nome });

        api.get(rota, { headers: cabecalhos })
            .then(({ data }) => {
                if (!ativo) return;
                definir_regras(data.regras);
                definir_provedor(data.cotacoes_provedor ?? {});
            })
            .catch((erro) => {
                if (ativo) alerta_erro(erro);
            });

        return () => {
            ativo = false;
        };
    }, [aberto, item?.id]);

    // grade do alvo escolhido (valor do jogo: cotação final antes das porcentagens do público)
    useEffect(() => {
        if (!regras || !alvo) return;

        const regra = regras.find((atual) => atual.alvo === alvo.alvo && (atual.usuarios_id ?? null) === (alvo.usuarios_id ?? null));

        grade.carregar(e_jogo ? (regra?.cotacoes ?? {}) : (regra?.valores ?? {}));
    }, [regras, alvo?.alvo, alvo?.usuarios_id]);

    // cadeados do campeonato para o alvo Vendedores de um usuário
    useEffect(() => {
        if (e_jogo || !aberto || alvo?.alvo !== 'Vendedores') {
            definir_cadeados([]);
            return undefined;
        }

        let ativo = true;

        api.get(`/cotacoes/usuarios/${alvo.usuarios_id}`, { params: { campeonatos_id: item.id }, headers: cabecalhos })
            .then(({ data }) => ativo && definir_cadeados(data.cadeados_campeonato))
            .catch(() => ativo && definir_cadeados([]));

        return () => {
            ativo = false;
        };
    }, [aberto, alvo?.alvo, alvo?.usuarios_id, item?.id]);

    // cadeados por código: o de um superior (não destrancável) prevalece
    const mapa_cadeados = {};

    cadeados.forEach((cadeado) => {
        if (!mapa_cadeados[cadeado.codigo] || !cadeado.pode_destrancar) mapa_cadeados[cadeado.codigo] = cadeado;
    });

    useEffect(() => {
        grade.definir_trancados(Object.fromEntries(Object.entries(mapa_cadeados).filter(([, cadeado]) => !cadeado.pode_destrancar)));
    }, [cadeados]);

    const pode_trancar = !e_jogo && gestor_geral && alvo?.alvo === 'Vendedores' && alvo.usuarios_id !== logado.id;

    const alternar_cadeado = async (codigo, trancar) => {
        const cadeado = mapa_cadeados[codigo];

        try {
            const { data } = await api({
                method: trancar ? 'post' : 'delete',
                url: '/cotacoes/cadeados',
                data: { camada: 'campeonatos', usuarios_id: cadeado?.no_usuario?.id ?? alvo.usuarios_id, campeonatos_id: item.id, codigos: [codigo] },
                headers: cabecalhos,
            });
            const resposta = await api.get(`/cotacoes/usuarios/${alvo.usuarios_id}`, { params: { campeonatos_id: item.id }, headers: cabecalhos });

            definir_cadeados(resposta.data.cadeados_campeonato);
            avisar('success', trancar ? `${nomes[codigo]} trancada.` : (data.nao_destrancados ? 'Esse cadeado é de um superior.' : `${nomes[codigo]} destrancada.`));
        } catch (erro) {
            alerta_erro(erro);
        }
    };

    const salvar = async () => {
        const alteracoes = grade.alteracoes_para_salvar();

        if (!alteracoes) {
            avisar('error', 'Corrija os campos marcados antes de salvar.');
            return;
        }

        if (alvo.alvo === 'Todos' && !(await confirmar('Esta regra vai valer para visitantes, clientes e todos os vendedores. Continuar?', cor))) return;

        definir_salvando(true);

        try {
            const corpo = e_jogo
                // campo vazio no jogo = voltar à cotação do provedor (remove o valor do jogo)
                ? { alvo: alvo.alvo, usuarios_id: alvo.usuarios_id, cotacoes: Object.fromEntries(Object.entries(alteracoes).map(([codigo, valor]) => [codigo, valor || provedor[codigo]])) }
                : { alvo: alvo.alvo, usuarios_id: alvo.usuarios_id, valores: alteracoes };
            const { data } = await api.patch(rota, corpo, { headers: cabecalhos });

            definir_regras(data.regras);
            definir_recarga((atual) => atual + 1);
            ao_salvo?.(item.id, resumir(data.regras));
            avisar('success', data.pulados ? `Salvo. ${data.pulados} cotações trancadas foram puladas.` : 'Cotações salvas.');
        } catch (erro) {
            if (erro.response?.status === 422 && Object.keys(erros_dos_campos(erro)).length) {
                grade.definir_erros(erros_dos_campos(erro));
                avisar('error', 'Algumas cotações não foram aceitas.');
            } else {
                alerta_erro(erro);
            }
        } finally {
            definir_salvando(false);
        }
    };

    // prévia: público do alvo, com a regra em edição fora das parcelas
    const parametros = alvo && item ? {
        publico: alvo.alvo === 'Vendedores' ? 'usuario' : 'clientes',
        publico_id: alvo.alvo === 'Vendedores' ? alvo.usuarios_id : undefined,
        tipo: 'pre_jogo',
        camada,
        alvo: alvo.alvo,
        alvo_usuarios_id: alvo.usuarios_id ?? undefined,
        confrontos_id: e_jogo ? item.id : undefined,
        campeonatos_id: e_jogo ? undefined : item.id,
    } : null;

    return (
        <>
            <Backdrop visivel={aberto} ao_fechar={fechar} camada={45} />
            <Container $visivel={aberto} role="dialog" aria-label={item ? `Cotações de ${item.nome}` : 'Cotações'}>
                {aberto && (
                    <>
                        <ModalHeader
                            icone={e_jogo ? 'sports_soccer' : 'emoji_events'}
                            titulo={`Cotações · ${item.nome}`}
                            descricao={e_jogo
                                ? 'Informe a cotação desejada para o público escolhido. Vale só para o pré-jogo.'
                                : 'Ajuste as porcentagens deste campeonato para o público escolhido. Vale para pré-jogo e ao vivo.'}
                            ao_fechar={fechar}
                        />
                        <Top>
                            <TargetPicker
                                alvo={alvo}
                                gestor_geral={gestor_geral}
                                token={token}
                                desabilitado={grade.quantidade > 0}
                                ao_escolher={definir_alvo}
                            />
                            {grade.quantidade > 0 && <Note>Salve ou desfaça as alterações para trocar o público.</Note>}
                        </Top>
                        <Body>
                            {!regras || !nomes ? (
                                <Loading><Spinner tamanho={32} cor="currentColor" /></Loading>
                            ) : (
                                <Columns>
                                    <div>
                                        <QuoteGrid
                                            nomes={nomes}
                                            grade={grade}
                                            modo={e_jogo ? 'jogo' : 'porcentagem'}
                                            provedor={e_jogo ? provedor : null}
                                            cadeados={mapa_cadeados}
                                            pode_trancar={pode_trancar}
                                            ao_alternar_cadeado={alternar_cadeado}
                                            ao_focar={definir_foco}
                                            cor={cor}
                                        />
                                    </div>
                                    <QuotePreview
                                        parametros={parametros}
                                        codigo={foco}
                                        nome={nomes[foco]}
                                        camada={e_jogo ? 'jogo' : 'campeonato'}
                                        valor={grade.valor_atual(foco)}
                                        pendente={foco in grade.pendentes}
                                        rotulo_superiores="Superiores"
                                        token={token}
                                        recarga={recarga}
                                        acima_da_barra={grade.quantidade > 0}
                                    />
                                </Columns>
                            )}
                        </Body>
                        <SaveBar quantidade={grade.quantidade} salvando={salvando} ao_desfazer={grade.desfazer} ao_salvar={salvar} />
                    </>
                )}
            </Container>
        </>
    );
}
