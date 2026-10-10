import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';

import Backdrop from '../Backdrop';
import Spinner from '../Spinner';
import useSessao from '../../hooks/useSessao';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { alerta_erro, alerta_sucesso } from '../../utils/alerts';
import {
    Body, Check, CloseButton, Container, Field, FieldError, Form, Hint, InputBox, Logo, Row, SubmitButton, Switch, Tab, Tabs,
} from './styles';

const generos = ['Masculino', 'Feminino', 'Outro', 'Não informado'];

const cadastro_vazio = {
    nome: '', telefone: '', email: '', cpf: '', data_nascimento: '', genero: '',
    password: '', password_confirmation: '', codigo_afiliado: '', aceita_promocao: false,
};

// (11) 99999-9999 enquanto o visitante digita; a API recebe só os dígitos
const formatar_telefone = (texto) => {
    const digitos = texto.replace(/\D/g, '').slice(0, 11);

    if (digitos.length <= 2) return digitos;
    if (digitos.length <= 6) return `(${digitos.slice(0, 2)}) ${digitos.slice(2)}`;
    if (digitos.length <= 10) return `(${digitos.slice(0, 2)}) ${digitos.slice(2, 6)}-${digitos.slice(6)}`;

    return `(${digitos.slice(0, 2)}) ${digitos.slice(2, 7)}-${digitos.slice(7)}`;
};

// erros de validação (422) por campo: só a primeira mensagem de cada um
const erros_por_campo = (erro) => Object.fromEntries(
    Object.entries(erro.response?.data?.errors ?? {}).map(([campo, mensagens]) => [campo, mensagens[0]]),
);

