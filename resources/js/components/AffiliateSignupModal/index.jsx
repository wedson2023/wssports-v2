import { useEffect, useState } from 'react';

import { MAXIMO_SOBRE, so_digitos_seguidores } from '../AffiliateProfileFields';
import Backdrop from '../Backdrop';
import Spinner from '../Spinner';
import { redes_sociais } from '../../hooks/useAfiliadosPainel';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import api from '../../utils/api';
import { alerta_erro, alerta_sucesso } from '../../utils/alerts';
import {
    chave_pix_para_api, erros_por_campo, estados, formatar_chave_pix, formatar_cpf, formatar_telefone, somente_digitos, tipos_chave_pix,
} from '../../utils/forms';
import {
    Caption, CloseButton, Container, EyeButton, Field, FieldError, Form, Header, Hint, InputIcon, Label, List, SubmitButton, Title,
} from './styles';

const cadastro_vazio = {
    nome: '', login: '', password: '', password_confirmation: '', email: '', cpf: '',
    cidade: '', estado: '', endereco: '', telefone: '', pix_tipo_chave: '', pix_chave: '',
    rede_social: '', link_perfil: '', quantidade_seguidores: '', sobre: '',
};

// cadastro público de afiliado (spec 012, FR-053): campos e visual do modals/affiliated antigo,
// com rótulo, erro por campo, máscaras, confirmação e olho na senha; fica em análise até a banca
export default function AffiliateSignupModal({ aberto, ao_fechar }) {
    useVoltarFecha(aberto, ao_fechar);

    const [cadastro, definir_cadastro] = useState(cadastro_vazio);
    const [erros, definir_erros] = useState({});
    const [ver_senha, definir_ver_senha] = useState(false);
    const [enviando, definir_enviando] = useState(false);

    // Esc fecha o modal
    useEffect(() => {
        if (!aberto) return undefined;

        const ao_teclar = (evento) => evento.key === 'Escape' && ao_fechar();
        window.addEventListener('keydown', ao_teclar);

        return () => window.removeEventListener('keydown', ao_teclar);
    }, [aberto, ao_fechar]);

    const formatar = (campo, valor) => {
        if (campo === 'telefone') return formatar_telefone(valor);
        if (campo === 'cpf') return formatar_cpf(valor);
        if (campo === 'pix_chave') return formatar_chave_pix(cadastro.pix_tipo_chave, valor);
        if (campo === 'quantidade_seguidores') return so_digitos_seguidores(valor);

        return valor;
    };

    const mudar = (campo) => (evento) => {
        const valor = evento.currentTarget.value;

        definir_cadastro((atual) => (campo === 'pix_tipo_chave'
            ? { ...atual, pix_tipo_chave: valor, pix_chave: '' } // trocar o tipo limpa a chave, como no antigo
            : { ...atual, [campo]: formatar(campo, valor) }));
        definir_erros((atuais) => ({ ...atuais, [campo]: undefined }));
    };

    const enviar = async (evento) => {
        evento.preventDefault();

        if (cadastro.password !== cadastro.password_confirmation) {
            definir_erros({ password_confirmation: 'As senhas não conferem.' });
            return;
        }

        definir_enviando(true);

        try {
            const { data } = await api.post('/area-afiliado/cadastro', {
                ...cadastro,
                cpf: somente_digitos(cadastro.cpf),
                telefone: somente_digitos(cadastro.telefone),
                email: cadastro.email.trim(),
                pix_chave: chave_pix_para_api(cadastro.pix_tipo_chave, cadastro.pix_chave),
                quantidade_seguidores: cadastro.quantidade_seguidores === '' ? null : Number(cadastro.quantidade_seguidores),
                sobre: cadastro.sobre.trim() || null,
            });

            definir_cadastro(cadastro_vazio);
            definir_erros({});
            ao_fechar();
            alerta_sucesso(data.message);
        } catch (erro) {
            if (erro.response?.status === 422) definir_erros(erros_por_campo(erro));
            else alerta_erro(erro);
        } finally {
            definir_enviando(false);
        }
    };

    const olho = (
        <EyeButton type="button" onClick={() => definir_ver_senha((atual) => !atual)} aria-label={ver_senha ? 'Esconder senha' : 'Mostrar senha'}>
            <i className="material-icons" aria-hidden="true">{ver_senha ? 'visibility_off' : 'visibility'}</i>
        </EyeButton>
    );

    // campo com ícone à esquerda, como no antigo; o erro da API aparece embaixo
    const campo = (nome, rotulo, icone, controle, dica = null) => (
        <Field key={nome}>
            <Caption>{rotulo}</Caption>
            <Label $erro={Boolean(erros[nome])}>
                <InputIcon className="material-icons" aria-hidden="true">{icone}</InputIcon>
                {controle}
            </Label>
            {erros[nome] ? <FieldError>{erros[nome]}</FieldError> : dica && <Hint>{dica}</Hint>}
        </Field>
    );

    const entrada = (nome, props = {}) => (
        <input name={nome} autoComplete="off" value={cadastro[nome]} onChange={mudar(nome)} aria-invalid={Boolean(erros[nome])} {...props} />
    );

    const tipo_senha = ver_senha ? 'text' : 'password';

    return (
        <>
            <Container $visivel={aberto} role="dialog" aria-modal="true" aria-label="Cadastro afiliados">
                <Header>
                    <Title>Cadastro afiliados</Title>
                    <CloseButton type="button" onClick={ao_fechar} aria-label="Fechar">
                        <i className="material-icons" aria-hidden="true">close</i>
                    </CloseButton>
                </Header>
                <List>
                    <Form onSubmit={enviar} noValidate>
                        {campo('nome', 'Nome completo', 'person', entrada('nome', { placeholder: 'Nome completo.', autoComplete: 'name' }))}
                        {campo('login', 'Login', 'account_circle', entrada('login', { placeholder: 'Seu login.', autoComplete: 'username' }), 'Letras, números, ponto, hífen ou sublinhado.')}
                        {campo('password', 'Senha', 'vpn_key', <>{entrada('password', { type: tipo_senha, placeholder: 'Digite sua senha.', autoComplete: 'new-password' })}{olho}</>, 'Mínimo 8 caracteres, com letras e números.')}
                        {campo('password_confirmation', 'Repita a senha', 'vpn_key', entrada('password_confirmation', { type: tipo_senha, placeholder: 'Repita sua senha.', autoComplete: 'new-password' }))}
                        {campo('email', 'E-mail', 'alternate_email', entrada('email', { type: 'email', placeholder: 'Seu email.', autoComplete: 'email' }))}
                        {campo('cpf', 'CPF', 'key', entrada('cpf', { inputMode: 'numeric', placeholder: 'Seu CPF.' }))}
                        {campo('cidade', 'Cidade', 'location_city', entrada('cidade', { placeholder: 'Sua cidade.' }))}
                        {campo('estado', 'Estado', 'apartment', (
                            <select name="estado" value={cadastro.estado} onChange={mudar('estado')}>
                                <option value="">Selecione</option>
                                {estados.map(([uf, nome]) => <option key={uf} value={uf}>{nome}</option>)}
                            </select>
                        ))}
                        {campo('endereco', 'Endereço', 'home', entrada('endereco', { placeholder: 'Seu endereço.' }))}
                        {campo('telefone', 'Telefone', 'smartphone', entrada('telefone', { type: 'tel', inputMode: 'numeric', placeholder: 'Digite seu telefone.' }))}
                        {campo('pix_tipo_chave', 'Tipo de chave Pix', 'list', (
                            <select name="pix_tipo_chave" value={cadastro.pix_tipo_chave} onChange={mudar('pix_tipo_chave')}>
                                <option value="">Tipo chave PIX</option>
                                {tipos_chave_pix.map((tipo) => <option key={tipo} value={tipo}>{tipo}</option>)}
                            </select>
                        ))}
                        {campo('pix_chave', 'Chave Pix', 'vpn_key', entrada('pix_chave', { disabled: !cadastro.pix_tipo_chave, placeholder: 'Digite seu pix.' }))}
                        {/* perfil na rede social (spec 022, FR-017) */}
                        {campo('rede_social', 'Rede social principal', 'public', (
                            <select name="rede_social" value={cadastro.rede_social} onChange={mudar('rede_social')}>
                                <option value="">Selecione</option>
                                {redes_sociais.map((rede) => <option key={rede} value={rede}>{rede}</option>)}
                            </select>
                        ))}
                        {campo('link_perfil', 'Link do perfil', 'link', entrada('link_perfil', { type: 'url', maxLength: 255, placeholder: 'https://' }))}
                        {campo('quantidade_seguidores', 'Quantidade de seguidores', 'groups', entrada('quantidade_seguidores', { inputMode: 'numeric', placeholder: 'Ex.: 12000' }))}
                        {campo('sobre', 'Sobre você', 'description', (
                            <textarea name="sobre" maxLength={MAXIMO_SOBRE} value={cadastro.sobre} onChange={mudar('sobre')} aria-invalid={Boolean(erros.sobre)} placeholder="Conte um pouco sobre o seu público e como vai divulgar." />
                        ), `Opcional. ${cadastro.sobre.length}/${MAXIMO_SOBRE}`)}
                        <SubmitButton type="submit" disabled={enviando} aria-busy={enviando}>
                            {enviando ? <Spinner tamanho={30} /> : <span>CRIAR CONTA</span>}
                        </SubmitButton>
                    </Form>
                </List>
            </Container>
            <Backdrop visivel={aberto} ao_fechar={ao_fechar} />
        </>
    );
}
