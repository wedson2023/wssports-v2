import { useEffect, useRef, useState } from 'react';

import CashModal, { Button, Error, Field, Fields, GhostButton, Input, Label, Warning } from '../CashModal';
import DecimalField from '../DecimalField';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { erros_por_campo } from '../../utils/forms';
import {
    AddButton, Badge, Group, OptionCell, OptionRow, OptionsHead, RemoveButton, Select, Title,
} from './styles';

// a API devolve a data limite com -03:00: os 16 primeiros caracteres já são o valor do campo
// datetime-local no horário de Brasília
const para_campo = (texto) => (texto ? texto.slice(0, 16) : '');

// a API lê a data sem fuso no horário de Brasília (spec 006)
const para_api = (texto) => texto.replace('T', ' ');

const opcao_vazia = (chave) => ({ chave, id: null, nome: '', cotacao: null, ativo: true, anulada: false });

// campos de uma opção existente que mudaram, para o PUT só levar o alterado; a criada nesta tela
// (sem original carregado) leva todos
const alteracoes = (opcao, original = {}) => Object.fromEntries(
    [['nome', opcao.nome.trim()], ['cotacao', opcao.cotacao], ['ativo', opcao.ativo]]
        .filter(([campo, valor]) => String(valor) !== String(original[campo])),
);

