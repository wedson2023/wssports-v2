import { useEffect, useState } from 'react';

import AffiliateProfileFields from '../AffiliateProfileFields';
import CashModal, { Button, Error, Field, Fields, GhostButton, Hint, Input, Label } from '../CashModal';
import Spinner from '../Spinner';
import { carregar_gerentes, tipos_ganho } from '../../hooks/useAfiliadosPainel';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import {
    chave_pix_para_api, erros_por_campo, estados, formatar_chave_pix, formatar_cpf, formatar_telefone, somente_digitos, tipos_chave_pix,
} from '../../utils/forms';
import { Group, Loading, Select, Title } from './styles';

const em_branco = {
    nome: '',
    login: '',
    password: '',
    password_confirmation: '',
    email: '',
    cpf: '',
    ddi: '55',
    telefone: '',
    cidade: '',
    estado: '',
    endereco: '',
    pix_tipo_chave: '',
    pix_chave: '',
    rede_social: '',
    link_perfil: '',
    quantidade_seguidores: '',
    sobre: '',
    usuarios_id: '',
    codigo: '',
    situacao: '',
    tipo_ganho: '',
    percentual: '',
};

// afiliado da API → campos do formulário (CPF, e-mail, telefone e Pix podem vir mascarados)
const do_servidor = (afiliado) => ({
    ...em_branco,
    nome: afiliado.nome ?? '',
    login: afiliado.login ?? '',
    email: afiliado.email ?? '',
    cpf: afiliado.cpf ?? '',
    ddi: afiliado.ddi ?? '55',
    telefone: afiliado.telefone ?? '',
    cidade: afiliado.cidade ?? '',
    estado: afiliado.estado ?? '',
    endereco: afiliado.endereco ?? '',
    pix_tipo_chave: afiliado.meio_pagamento?.pix_tipo_chave ?? '',
    pix_chave: afiliado.meio_pagamento?.pix_chave ?? '',
    rede_social: afiliado.rede_social ?? '',
    link_perfil: afiliado.link_perfil ?? '',
    quantidade_seguidores: afiliado.quantidade_seguidores === null || afiliado.quantidade_seguidores === undefined ? '' : String(afiliado.quantidade_seguidores),
    sobre: afiliado.sobre ?? '',
    usuarios_id: afiliado.gerente ? String(afiliado.gerente.id) : '',
    codigo: afiliado.codigo ?? '',
    situacao: afiliado.situacao ?? '',
});

// valor da API ("10.5") a partir do percentual digitado ("10,5")
const percentual_para_api = (texto) => String(texto ?? '').trim().replace(',', '.');

// campos enviados como chegam (sem formatação própria)
const campos_texto = ['nome', 'email', 'ddi', 'cidade', 'estado', 'endereco', 'rede_social', 'link_perfil', 'sobre', 'codigo'];

