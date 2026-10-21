import { useEffect, useMemo, useState } from 'react';

import Backdrop from '../Backdrop';
import BettingRulesFields from '../BettingRulesFields';
import CommissionFields from '../CommissionFields';
import ModalHeader from '../ModalHeader';
import PanelTabs from '../PanelTabs';
import PermissionsPanel from '../PermissionsPanel';
import Spinner from '../Spinner';
import useSessao from '../../hooks/useSessao';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro, alerta_sucesso, confirmar } from '../../utils/alerts';
import { erros_por_campo, formatar_telefone } from '../../utils/forms';
import {
    Body, Container, Error, Field, Footer, Grid, Hint, Input, Label, SaveButton, Select, Warning,
} from './styles';

// nível de cada função (1 = topo) e a função logo acima, como em Funcao.php
const niveis = { Admin: 1, Supervisor: 2, Gerente: 3, Vendedor: 4 };
const funcao_acima = { Supervisor: 'Admin', Gerente: 'Supervisor', Vendedor: 'Gerente' };
const nome_funcao = { Supervisor: 'supervisor', Gerente: 'gerente', Vendedor: 'vendedor' };

// o superior é escolhido quando há ao menos um nível entre quem edita e a função (FR-009, FR-011)
const escolhe_superior = (logado, funcao) => niveis[funcao] - niveis[logado.funcao] >= 2;

// abas do modal por função (contracts/paginas §3); cadastro só tem Dados
const abas_de = (funcao, editando, logado) => {
    const abas = [{ chave: 'dados', rotulo: 'Dados', icone: 'person' }];

    if (!editando) return abas;

    if (funcao === 'Vendedor') {
        abas.push({ chave: 'comissoes', rotulo: 'Comissões', icone: 'percent' }, { chave: 'regras', rotulo: 'Limites e regras', icone: 'rule' });
    } else if (logado.permissoes.includes('usuarios_configuracoes.editar')) {
        abas.push({ chave: 'massa', rotulo: funcao === 'Gerente' ? 'Regras da equipe' : 'Regras da rede', icone: 'groups' });
    }

    if (logado.permissoes.includes('usuarios.gerenciar_permissoes')) {
        abas.push({ chave: 'permissoes', rotulo: 'Permissões', icone: 'vpn_key' });
    }

    return abas;
};

// campos alterados entre o original e o editado (só eles vão para a API)
const diferencas = (original, editado) => Object.fromEntries(
    Object.entries(editado).filter(([campo, valor]) => JSON.stringify(valor) !== JSON.stringify(original[campo])),
);

// sem os campos em branco (Regras da equipe/rede: vazio = manter o valor de cada vendedor)
const preenchidos = (valores) => Object.fromEntries(
    Object.entries(valores).filter(([, valor]) => valor !== undefined && valor !== '' && !(Array.isArray(valor) && !valor.length)),
);