// cadastro e edição de um especial com as opções em grade (spec 024, FR-008 a FR-011,
// contracts/paginas §3.1). Na edição, salva a categoria e depois cada opção nova, alterada ou
// removida pelas rotas de opções (R-12), parando no primeiro erro com o aviso do que já foi salvo.
// especial = {} para cadastrar; com id, o especial carregado com as opções
export default function SpecialFormModal({ especial, token, ao_fechar, ao_salvar }) {
    const aberto = Boolean(especial);
    const edicao = Boolean(especial?.id);
    const [dados, definir_dados] = useState({ nome: '', data_limite: '', quantidade_vencedoras: '1', ativo: true });
    const [opcoes, definir_opcoes] = useState([]);
    const [removidas, definir_removidas] = useState([]);
    const [erros, definir_erros] = useState({});
    const [erros_opcoes, definir_erros_opcoes] = useState({});
    const [salvo_em_parte, definir_salvo_em_parte] = useState(false);
    const [salvando, definir_salvando] = useState(false);
    const proxima_chave = useRef(0);

    const cabecalhos = { headers: { Authorization: `Bearer ${token}` } };
    const nova_chave = () => {
        proxima_chave.current += 1;

        return `nova_${proxima_chave.current}`;
    };

    useEffect(() => {
        if (!aberto) return;

        definir_erros({});
        definir_erros_opcoes({});
        definir_removidas([]);
        definir_salvo_em_parte(false);
        definir_dados({
            nome: especial.nome ?? '',
            data_limite: para_campo(especial.data_limite),
            quantidade_vencedoras: String(especial.quantidade_vencedoras ?? 1),
            ativo: especial.ativo ?? true,
        });
        definir_opcoes(edicao
            ? especial.opcoes.map((opcao) => ({
                chave: `opcao_${opcao.id}`, id: opcao.id, nome: opcao.nome, cotacao: opcao.cotacao, ativo: opcao.ativo, anulada: opcao.situacao === 'Anulada',
            }))
            : [opcao_vazia(nova_chave()), opcao_vazia(nova_chave())]);
    }, [especial?.id, aberto]);

    const alterar = (mudanca) => definir_dados((atual) => ({ ...atual, ...mudanca }));
    const alterar_opcao = (chave, mudanca) => definir_opcoes((atuais) => atuais.map((opcao) => (opcao.chave === chave ? { ...opcao, ...mudanca } : opcao)));

    const remover_opcao = (opcao) => {
        definir_opcoes((atuais) => atuais.filter((item) => item.chave !== opcao.chave));

        if (opcao.id) definir_removidas((atuais) => [...atuais, opcao]);
    };

    const data_passada = dados.data_limite !== '' && new Date(`${dados.data_limite}:00-03:00`) <= new Date();

    const corpo_categoria = () => ({
        nome: dados.nome.trim(),
        data_limite: para_api(dados.data_limite),
        quantidade_vencedoras: Number(dados.quantidade_vencedoras),
        ativo: dados.ativo,
    });

    // cadastro: categoria e opções numa chamada só; erros das opções vêm como opcoes.N.campo
    const cadastrar = async () => {
        try {
            await api.post('/especiais', {
                ...corpo_categoria(),
                opcoes: opcoes.map((opcao) => ({ nome: opcao.nome.trim(), cotacao: opcao.cotacao })),
            }, cabecalhos);
            ao_salvar('Especial cadastrado.');
        } catch (falha) {
            if (falha.response?.status !== 422) throw falha;

            const todos = erros_por_campo(falha);
            const por_opcao = {};

            Object.entries(todos).forEach(([campo, mensagem]) => {
                const partes = campo.match(/^opcoes\.(\d+)\.(\w+)$/);

                if (partes) por_opcao[opcoes[Number(partes[1])]?.chave] = { ...por_opcao[opcoes[Number(partes[1])]?.chave], [partes[2]]: mensagem };
            });

            definir_erros(todos);
            definir_erros_opcoes(por_opcao);
        }
    };

    // edição (R-12): categoria, opções novas, alteradas e removidas, em sequência; no primeiro erro
    // para e mostra no campo, com o aviso de que o anterior já foi salvo
    const editar = async () => {
        const base = `/especiais/${especial.id}`;
        const originais = Object.fromEntries(especial.opcoes.map((opcao) => [opcao.id, opcao]));
        let algum_salvo = false;

        const passo = async (chave_opcao, chamada) => {
            try {
                const resposta = await chamada();
                algum_salvo = true;

                return resposta;
            } catch (falha) {
                if (falha.response?.status !== 422) throw falha;

                if (chave_opcao) definir_erros_opcoes({ [chave_opcao]: erros_por_campo(falha) });
                else definir_erros(erros_por_campo(falha));

                definir_salvo_em_parte(algum_salvo);

                return false;
            }
        };

        if (!(await passo(null, () => api.put(base, corpo_categoria(), cabecalhos)))) return;

        for (const opcao of opcoes.filter((item) => !item.anulada)) {
            if (!opcao.id) {
                const corpo = { nome: opcao.nome.trim(), cotacao: opcao.cotacao, ativo: opcao.ativo };
                const resposta = await passo(opcao.chave, () => api.post(`${base}/opcoes`, corpo, cabecalhos));

                if (!resposta) return;

                // a opção criada passa a ser existente, para salvar de novo não criá-la outra vez
                originais[resposta.data.data.id] = resposta.data.data;
                alterar_opcao(opcao.chave, { id: resposta.data.data.id });
            } else {
                const corpo = alteracoes(opcao, originais[opcao.id]);

                if (Object.keys(corpo).length && !(await passo(opcao.chave, () => api.put(`${base}/opcoes/${opcao.id}`, corpo, cabecalhos)))) return;
            }
        }

        for (const opcao of removidas) {
            if (!(await passo(opcao.chave, () => api.delete(`${base}/opcoes/${opcao.id}`, cabecalhos)))) {
                // a opção que não pôde sair ("Desative a opção em vez de remover.") volta para a
                // grade, com o erro na linha dela
                definir_opcoes((atuais) => [...atuais, opcao]);
                definir_removidas((atuais) => atuais.filter((item) => item.id !== opcao.id));

                return;
            }

            definir_removidas((atuais) => atuais.filter((item) => item.id !== opcao.id));
        }

        ao_salvar('Especial alterado.');
    };

    const salvar = async () => {
        definir_salvando(true);
        definir_erros({});
        definir_erros_opcoes({});
        definir_salvo_em_parte(false);

        try {
            if (edicao) await editar();
            else await cadastrar();
        } catch (falha) {
            alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    const erro_geral = erros.especial ?? erros.opcoes ?? null;

    return (
        <CashModal
            aberto={aberto}
            icone="emoji_events"
            titulo={edicao ? 'Editar especial' : 'Novo especial'}
            descricao="Preencha os dados e as opções do especial."
            ao_fechar={ao_fechar}
            altura_fixa
            largura={760}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando || !dados.nome.trim() || !dados.data_limite} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            {aberto && (
                <>
                    <Group>
                        <Title>Dados</Title>
                        <Fields>
                            <Field $inteiro>
                                <Label htmlFor="especial_nome">Nome</Label>
                                <Input id="especial_nome" maxLength={150} placeholder="Ex.: Campeão Brasileiro 2026" value={dados.nome} $erro={Boolean(erros.nome)} onChange={(evento) => alterar({ nome: evento.target.value })} />
                                {erros.nome && <Error role="alert">{erros.nome}</Error>}
                            </Field>
                            <Field>
                                <Label htmlFor="especial_data_limite">Data limite (horário de Brasília)</Label>
                                <Input id="especial_data_limite" type="datetime-local" value={dados.data_limite} $erro={Boolean(erros.data_limite)} onChange={(evento) => alterar({ data_limite: evento.target.value })} />
                                {erros.data_limite && <Error role="alert">{erros.data_limite}</Error>}
                            </Field>
                            <Field>
                                <Label htmlFor="especial_quantidade">Quantidade de vencedoras</Label>
                                <Input id="especial_quantidade" type="number" min={1} step={1} inputMode="numeric" value={dados.quantidade_vencedoras} $erro={Boolean(erros.quantidade_vencedoras)} onChange={(evento) => alterar({ quantidade_vencedoras: evento.target.value })} />
                                {erros.quantidade_vencedoras && <Error role="alert">{erros.quantidade_vencedoras}</Error>}
                            </Field>
                            <Field>
                                <Label htmlFor="especial_ativo">Ativo</Label>
                                <Select id="especial_ativo" value={dados.ativo ? '1' : '0'} onChange={(evento) => alterar({ ativo: evento.target.value === '1' })}>
                                    <option value="1">Sim</option>
                                    <option value="0">Não</option>
                                </Select>
                            </Field>
                        </Fields>
                    </Group>

                    <Group>
                        <Title>Opções</Title>
                        <OptionsHead aria-hidden="true">
                            <span>Nome</span>
                            <span>Cotação</span>
                            <span>Ativo</span>
                            <span />
                        </OptionsHead>
                        {opcoes.map((opcao, indice) => {
                            const erro = erros_opcoes[opcao.chave] ?? {};

                            return (
                                <OptionRow key={opcao.chave}>
                                    <OptionCell $area="nome">
                                        <Label htmlFor={`opcao_nome_${opcao.chave}`} className="so_celular">Nome</Label>
                                        <Input
                                            id={`opcao_nome_${opcao.chave}`}
                                            maxLength={150}
                                            aria-label={`Nome da opção ${indice + 1}`}
                                            value={opcao.nome}
                                            disabled={opcao.anulada}
                                            $erro={Boolean(erro.nome ?? erro.opcao)}
                                            onChange={(evento) => alterar_opcao(opcao.chave, { nome: evento.target.value })}
                                        />
                                        {opcao.anulada && <Badge>Anulada</Badge>}
                                        {(erro.nome ?? erro.opcao) && <Error role="alert">{erro.nome ?? erro.opcao}</Error>}
                                    </OptionCell>
                                    <OptionCell $area="cotacao">
                                        <Label htmlFor={`opcao_cotacao_${opcao.chave}`} className="so_celular">Cotação</Label>
                                        <DecimalField
                                            id={`opcao_cotacao_${opcao.chave}`}
                                            valor={opcao.cotacao}
                                            desabilitado={opcao.anulada}
                                            erro={Boolean(erro.cotacao)}
                                            ao_mudar={(cotacao) => alterar_opcao(opcao.chave, { cotacao })}
                                        />
                                        {erro.cotacao && <Error role="alert">{erro.cotacao}</Error>}
                                    </OptionCell>
                                    <OptionCell $area="ativo">
                                        <Label htmlFor={`opcao_ativo_${opcao.chave}`} className="so_celular">Ativo</Label>
                                        <Select
                                            id={`opcao_ativo_${opcao.chave}`}
                                            aria-label={`Opção ${indice + 1} ativa`}
                                            value={opcao.ativo ? '1' : '0'}
                                            disabled={opcao.anulada || !edicao}
                                            onChange={(evento) => alterar_opcao(opcao.chave, { ativo: evento.target.value === '1' })}
                                        >
                                            <option value="1">Sim</option>
                                            <option value="0">Não</option>
                                        </Select>
                                    </OptionCell>
                                    <RemoveButton
                                        type="button"
                                        title="Remover opção"
                                        aria-label={`Remover a opção ${indice + 1}`}
                                        disabled={opcao.anulada}
                                        onClick={() => remover_opcao(opcao)}
                                    >
                                        <i className="material-icons" aria-hidden="true">delete_outline</i>
                                    </RemoveButton>
                                </OptionRow>
                            );
                        })}
                        {opcoes.length < 100 && (
                            <AddButton type="button" onClick={() => definir_opcoes((atuais) => [...atuais, opcao_vazia(nova_chave())])}>
                                <i className="material-icons" aria-hidden="true">add</i>
                                Adicionar opção
                            </AddButton>
                        )}
                    </Group>

                    {erro_geral && <Error role="alert">{erro_geral}</Error>}
                    {data_passada && (
                        <Warning $tom="#ef6c00">
                            <i className="material-icons" aria-hidden="true">warning</i>
                            A data limite já passou: o especial não vai aceitar palpites.
                        </Warning>
                    )}
                    {salvo_em_parte && (
                        <Warning $tom="#ef6c00">
                            <i className="material-icons" aria-hidden="true">info</i>
                            Os dados até aqui já foram salvos.
                        </Warning>
                    )}
                </>
            )}
        </CashModal>
    );
}
