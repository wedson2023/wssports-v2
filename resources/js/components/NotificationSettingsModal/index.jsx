import { useEffect, useState } from 'react';

import ModalCard, { ModalButton } from '../ModalCard';
import Spinner from '../Spinner';
import { Device, Option, Options } from './styles';

// tipos de aviso que o cliente liga ou desliga em cada aparelho (FR-041)
const tipos = [
    { chave: 'receber_gols', rotulo: 'Gols', descricao: 'Gol e gol anulado nos jogos das suas apostas' },
    { chave: 'receber_intervalo', rotulo: 'Intervalo', descricao: 'Placar no intervalo' },
    { chave: 'receber_fim_jogo', rotulo: 'Fim de jogo', descricao: 'Placar final' },
    { chave: 'receber_resultado_aposta', rotulo: 'Resultado da aposta', descricao: 'Quando a aposta é ganha ou perdida' },
    { chave: 'receber_encerramento_automatico', rotulo: 'Encerramento automático', descricao: 'Quando a aposta é encerrada pelo valor-alvo' },
];

// preferências das notificações deste aparelho e o botão de desativar (FR-041)
export default function NotificationSettingsModal({ aberto, preferencias, enviando, ao_salvar, ao_desativar, ao_fechar }) {
    const [valores, definir_valores] = useState(preferencias ?? {});

    useEffect(() => {
        if (aberto) definir_valores(preferencias ?? {});
    }, [aberto, preferencias]);

    const salvar = async () => {
        await ao_salvar(Object.fromEntries(tipos.map(({ chave }) => [chave, Boolean(valores[chave])])));
        ao_fechar();
    };

    const rodape = (
        <>
            <ModalButton type="button" $secundario onClick={ao_desativar} disabled={enviando}>Desativar neste aparelho</ModalButton>
            <ModalButton type="button" onClick={salvar} disabled={enviando}>{enviando ? <Spinner /> : 'Salvar'}</ModalButton>
        </>
    );

    return (
        <ModalCard aberto={aberto} titulo="Notificações" subtitulo="Escolha o que receber neste aparelho" ao_fechar={ao_fechar} rodape={rodape} largura={420}>
            {preferencias?.aparelho && <Device>{preferencias.aparelho}</Device>}
            <Options>
                {tipos.map(({ chave, rotulo, descricao }) => (
                    <Option key={chave}>
                        <span>
                            <strong>{rotulo}</strong>
                            <small>{descricao}</small>
                        </span>
                        <input
                            type="checkbox"
                            role="switch"
                            checked={Boolean(valores[chave])}
                            onChange={(evento) => definir_valores((atuais) => ({ ...atuais, [chave]: evento.target.checked }))}
                        />
                    </Option>
                ))}
            </Options>
        </ModalCard>
    );
}