// cadastro e edição do afiliado pela gestão (spec 022, FR-010 a FR-015, contracts/paginas §2): campos
// agrupados por seção, em grade (uma coluna no celular), escolhas em seleção (sem interruptor); na
// edição, só os campos alterados vão para a API (os mascarados só quando o usuário digita outro valor)
// e a situação vai na rota própria depois dos dados (R-09). afiliado = {} cadastra; { id } edita
export default function AffiliateFormModal({ afiliado, token, usuario, pode, ao_fechar, ao_salvar }) {
    const aberto = Boolean(afiliado);
    const editando = Boolean(afiliado?.id);
    const [original, definir_original] = useState(em_branco);
    const [dados, definir_dados] = useState(em_branco);
    const [erros, definir_erros] = useState({});
    const [carregando, definir_carregando] = useState(false);
    const [salvando, definir_salvando] = useState(false);
    const [gerentes, definir_gerentes] = useState({ lista: [], sem_acesso: false });
    const [dados_salvos, definir_dados_salvos] = useState(false);

    const e_gerente = usuario?.funcao === 'Gerente';
    const gestor_geral = ['Admin', 'Supervisor'].includes(usuario?.funcao);
    const muda_situacao = editando && pode('afiliados.alterar_situacao') && ['Ativo', 'Inativo'].includes(original.situacao);

    const cabecalhos = { headers: { Authorization: `Bearer ${token}` } };

    useEffect(() => {
        if (!aberto) return undefined;

        let ativo = true;
        definir_erros({});
        definir_dados_salvos(false);

        if (editando) {
            definir_carregando(true);
            api.get(`/afiliados/${afiliado.id}`, cabecalhos)
                .then(({ data }) => {
                    if (!ativo) return;

                    const campos = do_servidor(data.data);
                    definir_original(campos);
                    definir_dados(campos);
                })
                .catch((erro) => {
                    if (!ativo) return;

                    alerta_erro(erro);
                    ao_fechar();
                })
                .finally(() => ativo && definir_carregando(false));
        } else {
            definir_original(em_branco);
            definir_dados(em_branco);
        }

        if (gestor_geral && !gerentes.lista.length && !gerentes.sem_acesso) {
            carregar_gerentes(token)
                .then((lista) => ativo && definir_gerentes({ lista, sem_acesso: false }))
                .catch(() => ativo && definir_gerentes({ lista: [], sem_acesso: true }));
        }

        return () => {
            ativo = false;
        };
    }, [afiliado?.id, aberto]);

    const alterar = (mudanca) => definir_dados((atual) => ({ ...atual, ...mudanca }));

    // corpo da requisição: no cadastro, tudo; na edição, só o que mudou (FR-014)
    const corpo = () => {
        const mudou = (campo) => !editando || dados[campo] !== original[campo];
        const saida = {};

        campos_texto.forEach((campo) => {
            if (campo === 'codigo' && (!editando || !gestor_geral)) return;
            if (mudou(campo)) saida[campo] = dados[campo];
        });

        if (mudou('cpf')) saida.cpf = somente_digitos(dados.cpf);
        if (mudou('telefone')) saida.telefone = somente_digitos(dados.telefone);
        // seguidores apagado vai vazio para o servidor mostrar que não pode voltar a vazio
        if (mudou('quantidade_seguidores')) saida.quantidade_seguidores = dados.quantidade_seguidores === '' ? '' : Number(dados.quantidade_seguidores);

        // tipo e chave do Pix andam juntos
        if (mudou('pix_tipo_chave') || mudou('pix_chave')) {
            saida.pix_tipo_chave = dados.pix_tipo_chave;
            saida.pix_chave = chave_pix_para_api(dados.pix_tipo_chave, dados.pix_chave);
        }

        if (gestor_geral && mudou('usuarios_id')) saida.usuarios_id = dados.usuarios_id ? Number(dados.usuarios_id) : null;

        if (!editando) {
            saida.login = dados.login.trim();
            saida.tipo_ganho = dados.tipo_ganho;
            saida.percentual = percentual_para_api(dados.percentual);
        }

        if (!editando || dados.password) {
            saida.password = dados.password;
            saida.password_confirmation = dados.password_confirmation;
        }

        return saida;
    };

    const salvar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            if (editando) {
                if (!dados_salvos) {
                    const alteracoes = corpo();

                    if (Object.keys(alteracoes).length) await api.patch(`/afiliados/${afiliado.id}`, alteracoes, cabecalhos);

                    definir_dados_salvos(true);
                }

                // situação pela rota própria, depois dos dados (R-09); falhando, os dados já ficaram salvos
                if (muda_situacao && dados.situacao !== original.situacao) {
                    try {
                        await api.patch(`/afiliados/${afiliado.id}/situacao`, { situacao: dados.situacao }, cabecalhos);
                    } catch (falha) {
                        definir_erros({ situacao: falha.response?.data?.errors?.situacao?.[0] ?? falha.response?.data?.message ?? 'Não foi possível alterar a situação.', geral: 'Os dados foram salvos; a situação não foi alterada.' });
                        return;
                    }
                }

                ao_salvar('Afiliado atualizado.');
            } else {
                await api.post('/afiliados', corpo(), cabecalhos);
                ao_salvar('Afiliado cadastrado.');
            }
        } catch (falha) {
            if (falha.response?.status === 422) definir_erros(erros_por_campo(falha));
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    const campo = (nome, rotulo, controle, { inteiro = false, dica = null } = {}) => (
        <Field $inteiro={inteiro}>
            <Label htmlFor={`afiliado_${nome}`}>{rotulo}</Label>
            {controle}
            {dica && <Hint>{dica}</Hint>}
            {erros[nome] && <Error role="alert">{erros[nome]}</Error>}
        </Field>
    );

    const texto = (nome, rotulo, extras = {}, opcoes = {}) => campo(nome, rotulo, (
        <Input id={`afiliado_${nome}`} value={dados[nome]} $erro={Boolean(erros[nome])} onChange={(evento) => alterar({ [nome]: evento.target.value })} {...extras} />
    ), opcoes);

    const selecao = (nome, rotulo, opcoes, { vazio = 'Selecione', desabilitado = false, dica = null } = {}) => campo(nome, rotulo, (
        <Select id={`afiliado_${nome}`} value={dados[nome]} disabled={desabilitado} $erro={Boolean(erros[nome])} onChange={(evento) => alterar({ [nome]: evento.target.value })}>
            <option value="">{vazio}</option>
            {opcoes.map(([valor, rotulo_opcao]) => <option key={valor} value={valor}>{rotulo_opcao}</option>)}
        </Select>
    ), { dica });

    // o gerente atual continua na lista mesmo antes de ela carregar
    const opcoes_gerente = [
        ...(dados.usuarios_id && !gerentes.lista.some((gerente) => String(gerente.id) === dados.usuarios_id) && afiliado?.gerente
            ? [[dados.usuarios_id, afiliado.gerente.nome]] : []),
        ...gerentes.lista.map((gerente) => [String(gerente.id), gerente.nome]),
    ];

    return (
        <CashModal
            aberto={aberto}
            icone={editando ? 'edit' : 'person_add'}
            titulo={editando ? 'Editar afiliado' : 'Cadastrar afiliado'}
            descricao={editando ? 'Altere os dados do afiliado. Os campos de senha vazios mantêm a senha atual.' : 'Preencha os dados para cadastrar um novo afiliado na sua rede.'}
            ao_fechar={ao_fechar}
            altura_fixa
            largura={760}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando || carregando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            {carregando && <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>}
            {!carregando && (
                <>
                    {erros.geral && <Error role="alert">{erros.geral}</Error>}

                    <Group>
                        <Title>Dados pessoais</Title>
                        <Fields>
                            {texto('nome', 'Nome *', { maxLength: 150, autoComplete: 'off' }, { inteiro: true })}
                            {texto('login', 'Login *', { maxLength: 50, autoComplete: 'off', disabled: editando }, { dica: editando ? 'O login não pode ser alterado.' : null })}
                            {editando && gestor_geral && texto('codigo', 'Código do link', { maxLength: 50 }, { dica: 'Letras, números, ponto, hífen ou sublinhado.' })}
                            {texto('email', 'E-mail *', { type: 'email', maxLength: 150 })}
                            {campo('cpf', 'CPF *', (
                                <Input id="afiliado_cpf" inputMode="numeric" value={dados.cpf} $erro={Boolean(erros.cpf)} onChange={(evento) => alterar({ cpf: formatar_cpf(evento.target.value) })} />
                            ))}
                            {texto('password', editando ? 'Nova senha' : 'Senha *', { type: 'password', autoComplete: 'new-password' }, { dica: 'Mínimo de 8 caracteres, com letras e números.' })}
                            {texto('password_confirmation', editando ? 'Confirmar nova senha' : 'Confirmar senha *', { type: 'password', autoComplete: 'new-password' })}
                        </Fields>
                    </Group>

                    <Group>
                        <Title>Contato e endereço</Title>
                        <Fields>
                            {texto('ddi', 'DDI', { inputMode: 'numeric', maxLength: 3 })}
                            {campo('telefone', 'Telefone *', (
                                <Input id="afiliado_telefone" inputMode="tel" value={dados.ddi === '55' && !dados.telefone.includes('*') ? formatar_telefone(dados.telefone) : dados.telefone} $erro={Boolean(erros.telefone)} onChange={(evento) => alterar({ telefone: evento.target.value })} />
                            ))}
                            {texto('cidade', 'Cidade', { maxLength: 100 })}
                            {selecao('estado', 'Estado', estados)}
                            {texto('endereco', 'Endereço', { maxLength: 255 }, { inteiro: true })}
                        </Fields>
                    </Group>

                    <Group>
                        <Title>PIX</Title>
                        <Fields>
                            {selecao('pix_tipo_chave', 'Tipo de chave *', tipos_chave_pix.map((tipo) => [tipo, tipo]))}
                            {campo('pix_chave', 'Chave *', (
                                <Input
                                    id="afiliado_pix_chave"
                                    value={dados.pix_chave}
                                    disabled={!dados.pix_tipo_chave}
                                    $erro={Boolean(erros.pix_chave)}
                                    onChange={(evento) => alterar({ pix_chave: formatar_chave_pix(dados.pix_tipo_chave, evento.target.value) })}
                                />
                            ))}
                        </Fields>
                    </Group>

                    <Group>
                        <Title>Perfil</Title>
                        <AffiliateProfileFields id="afiliado" valores={dados} ao_mudar={alterar} erros={erros} obrigatorios={!editando || original.rede_social !== ''} />
                    </Group>

                    {(!editando || gestor_geral || muda_situacao) && (
                        <Group>
                            <Title>{editando ? 'Gestão' : 'Ganho'}</Title>
                            <Fields>
                                {gestor_geral && selecao('usuarios_id', 'Gerente responsável *', opcoes_gerente, {
                                    desabilitado: gerentes.sem_acesso,
                                    dica: gerentes.sem_acesso ? 'Você não tem acesso à lista de gerentes.' : null,
                                })}
                                {e_gerente && !editando && <Hint>Você fica como gerente responsável deste afiliado.</Hint>}
                                {!editando && selecao('tipo_ganho', 'Tipo de ganho *', tipos_ganho.map((tipo) => [tipo, tipo]))}
                                {!editando && texto('percentual', 'Percentual (%) *', { inputMode: 'decimal', placeholder: 'De 0 a 100' })}
                                {muda_situacao && campo('situacao', 'Situação', (
                                    <Select id="afiliado_situacao" value={dados.situacao} $erro={Boolean(erros.situacao)} onChange={(evento) => alterar({ situacao: evento.target.value })}>
                                        <option value="Ativo">Ativo</option>
                                        <option value="Inativo">Inativo</option>
                                    </Select>
                                ), { dica: 'Inativo não entra no painel do afiliado; os clientes continuam ligados.' })}
                            </Fields>
                            {editando && <Hint>A regra de ganho é trocada pela ação "Trocar regra".</Hint>}
                        </Group>
                    )}
                </>
            )}
        </CashModal>
    );
}
