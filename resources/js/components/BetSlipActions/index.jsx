import { usePage } from '@inertiajs/react';

import Spinner from '../Spinner';
import useCupom from '../../hooks/useCupom';
import { confirmar } from '../../utils/alerts';
import { ClearButton, ClearText, Count, FinishButton, FinishText, Icon, Row, SpinnerArea } from './styles';

// "Limpar" (com confirmação) e "Finalizar" com a quantidade de palpites (FR-038, FR-042); com o
// código do visitante aberto pelo vendedor, o botão fica verde e vira "Validar" (sistema antigo)
export default function BetSlipActions({ ao_finalizar }) {
    const { tema } = usePage().props;
    const { cupom, enviando, codigo_validacao, limpar } = useCupom();
    const validando = codigo_validacao !== null;

    const limpar_cupom = async () => {
        if (!cupom.palpites.length) return;

        if (await confirmar('Tem certeza que deseja limpar sua aposta?', tema.temas)) limpar();
    };

    return (
        <Row>
            <ClearButton onClick={limpar_cupom} title="Clique para limpar sua aposta.">
                <Icon>clear_all</Icon>
                <ClearText>Limpar</ClearText>
            </ClearButton>
            <FinishButton
                type="button"
                $largura="50%"
                $validar={validando}
                onClick={ao_finalizar}
                disabled={enviando}
                title={validando ? 'Clique para validar o código.' : 'Clique para finalizar sua aposta.'}
            >
                {enviando ? (
                    <SpinnerArea>
                        <Spinner />
                    </SpinnerArea>
                ) : (
                    <>
                        <FinishText>{validando ? 'Validar' : 'Finalizar'}</FinishText>
                        <Count>{cupom.palpites.length}</Count>
                    </>
                )}
            </FinishButton>
        </Row>
    );
}
