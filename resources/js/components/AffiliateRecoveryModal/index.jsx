import { useEffect, useState } from 'react';

import Backdrop from '../Backdrop';
import Spinner from '../Spinner';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro, alerta_sucesso } from '../../utils/alerts';
import { erros_por_campo, somente_digitos } from '../../utils/forms';
import {
    Caption, CloseButton, Container, EyeButton, Field, FieldError, Form, Header, Hint, InputIcon, Label, LinkButton, List, SubmitButton, Text, Title,
} from './styles';

const dados_vazios = { identificacao: '', codigo: '', password: '', password_confirmation: '' };

// "Esqueci minha senha" do afiliado (spec 012, FR-021a): etapa 1 pede o código por e-mail;
// etapa 2 troca a senha com o código. ao_concluir abre o modal de entrada
export default function AffiliateRecoveryModal({ aberto, ao_fechar, ao_concluir }) {
    useVoltarFecha(aberto, ao_fechar);

    const [etapa, definir_etapa] = useState('pedir');
    const [dados, definir_dados] = useState(dados_vazios);
    const [erros, definir_erros] = useState({});
    const [ver_senha, definir_ver_senha] = useState(false);
    const [enviando, definir_enviando] = useState(false);

    // cada abertura começa do pedido do código
    useEffect(() => {
        if (!aberto) return undefined;

        definir_etapa('pedir');
        definir_dados(dados_vazios);
        definir_erros({});

        const ao_teclar = (evento) => evento.key === 'Escape' && ao_fechar();
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    const mudar = (campo) => (evento) => {
        const valor = campo === 'codigo' ? somente_digitos(evento.currentTarget.value).slice(0, 6) : evento.currentTarget.value;

        definir_dados((atuais) => ({ ...atuais, [campo]: valor }));
        definir_erros((atuais) => ({ ...atuais, [campo]: undefined }));
    };

    const enviar = (acao) => async (evento) => {
        evento.preventDefault();
        definir_enviando(true);

        try {
            await acao();
        } catch (erro) {
            if (erro.response?.status === 422 && erro.response.data?.errors) definir_erros(erros_por_campo(erro));
            else alerta_erro(erro);
        } finally {
            definir_enviando(false);
        }
    };

    const pedir_codigo = enviar(async () => {
        const { data } = await api.post('/area-afiliado/auth/recuperar-senha', { identificacao: dados.identificacao.trim() });

        definir_etapa('redefinir');
        alerta_sucesso(data.message);
    });

    const redefinir = enviar(async () => {
        if (dados.password !== dados.password_confirmation) {
            definir_erros({ password_confirmation: 'As senhas não conferem.' });
            return;
        }

        await api.post('/area-afiliado/auth/redefinir-senha', { ...dados, identificacao: dados.identificacao.trim() });

        ao_fechar();
        await alerta_sucesso('Senha alterada! Entre com a senha nova.');
        ao_concluir?.();
    });

    const campo = (nome, rotulo, icone, props = {}, dica = null, extra = null) => (
        <Field>
            <Caption>{rotulo}</Caption>
            <Label $erro={Boolean(erros[nome])}>
                <InputIcon className="material-icons" aria-hidden="true">{icone}</InputIcon>
                <input name={nome} value={dados[nome]} onChange={mudar(nome)} aria-invalid={Boolean(erros[nome])} {...props} />
                {extra}
            </Label>
            {erros[nome] ? <FieldError>{erros[nome]}</FieldError> : dica && <Hint>{dica}</Hint>}
        </Field>
    );

    const olho = (
        <EyeButton type="button" onClick={() => definir_ver_senha((atual) => !atual)} aria-label={ver_senha ? 'Esconder senha' : 'Mostrar senha'}>
            <i className="material-icons" aria-hidden="true">{ver_senha ? 'visibility_off' : 'visibility'}</i>
        </EyeButton>
    );

    const botao = (texto) => (
        <SubmitButton type="submit" disabled={enviando} aria-busy={enviando}>
            {enviando ? <Spinner tamanho={30} /> : <span>{texto}</span>}
        </SubmitButton>
    );

    return (
        <>
            <Container $visivel={aberto} role="dialog" aria-modal="true" aria-label="Esqueci minha senha">
                <Header>
                    <Title>Esqueci minha senha</Title>
                    <CloseButton type="button" onClick={ao_fechar} aria-label="Fechar">
                        <i className="material-icons" aria-hidden="true">close</i>
                    </CloseButton>
                </Header>
                <List>
                    {etapa === 'pedir' ? (
                        <Form onSubmit={pedir_codigo} noValidate>
                            <Text>Informe seu login ou e-mail. Enviaremos um código para o e-mail do cadastro.</Text>
                            {campo('identificacao', 'Login ou e-mail', 'account_circle', { autoComplete: 'username', placeholder: 'Seu login ou e-mail.' })}
                            {botao('ENVIAR CÓDIGO')}
                        </Form>
                    ) : (
                        <Form onSubmit={redefinir} noValidate>
                            <Text>Digite o código que chegou no seu e-mail (vale por 15 minutos) e a senha nova.</Text>
                            {campo('codigo', 'Código', 'pin', { inputMode: 'numeric', autoComplete: 'one-time-code', placeholder: '000000' })}
                            {campo('password', 'Nova senha', 'vpn_key', { type: ver_senha ? 'text' : 'password', autoComplete: 'new-password' }, 'Mínimo 8 caracteres, com letras e números.', olho)}
                            {campo('password_confirmation', 'Repita a nova senha', 'vpn_key', { type: ver_senha ? 'text' : 'password', autoComplete: 'new-password' })}
                            {botao('ALTERAR SENHA')}
                            <LinkButton type="button" onClick={() => definir_etapa('pedir')}>Pedir outro código</LinkButton>
                        </Form>
                    )}
                </List>
            </Container>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} />
        </>
    );
}
