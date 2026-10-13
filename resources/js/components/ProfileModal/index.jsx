import { useEffect, useRef, useState } from 'react';

import ModalCard, { ModalButton } from '../ModalCard';
import Spinner from '../Spinner';
import api from '../../utils/api';
import { alerta_erro, alerta_sucesso } from '../../utils/alerts';
import { Field, Grid, Hint, Input, Lock, Panel, Section, Select, Tab, Tabs, ErrorText } from './styles';

const generos = ['Masculino', 'Feminino', 'Outro', 'Não informado'];
const tipos_chave = ['CPF', 'CNPJ', 'Telefone', 'E-mail', 'Chave aleatória'];
const aviso_travado = 'Para alterar, fale com o atendimento.';

// máscara da chave Pix conforme o tipo (edit_register do antigo)
const mascarar_chave = (tipo, texto) => {
    const digitos = texto.replace(/\D/g, '');

    if (tipo === 'CPF') return digitos.slice(0, 11).replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    if (tipo === 'CNPJ') return digitos.slice(0, 14).replace(/(\d{2})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    if (tipo === 'Telefone') return digitos.slice(0, 11).replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d{1,4})$/, '$1-$2');

    return texto;
};

const erros_por_campo = (erro) => Object.fromEntries(Object.entries(erro.response?.data?.errors ?? {}).map(([campo, mensagens]) => [campo, mensagens[0]]));

