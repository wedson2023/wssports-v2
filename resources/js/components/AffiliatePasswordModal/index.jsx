import { useEffect, useState } from 'react';

import ModalCard, { ModalButton } from '../ModalCard';
import Spinner from '../Spinner';
import useSessao from '../../hooks/useSessao';
import { alerta_erro, alerta_sucesso } from '../../utils/alerts';
import { erros_por_campo } from '../../utils/forms';
import { ErrorText, Field, Fields, Hint, PasswordBox } from './styles';

const senhas_vazias = { senha_atual: '', password: '', password_confirmation: '' };

// troca de senha do afiliado (spec 012, FR-057): pede a senha atual; a API devolve um token novo,
// que passa a ser o da sessão (os demais deixam de valer)
export default function AffiliatePasswordModal({ aberto, requisitar, ao_fechar }) {
    const { trocar_token } = useSessao();
    const [senhas, definir_senhas] = useState(senhas_vazias);
    const [erros, definir_erros] = useState({});
    const [ver_senha, definir_ver_senha] = useState(false);
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_senhas(senhas_vazias);
        definir_erros({});
        definir_ver_senha(false);
    }, [aberto]);

    const mudar = (campo) => (evento) => {
        const valor = evento.currentTarget.value;

        definir_senhas((atuais) => ({ ...atuais, [campo]: valor }));
        definir_erros((atuais) => ({ ...atuais, [campo]: undefined }));
    };

    const salvar = async () => {
        if (senhas.password !== senhas.password_confirmation) {
            definir_erros({ password_confirmation: 'As senhas não conferem.' });
            return;
        }

        definir_salvando(true);

        try {
            const data = await requisitar({ url: '/area-afiliado/meus-dados/senha', method: 'put', data: senhas });

            trocar_token(data.token, data.expira_em);
            ao_fechar();
            alerta_sucesso('Senha alterada!');
        } catch (erro) {
            if (erro.response?.status === 422) definir_erros(erros_por_campo(erro));
            else if (![401, 403].includes(erro.response?.status)) alerta_erro(erro);
        } finally {
            definir_salvando(false);
        }
    };

    const campo = (nome, rotulo, autocompletar, dica = null) => (
        <Field as="div">
            <span>{rotulo}</span>
            <PasswordBox $erro={Boolean(erros[nome])}>
                <input type={ver_senha ? 'text' : 'password'} autoComplete={autocompletar} value={senhas[nome]} onChange={mudar(nome)} />
                <button type="button" onClick={() => definir_ver_senha((atual) => !atual)} aria-label={ver_senha ? 'Esconder senha' : 'Mostrar senha'}>
                    <i className="material-icons" aria-hidden="true">{ver_senha ? 'visibility_off' : 'visibility'}</i>
                </button>
            </PasswordBox>
            {erros[nome] ? <ErrorText>{erros[nome]}</ErrorText> : dica && <Hint>{dica}</Hint>}
        </Field>
    );

    const rodape = (
        <>
            <ModalButton type="button" $secundario onClick={ao_fechar} disabled={salvando}>Cancelar</ModalButton>
            <ModalButton type="button" onClick={salvar} disabled={salvando}>{salvando ? <Spinner /> : 'Alterar senha'}</ModalButton>
        </>
    );

    return (
        <ModalCard aberto={aberto} titulo="Alterar senha" ao_fechar={ao_fechar} rodape={rodape} largura={440}>
            <Fields>
                {campo('senha_atual', 'Senha atual', 'current-password')}
                {campo('password', 'Nova senha', 'new-password', 'Mínimo 8 caracteres, com letras e números.')}
                {campo('password_confirmation', 'Repita a nova senha', 'new-password')}
            </Fields>
        </ModalCard>
    );
}
