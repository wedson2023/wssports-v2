import { useEffect, useState } from 'react';

import CashModal, {
    Error, Field, GhostButton, Hint, Input, Label, Warning,
} from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { erros_por_campo } from '../../utils/forms';
import { DangerButton } from './styles';

// exclusão do afiliado para a lixeira (spec 022, FR-029): avisa o valor ainda em aberto e confirma
// com a senha de quem exclui; não existe exclusão definitiva
export default function AffiliateDeleteModal({ afiliado, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(afiliado);
    const [senha, definir_senha] = useState('');
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_senha('');
        definir_erros({});
    }, [afiliado?.id]);

    const excluir = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            await api.delete(`/afiliados/${afiliado.id}`, { data: { password: senha }, headers: { Authorization: `Bearer ${token}` } });
            ao_concluir('Afiliado enviado para a lixeira.');
        } catch (falha) {
            if (falha.response?.status === 422) definir_erros(erros_por_campo(falha));
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    const a_receber = afiliado?.valor_a_receber ?? '0.00';
    const tem_valor = Number(a_receber) !== 0;

    return (
        <CashModal
            aberto={aberto}
            icone="delete_outline"
            titulo="Excluir afiliado"
            descricao="O afiliado vai para a lixeira e pode ser restaurado."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <DangerButton type="button" disabled={salvando || !senha} onClick={excluir}>{salvando ? 'Excluindo…' : 'Excluir'}</DangerButton>
                </>
            )}
        >
            {afiliado && (
                <>
                    <Hint>{`${afiliado.nome} (${afiliado.codigo})`}</Hint>
                    {tem_valor && (
                        <Warning>
                            <i className="material-icons" aria-hidden="true">warning</i>
                            {Number(a_receber) > 0
                                ? `Este afiliado tem ${formatar_valor(a_receber)} a receber. O valor continua na lixeira e pode ser prestado depois de restaurar.`
                                : `Este afiliado tem saldo negativo de ${formatar_valor(a_receber)}.`}
                        </Warning>
                    )}
                    <Hint>Ele perde o acesso na hora e o código deixa de ligar novos clientes; os clientes já ligados continuam.</Hint>
                    <Field>
                        <Label htmlFor="excluir_afiliado_senha">Sua senha</Label>
                        <Input id="excluir_afiliado_senha" type="password" autoComplete="current-password" value={senha} $erro={Boolean(erros.password)} onChange={(evento) => definir_senha(evento.target.value)} />
                        {erros.password && <Error role="alert">{erros.password}</Error>}
                    </Field>
                </>
            )}
        </CashModal>
    );
}