// Meus dados (modals/edit_register do antigo, com visual novo): CPF, e-mail e nascimento o cliente
// preenche uma vez (depois só o atendimento); telefone só leitura; nome, gênero, senha e chave Pix
// livres. foco lista os campos que faltam para depositar ou sacar; perfil ({ meus, meios }) são os
// dados já carregados pela tela, para abrir sem esperar a API
export default function ProfileModal({ aberto, foco = [], perfil = null, ao_fechar, ao_salvar, ao_senha_alterada }) {
    const [aba, definir_aba] = useState('dados');
    const [cliente, definir_cliente] = useState(null);
    const [dados, definir_dados] = useState({});
    const [pix, definir_pix] = useState({ id: null, pix_tipo_chave: 'CPF', pix_chave: '', pix_nome_titular: '' });
    const [senha, definir_senha] = useState({ senha_atual: '', password: '', password_confirmation: '' });
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);
    // altura medida do conteúdo da aba: o painel anima até ela (height: auto não anima)
    const conteudo = useRef(null);
    const [altura_painel, definir_altura_painel] = useState(null);

    // o conteúdo só existe com o modal aberto; ao fechar, a altura recomeça sem transição
    useEffect(() => {
        if (!aberto || !conteudo.current) {
            definir_altura_painel(null);
            return undefined;
        }

        const observador = new ResizeObserver(([entrada]) => definir_altura_painel(entrada.target.offsetHeight));
        observador.observe(conteudo.current);

        return () => observador.disconnect();
    }, [aberto]);

    useEffect(() => {
        if (!aberto) return;

        definir_aba(foco.includes('pix') && !foco.includes('cpf') && !foco.includes('email') ? 'pix' : 'dados');
        definir_erros({});

        const preencher = (meus, meios) => {
            definir_cliente(meus);
            definir_dados({
                nome: meus.nome ?? '',
                genero: meus.genero ?? '',
                email: meus.email ?? '',
                cpf: meus.cpf ?? '',
                data_nascimento: meus.data_nascimento ?? '',
            });

            const chave = meios.filter((meio) => meio.tipo === 'Pix').sort((a, b) => Number(b.principal) - Number(a.principal))[0];
            if (chave) definir_pix({ id: chave.id, pix_tipo_chave: chave.pix_tipo_chave, pix_chave: mascarar_chave(chave.pix_tipo_chave, chave.pix_chave ?? ''), pix_nome_titular: chave.pix_nome_titular ?? '' });
        };

        // com os dados da tela, abre preenchido; sem eles, busca na API
        if (perfil) {
            preencher(perfil.meus, perfil.meios);
            return;
        }

        definir_cliente(null);

        Promise.all([api.get('/area-cliente/meus-dados'), api.get('/area-cliente/meios-pagamento')])
            .then(([{ data: meus }, { data: meios }]) => preencher(meus.data, meios.data))
            .catch((erro) => {
                alerta_erro(erro);
                ao_fechar();
            });
        // preenche a cada abertura; o foco só escolhe a aba inicial
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [aberto]);

    const mudar = (campo) => (evento) => {
        definir_dados((atual) => ({ ...atual, [campo]: evento.target.value }));
        definir_erros((atuais) => ({ ...atuais, [campo]: undefined }));
    };

    const salvar_dados = async () => {
        // só envia os campos que o cliente pode mudar: os travados não vão
        const corpo = { nome: dados.nome, genero: dados.genero };
        if (!cliente.email && dados.email) corpo.email = dados.email.trim();
        if (!cliente.cpf && dados.cpf) corpo.cpf = dados.cpf.replace(/\D/g, '');
        if (!cliente.data_nascimento && dados.data_nascimento) corpo.data_nascimento = dados.data_nascimento;

        await api.patch('/area-cliente/meus-dados', corpo);
    };

    const salvar_pix = async () => {
        if (!pix.pix_chave.trim()) return;

        const corpo = { tipo: 'Pix', principal: true, pix_tipo_chave: pix.pix_tipo_chave, pix_chave: pix.pix_chave, pix_nome_titular: pix.pix_nome_titular };

        if (pix.id) await api.put(`/area-cliente/meios-pagamento/${pix.id}`, corpo);
        else await api.post('/area-cliente/meios-pagamento', corpo);
    };

    const salvar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            if (aba === 'senha') {
                await api.put('/area-cliente/meus-dados/senha', senha);
                definir_senha({ senha_atual: '', password: '', password_confirmation: '' });
                await alerta_sucesso('Senha alterada. Entre de novo com a nova senha.');
                ao_senha_alterada();
                return;
            }

            await salvar_dados();
            await salvar_pix();
            ao_fechar();
            alerta_sucesso('Cadastro atualizado com sucesso!');
            ao_salvar();
        } catch (erro) {
            if (erro.response?.status === 422) definir_erros(erros_por_campo(erro));
            else alerta_erro(erro);
        } finally {
            definir_salvando(false);
        }
    };

    const destaque = (campo) => foco.includes(campo);
    const travado = (campo) => Boolean(cliente?.[campo]);

    const rodape = (
        <>
            <ModalButton type="button" $secundario onClick={ao_fechar} disabled={salvando}>Cancelar</ModalButton>
            <ModalButton type="button" onClick={salvar} disabled={salvando || !cliente}>{salvando ? <Spinner /> : 'Salvar'}</ModalButton>
        </>
    );

    const campo = (nome, rotulo, props = {}) => (
        <Field $destaque={destaque(nome)}>
            <span>{rotulo}{travado(nome) && <Lock className="material-icons" title={aviso_travado}>lock</Lock>}</span>
            <Input value={dados[nome] ?? ''} onChange={mudar(nome)} disabled={travado(nome)} {...props} />
            {travado(nome) && <Hint>{aviso_travado}</Hint>}
            {erros[nome] && <ErrorText>{erros[nome]}</ErrorText>}
        </Field>
    );

    return (
        <ModalCard aberto={aberto} titulo="Meus dados" subtitulo={foco.length ? 'Complete os dados destacados para continuar.' : null} ao_fechar={ao_fechar} rodape={rodape} largura={560}>
            <Tabs role="tablist">
                <Tab type="button" $ativa={aba === 'dados'} onClick={() => definir_aba('dados')}>Dados pessoais</Tab>
                <Tab type="button" $ativa={aba === 'pix'} onClick={() => definir_aba('pix')}>Chave Pix</Tab>
                <Tab type="button" $ativa={aba === 'senha'} onClick={() => definir_aba('senha')}>Senha</Tab>
            </Tabs>

            {/* a altura acompanha o conteúdo da aba com transição suave */}
            <Panel style={{ height: altura_painel ?? undefined }}>
                <div ref={conteudo}>
                    {!cliente ? <Hint><Spinner tamanho={24} cor="#999" /></Hint> : (
                        <>
                            {aba === 'dados' && (
                                <Section>
                                    {campo('nome', 'Nome e sobrenome', { autoComplete: 'name' })}
                                    <Grid>
                                        {campo('cpf', 'CPF', { inputMode: 'numeric', placeholder: '000.000.000-00' })}
                                        {campo('data_nascimento', 'Data de nascimento', { type: 'date' })}
                                    </Grid>
                                    {campo('email', 'E-mail', { type: 'email', autoComplete: 'email' })}
                                    <Grid>
                                        <Field>
                                            <span>Gênero</span>
                                            <Select value={dados.genero} onChange={mudar('genero')}>
                                                <option value="">Selecione</option>
                                                {generos.map((genero) => <option key={genero} value={genero}>{genero}</option>)}
                                            </Select>
                                            {erros.genero && <ErrorText>{erros.genero}</ErrorText>}
                                        </Field>
                                        <Field>
                                            <span>Telefone (login)<Lock className="material-icons" title={aviso_travado}>lock</Lock></span>
                                            <Input value={cliente.telefone ?? ''} disabled />
                                        </Field>
                                    </Grid>
                                </Section>
                            )}

                            {aba === 'pix' && (
                                <Section>
                                    <Hint>Chave usada para receber seus saques.</Hint>
                                    <Field $destaque={destaque('pix')}>
                                        <span>Tipo da chave</span>
                                        <Select value={pix.pix_tipo_chave} onChange={(evento) => definir_pix({ ...pix, pix_tipo_chave: evento.target.value, pix_chave: '' })}>
                                            {tipos_chave.map((tipo) => <option key={tipo} value={tipo}>{tipo}</option>)}
                                        </Select>
                                    </Field>
                                    <Field $destaque={destaque('pix')}>
                                        <span>Chave Pix</span>
                                        <Input
                                            value={pix.pix_chave}
                                            inputMode={['CPF', 'CNPJ', 'Telefone'].includes(pix.pix_tipo_chave) ? 'numeric' : 'text'}
                                            onChange={(evento) => definir_pix({ ...pix, pix_chave: mascarar_chave(pix.pix_tipo_chave, evento.target.value) })}
                                        />
                                        {erros.pix_chave && <ErrorText>{erros.pix_chave}</ErrorText>}
                                    </Field>
                                    <Field $destaque={destaque('pix')}>
                                        <span>Nome do titular da chave</span>
                                        <Input value={pix.pix_nome_titular} onChange={(evento) => definir_pix({ ...pix, pix_nome_titular: evento.target.value })} />
                                        {erros.pix_nome_titular && <ErrorText>{erros.pix_nome_titular}</ErrorText>}
                                    </Field>
                                </Section>
                            )}

                            {aba === 'senha' && (
                                <Section>
                                    {['senha_atual', 'password', 'password_confirmation'].map((nome) => (
                                        <Field key={nome}>
                                            <span>{{ senha_atual: 'Senha atual', password: 'Nova senha', password_confirmation: 'Confirme a nova senha' }[nome]}</span>
                                            <Input type="password" autoComplete={nome === 'senha_atual' ? 'current-password' : 'new-password'} value={senha[nome]} onChange={(evento) => definir_senha({ ...senha, [nome]: evento.target.value })} />
                                            {erros[nome] && <ErrorText>{erros[nome]}</ErrorText>}
                                        </Field>
                                    ))}
                                    <Hint>Use letras e números. Depois de trocar a senha, você entra de novo.</Hint>
                                </Section>
                            )}
                        </>
                    )}
                </div>
            </Panel>
        </ModalCard>
    );
}