// cadastro e edição de usuário do painel (modals/register do antigo) com as abas de comissões,
// regras, regras em massa e permissões (spec 015, FR-009 a FR-016)
export default function UserFormModal({ aberto, funcao, usuario = null, logado, ao_fechar, ao_salvar }) {
    const { sessao } = useSessao();
    const editando = Boolean(usuario);
    const abas = useMemo(() => abas_de(funcao, editando, logado), [funcao, editando, logado]);
    const pode_configurar = logado.permissoes.includes('usuarios_configuracoes.editar');
    const com_superior = escolhe_superior(logado, funcao);
    const cabecalhos = { Authorization: `Bearer ${sessao?.token}` };

    const [aba, definir_aba] = useState('dados');
    const [dados, definir_dados] = useState({});
    const [config_original, definir_config_original] = useState(null);
    const [config, definir_config] = useState({});
    const [em_massa, definir_em_massa] = useState({});
    const [superiores, definir_superiores] = useState([]);
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);
    useVoltarFecha(aberto, ao_fechar);

    // estado inicial a cada abertura
    useEffect(() => {
        if (!aberto) return;

        definir_aba('dados');
        definir_erros({});
        definir_em_massa({});
        definir_config_original(null);
        definir_dados(usuario
            ? { nome: usuario.nome, login: usuario.login, telefone: usuario.telefone ?? '', endereco: usuario.endereco ?? '', ativo: usuario.ativo, usuarios_id: usuario.usuarios_id, password: '', password_confirmation: '' }
            : { nome: '', login: '', telefone: '', endereco: '', password: '', password_confirmation: '', usuarios_id: null, comissao_1: '', comissao_2: '', comissao_3: '' });
    }, [aberto, usuario?.id]);

    // todos os superiores da rede para a lista de escolha (de 100 em 100, o máximo por página),
    // para mover o usuário para outro gerente ou supervisor
    useEffect(() => {
        if (!aberto || !com_superior) return undefined;

        let ativo = true;

        const carregar = async () => {
            const todos = [];
            let pagina = 1;
            let ultima = 1;

            do {
                const { data } = await api.get('/usuarios', { params: { funcao: funcao_acima[funcao], page: pagina, por_pagina: 100 }, headers: cabecalhos });
                todos.push(...data.data);
                ultima = data.meta?.last_page ?? 1;
                pagina += 1;
            } while (pagina <= ultima);

            if (!ativo) return;

            definir_superiores(todos);

            // um único superior na rede, no cadastro, já vem escolhido
            if (!editando && todos.length === 1) definir_dados((atuais) => ({ ...atuais, usuarios_id: todos[0].id }));
        };

        carregar().catch(() => ativo && definir_superiores([]));

        return () => {
            ativo = false;
        };
    }, [aberto, com_superior, funcao]);

    // configuração do vendedor, carregada ao abrir a aba de comissões ou de regras
    useEffect(() => {
        if (!aberto || !editando || funcao !== 'Vendedor' || config_original || !['comissoes', 'regras'].includes(aba)) return;

        api.get(`/usuarios-configuracoes/${usuario.id}`, { headers: cabecalhos })
            .then(({ data }) => {
                definir_config_original(data.data);
                definir_config(data.data);
            })
            .catch(alerta_erro);
    }, [aberto, aba, usuario?.id]);

    const mudar_dado = (campo, valor) => {
        definir_dados((atuais) => ({ ...atuais, [campo]: valor }));
        definir_erros((atuais) => ({ ...atuais, [campo]: undefined }));
    };

    const mudar_config = (campo, valor) => {
        definir_config((atuais) => ({ ...atuais, [campo]: valor }));
        definir_erros((atuais) => ({ ...atuais, [campo]: undefined }));
    };

    const mudar_em_massa = (campo, valor) => {
        definir_em_massa((atuais) => ({ ...atuais, [campo]: valor }));
        definir_erros((atuais) => ({ ...atuais, [campo]: undefined }));
    };

    const salvar_dados = async () => {
        if (dados.password !== dados.password_confirmation) {
            definir_erros({ password_confirmation: 'As senhas não conferem.' });
            return false;
        }

        if (!editando) {
            const corpo = { ...dados, funcao, comissao_1: dados.comissao_1 || null, comissao_2: dados.comissao_2 || null, comissao_3: dados.comissao_3 || null };
            delete corpo.password_confirmation;
            if (!com_superior) delete corpo.usuarios_id;
            if (funcao !== 'Vendedor') ['comissao_1', 'comissao_2', 'comissao_3'].forEach((campo) => delete corpo[campo]);

            await api.post('/usuarios', corpo, { headers: cabecalhos });
            alerta_sucesso('Usuário cadastrado com sucesso!');
            return true;
        }

        const original = { nome: usuario.nome, telefone: usuario.telefone ?? '', endereco: usuario.endereco ?? '', ativo: usuario.ativo, usuarios_id: usuario.usuarios_id };
        const alterados = diferencas(original, { nome: dados.nome, telefone: dados.telefone, endereco: dados.endereco, ativo: dados.ativo, usuarios_id: dados.usuarios_id });

        if (dados.password) alterados.password = dados.password;

        if (!Object.keys(alterados).length) {
            alerta_sucesso('Nada foi alterado.');
            return true;
        }

        await api.patch(`/usuarios/${usuario.id}`, alterados, { headers: cabecalhos });
        alerta_sucesso('Dados atualizados com sucesso!');
        return true;
    };

    const salvar_config = async () => {
        const alterados = diferencas(config_original ?? {}, config);

        if (!Object.keys(alterados).length) {
            alerta_sucesso('Nada foi alterado.');
            return true;
        }

        await api.patch('/usuarios-configuracoes', { ...alterados, usuarios_id: usuario.id }, { headers: cabecalhos });
        definir_config_original(config);
        alerta_sucesso('Configurações atualizadas com sucesso!');
        return false;
    };

    const salvar_em_massa = async () => {
        const campos = preenchidos(em_massa);

        if (!Object.keys(campos).length) {
            definir_erros({ geral: 'Altere ao menos um campo.' });
            return false;
        }

        const quantidade = usuario.quantidade_vendedores ?? 0;

        if (funcao === 'Supervisor' && !(await confirmar(`Isso vai alterar ${quantidade} vendedores. Deseja continuar?`))) return false;

        const { data } = await api.patch('/usuarios-configuracoes', { ...campos, usuarios_id: usuario.id }, { headers: cabecalhos });
        alerta_sucesso(`${data.vendedores_alterados ?? quantidade} vendedores alterados.`);
        definir_em_massa({});
        return false;
    };

    const salvar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            const acoes = { dados: salvar_dados, comissoes: salvar_config, regras: salvar_config, massa: salvar_em_massa };
            const fechar = await acoes[aba]();

            if (fechar) ao_salvar();
        } catch (erro) {
            if (erro.response?.status === 422) definir_erros(erros_por_campo(erro));
            else alerta_erro(erro);
        } finally {
            definir_salvando(false);
        }
    };

    const campo_texto = (campo, rotulo, props = {}) => (
        <Field>
            <Label htmlFor={`usuario-${campo}`}>{rotulo}</Label>
            <Input id={`usuario-${campo}`} value={dados[campo] ?? ''} $erro={Boolean(erros[campo])} onChange={(evento) => mudar_dado(campo, evento.target.value)} {...props} />
            {erros[campo] && <Error>{erros[campo]}</Error>}
        </Field>
    );

    const aba_dados = (
        <Grid>
            {campo_texto('nome', 'Nome', { placeholder: 'Ex.: João Silva', title: 'Digite um nome qualquer para o usuário.' })}
            {campo_texto('login', 'Login', { placeholder: 'Ex.: joao_silva', disabled: editando, title: 'Login de acesso: letras sem acento, números, ponto, hífen ou sublinhado, sem espaço.' })}
            {campo_texto('password', editando ? 'Nova senha' : 'Senha', { type: 'password', autoComplete: 'new-password', placeholder: editando ? 'Em branco mantém a atual' : 'Mínimo de 6 caracteres' })}
            {campo_texto('password_confirmation', 'Confirme a senha', { type: 'password', autoComplete: 'new-password' })}
            {com_superior && (
                <Field>
                    <Label htmlFor="usuario-superior">{funcao_acima[funcao]}</Label>
                    <Select
                        id="usuario-superior"
                        value={dados.usuarios_id ?? ''}
                        $erro={Boolean(erros.usuarios_id)}
                        onChange={(evento) => mudar_dado('usuarios_id', evento.target.value ? Number(evento.target.value) : null)}
                    >
                        <option value="">Selecione o {nome_funcao[funcao_acima[funcao]] ?? 'superior'}</option>
                        {superiores.map((superior) => (
                            <option key={superior.id} value={superior.id}>
                                {superior.nome}{superior.ativo ? '' : ' (inativo)'}
                            </option>
                        ))}
                    </Select>
                    {!superiores.length && <Hint>Cadastre um {nome_funcao[funcao_acima[funcao]]} antes.</Hint>}
                    {erros.usuarios_id && <Error>{erros.usuarios_id}</Error>}
                </Field>
            )}
            {campo_texto('telefone', 'Telefone', { type: 'tel', placeholder: '(00) 00000-0000', onChange: (evento) => mudar_dado('telefone', formatar_telefone(evento.target.value)) })}
            {campo_texto('endereco', 'Endereço', { placeholder: 'Opcional' })}
            {editando && (
                <Field>
                    <Label htmlFor="usuario-ativo">Ativo</Label>
                    <Select id="usuario-ativo" value={dados.ativo ? 'sim' : 'nao'} disabled={!logado.permissoes.includes('usuarios.alterar_situacao')} onChange={(evento) => mudar_dado('ativo', evento.target.value === 'sim')}>
                        <option value="sim">Sim</option>
                        <option value="nao">Não</option>
                    </Select>
                </Field>
            )}
            {!editando && funcao === 'Vendedor' && ['1', '2', '3'].map((faixa) => campo_texto(`comissao_${faixa}`, `Comissão ${faixa} ${faixa === '1' ? 'jogo' : 'jogos'} (%)`, {
                type: 'number', min: 0, max: 100, step: '0.01', inputMode: 'decimal', placeholder: ['Ex.: 5', 'Ex.: 7', 'Ex.: 10'][Number(faixa) - 1],
                title: 'De 4 a 12 jogos e no ao vivo vale a regra de 3 jogos; ajuste depois na aba Comissões.',
            }))}
        </Grid>
    );

    const carregando_config = ['comissoes', 'regras'].includes(aba) && !config_original;

    const conteudo = {
        dados: aba_dados,
        comissoes: carregando_config ? <Spinner tamanho={30} cor="currentColor" /> : <CommissionFields valores={config} ao_mudar={mudar_config} somente_leitura={!pode_configurar} erros={erros} />,
        regras: carregando_config ? <Spinner tamanho={30} cor="currentColor" /> : <BettingRulesFields valores={config} ao_mudar={mudar_config} somente_leitura={!pode_configurar} erros={erros} />,
        massa: (usuario?.quantidade_vendedores ?? 0) === 0
            ? <Warning>{funcao === 'Gerente' ? 'Este gerente' : 'Este supervisor'} ainda não tem vendedores.</Warning>
            : (
                <>
                    <Hint>
                        Só os campos preenchidos são gravados, em todos os {usuario?.quantidade_vendedores} vendedores {funcao === 'Gerente' ? 'deste gerente' : 'da rede deste supervisor'}.
                        Os campos em branco mantêm o valor de cada vendedor.
                    </Hint>
                    {erros.geral && <Error>{erros.geral}</Error>}
                    <CommissionFields valores={em_massa} ao_mudar={mudar_em_massa} erros={erros} />
                    <BettingRulesFields valores={em_massa} ao_mudar={mudar_em_massa} erros={erros} em_massa />
                </>
            ),
        permissoes: usuario && <PermissionsPanel usuario_id={usuario.id} />,
    };

    // editar dados exige a permissão da função (só consultar abre o modal para leitura)
    const pode_editar_dados = !editando || logado.permissoes.includes(`usuarios.editar_${funcao.toLowerCase()}`)
        || logado.permissoes.includes('usuarios.alterar_situacao');

    const mostra_salvar = aba !== 'permissoes'
        && !(aba === 'dados' && !pode_editar_dados)
        && !(aba === 'massa' && (usuario?.quantidade_vendedores ?? 0) === 0)
        && !(['comissoes', 'regras'].includes(aba) && !pode_configurar);

    return (
        <>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} camada={45} />
            <Container $visivel={aberto} $altura_fixa={abas.length > 1} role="dialog" aria-label={`${editando ? 'Editar' : 'Cadastrar'} ${nome_funcao[funcao]}`}>
                <ModalHeader
                    icone={editando ? 'manage_accounts' : 'person_add'}
                    titulo={`${editando ? 'Editar' : 'Cadastrar'} ${funcao}`}
                    descricao={editando
                        ? `Altere os dados e as configurações de ${usuario.nome} (${usuario.login}).`
                        : `Preencha os dados para cadastrar um novo ${nome_funcao[funcao]} na sua rede.`}
                    ao_fechar={ao_fechar}
                />
                {abas.length > 1 && <PanelTabs abas={abas} ativa={aba} ao_trocar={definir_aba} />}
                {aberto && <Body>{conteudo[aba]}</Body>}
                {mostra_salvar && (
                    <Footer>
                        <SaveButton type="button" onClick={salvar} disabled={salvando} aria-busy={salvando}>
                            {salvando ? <Spinner tamanho={20} /> : (editando ? 'Salvar' : 'Cadastrar')}
                        </SaveButton>
                    </Footer>
                )}
            </Container>
        </>
    );
}
