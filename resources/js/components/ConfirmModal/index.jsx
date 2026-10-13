import { useState } from 'react';

import ModalCard, { ModalButton } from '../ModalCard';
import Spinner from '../Spinner';
import { Icon, Message } from './styles';

// confirmação no visual dos modais novos (converter e excluir bônus, sair, motivo de recusa);
// sem ao_confirmar, mostra só o botão "Fechar"
export default function ConfirmModal({
    aberto,
    titulo,
    mensagem,
    icone = 'help_outline',
    rotulo_confirmar = 'Confirmar',
    ao_confirmar = null,
    ao_fechar,
}) {
    const [enviando, definir_enviando] = useState(false);

    const confirmar = async () => {
        definir_enviando(true);

        try {
            await ao_confirmar();
        } finally {
            definir_enviando(false);
        }
    };

    const rodape = ao_confirmar ? (
        <>
            <ModalButton type="button" $secundario onClick={ao_fechar} disabled={enviando}>Cancelar</ModalButton>
            <ModalButton type="button" onClick={confirmar} disabled={enviando}>
                {enviando ? <Spinner /> : rotulo_confirmar}
            </ModalButton>
        </>
    ) : (
        <ModalButton type="button" onClick={ao_fechar}>Fechar</ModalButton>
    );

    return (
        <ModalCard aberto={aberto} titulo={titulo} ao_fechar={enviando ? () => {} : ao_fechar} rodape={rodape} largura={420}>
            <Icon className="material-icons" aria-hidden="true">{icone}</Icon>
            <Message>{mensagem}</Message>
        </ModalCard>
    );
}