// entrar (apostador ou painel) e criar conta de apostador (spec 005, ajustes do responsável)
export default function AuthModal({ aba, codigo_afiliado = '', ao_trocar_aba, ao_fechar }) {
    const { tema, nome_sistema } = usePage().props;
    const { entrar, cadastrar } = useSessao();
    const visivel = Boolean(aba);
    useVoltarFecha(visivel, ao_fechar);
    // ao fechar, mantém a última aba na tela enquanto o modal esmaece
    const ultima_aba = useRef(aba);
    if (aba) ultima_aba.current = aba;
    const aba_exibida = aba ?? ultima_aba.current ?? 'entrar';

    const [login, definir_login] = useState({ identificacao: '', senha: '' });
    const [cadastro, definir_cadastro] = useState({ ...cadastro_vazio, codigo_afiliado });
    const [erros, definir_erros] = useState({});
    const [ver_senha, definir_ver_senha] = useState(false);
    const [enviando, definir_enviando] = useState(false);

    useEffect(() => {
        definir_erros({});
        definir_ver_senha(false);
    }, [aba]);

    useEffect(() => {
        if (codigo_afiliado) definir_cadastro((atual) => ({ ...atual, codigo_afiliado }));
    }, [codigo_afiliado]);

    // Esc fecha o modal
    useEffect(() => {
        if (!visivel) return undefined;

        const ao_teclar = (evento) => evento.key === 'Escape' && ao_fechar();
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [visivel, ao_fechar]);

    const mudar_cadastro = (campo) => (evento) => {
        const valor = evento.currentTarget.type === 'checkbox' ? evento.currentTarget.checked : evento.currentTarget.value;

        definir_cadastro((atual) => ({ ...atual, [campo]: campo === 'telefone' ? formatar_telefone(valor) : valor }));
        definir_erros((atuais) => ({ ...atuais, [campo]: undefined }));
    };

    const enviar_login = async (evento) => {
        evento.preventDefault();
        definir_enviando(true);

        try {
            await entrar(login.identificacao, login.senha);
            definir_login({ identificacao: '', senha: '' });
            ao_fechar();
            alerta_sucesso('Login realizado com sucesso!');
        } catch (erro) {
            alerta_erro(erro);
        } finally {
            definir_enviando(false);
        }
    };

    const enviar_cadastro = async (evento) => {
        evento.preventDefault();

        if (cadastro.password !== cadastro.password_confirmation) {
            definir_erros({ password_confirmation: 'As senhas não conferem.' });
            return;
        }

        definir_enviando(true);

        try {
            await cadastrar({
                ...cadastro,
                telefone: cadastro.telefone.replace(/\D/g, ''),
                cpf: cadastro.cpf.replace(/\D/g, '') || null,
                email: cadastro.email.trim() || null,
                codigo_afiliado: cadastro.codigo_afiliado.trim() || null,
            });
            definir_cadastro({ ...cadastro_vazio });
            ao_fechar();
            alerta_sucesso('Cadastro realizado! Você já está conectado.');
        } catch (erro) {
            if (erro.response?.status === 422) definir_erros(erros_por_campo(erro));
            else alerta_erro(erro);
        } finally {
            definir_enviando(false);
        }
    };

    const olho = (
        <button type="button" onClick={() => definir_ver_senha((atual) => !atual)} aria-label={ver_senha ? 'Esconder senha' : 'Mostrar senha'}>
            <i className="material-icons">{ver_senha ? 'visibility_off' : 'visibility'}</i>
        </button>
    );

    const campo = (nome, rotulo, props = {}, dica = null) => (
        <Field>
            {rotulo}
            <InputBox $erro={Boolean(erros[nome])}>
                <input name={nome} value={cadastro[nome]} onChange={mudar_cadastro(nome)} {...props} />
                {nome.startsWith('password') ? olho : null}
            </InputBox>
            {erros[nome] ? <FieldError>{erros[nome]}</FieldError> : dica && <Hint>{dica}</Hint>}
        </Field>
    );

    return (
        <>
            <Container $visivel={visivel} role="dialog" aria-modal="true" aria-label={aba_exibida === 'cadastrar' ? 'Criar conta' : 'Entrar'}>
                <CloseButton type="button" onClick={ao_fechar} aria-label="Fechar">
                    <i className="material-icons">close</i>
                </CloseButton>
                <Logo src={tema.logo} alt={nome_sistema} />

                <Tabs role="tablist">
                    <Tab type="button" role="tab" $ativa={aba_exibida === 'entrar'} aria-selected={aba_exibida === 'entrar'} onClick={() => ao_trocar_aba('entrar')}>Entrar</Tab>
                    <Tab type="button" role="tab" $ativa={aba_exibida === 'cadastrar'} aria-selected={aba_exibida === 'cadastrar'} onClick={() => ao_trocar_aba('cadastrar')}>Criar conta</Tab>
                </Tabs>

                <Body>
                    {aba_exibida === 'entrar' && (
                        <Form onSubmit={enviar_login}>
                            <Field>
                                Login ou telefone
                                <InputBox>
                                    <input
                                        autoComplete="username"
                                        placeholder="Seu login ou telefone com DDD"
                                        value={login.identificacao}
                                        onChange={(evento) => definir_login({ ...login, identificacao: evento.currentTarget.value })}
                                        required
                                    />
                                </InputBox>
                            </Field>
                            <Field>
                                Senha
                                <InputBox>
                                    <input
                                        type={ver_senha ? 'text' : 'password'}
                                        autoComplete="current-password"
                                        placeholder="Sua senha"
                                        value={login.senha}
                                        onChange={(evento) => definir_login({ ...login, senha: evento.currentTarget.value })}
                                        required
                                    />
                                    {olho}
                                </InputBox>
                            </Field>
                            <SubmitButton type="submit" disabled={enviando}>
                                {enviando ? <Spinner /> : 'Entrar'}
                            </SubmitButton>
                            <Switch>
                                Ainda não tem conta? <button type="button" onClick={() => ao_trocar_aba('cadastrar')}>Criar conta</button>
                            </Switch>
                        </Form>
                    )}

                    {aba_exibida === 'cadastrar' && (
                        <Form onSubmit={enviar_cadastro} noValidate>
                            {campo('nome', 'Nome completo', { autoComplete: 'name', placeholder: 'Nome e sobrenome', required: true })}
                            <Row>
                                {campo('telefone', 'Telefone (WhatsApp)', { type: 'tel', inputMode: 'numeric', autoComplete: 'tel-national', placeholder: '(11) 99999-9999', required: true }, 'Você usa o telefone para entrar.')}
                                {campo('data_nascimento', 'Data de nascimento', { type: 'date', required: true }, 'Precisa ter 18 anos ou mais.')}
                            </Row>
                            <Field>
                                Gênero
                                <InputBox $erro={Boolean(erros.genero)}>
                                    <select name="genero" value={cadastro.genero} onChange={mudar_cadastro('genero')} required>
                                        <option value="" disabled>Selecione</option>
                                        {generos.map((genero) => <option key={genero} value={genero}>{genero}</option>)}
                                    </select>
                                </InputBox>
                                {erros.genero && <FieldError>{erros.genero}</FieldError>}
                            </Field>
                            <Row>
                                {campo('email', 'E-mail (opcional)', { type: 'email', autoComplete: 'email', placeholder: 'voce@email.com' })}
                                {campo('cpf', 'CPF (opcional)', { inputMode: 'numeric', placeholder: 'Somente números' })}
                            </Row>
                            <Row>
                                {campo('password', 'Senha', { type: ver_senha ? 'text' : 'password', autoComplete: 'new-password', required: true }, 'Mínimo 8 caracteres, com letras e números.')}
                                {campo('password_confirmation', 'Repita a senha', { type: ver_senha ? 'text' : 'password', autoComplete: 'new-password', required: true })}
                            </Row>
                            {campo('codigo_afiliado', 'Código de afiliado (opcional)', { placeholder: 'Se alguém indicou você' })}
                            <Check>
                                <input type="checkbox" checked={cadastro.aceita_promocao} onChange={mudar_cadastro('aceita_promocao')} />
                                Quero receber promoções e novidades.
                            </Check>
                            <SubmitButton type="submit" disabled={enviando}>
                                {enviando ? <Spinner /> : 'Criar minha conta'}
                            </SubmitButton>
                            <Switch>
                                Já tem conta? <button type="button" onClick={() => ao_trocar_aba('entrar')}>Entrar</button>
                            </Switch>
                        </Form>
                    )}
                </Body>
            </Container>
            <Backdrop visivel={visivel} ao_fechar={ao_fechar} />
        </>
    );
}
