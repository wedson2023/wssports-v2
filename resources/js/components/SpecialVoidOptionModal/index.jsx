import { useEffect, useState } from 'react';

import CashModal, { Button, Error, GhostButton, Summary, SummaryRow } from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { formatar_valor } from '../../utils/charts';
import { erros_por_campo } from '../../utils/forms';
import { Text } from './styles';

// anular uma opção do especial sem encerrar a categoria (spec 024, FR-018 a FR-020,
// contracts/paginas §3.3): mostra a opção, a cotação e as apostas em aberto nela (da exposição);
// os palpites nela são devolvidos como cotação 1,00. anulacao = { especial, opcao }
export default function SpecialVoidOptionModal({ anulacao, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(anulacao);
    const [erro, definir_erro] = useState(null);
    const [enviando, definir_enviando] = useState(false);

    useEffect(() => {
        if (aberto) definir_erro(null);
    }, [anulacao?.opcao?.id, aberto]);

    const anular = async () => {
        definir_enviando(true);
        definir_erro(null);

        try {
            const { especial, opcao } = anulacao;
            const { data } = await api.post(`/especiais/${especial.id}/opcoes/${opcao.id}/anular`, {}, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir(data);
        } catch (falha) {
            const erros = erros_por_campo(falha);

            if (falha.response?.status === 422) definir_erro(erros.opcao ?? erros.especial ?? Object.values(erros)[0] ?? null);
            else alerta_erro(falha);
        } finally {
            definir_enviando(false);
        }
    };

    const opcao = anulacao?.opcao;

    return (
        <CashModal
            aberto={aberto}
            icone="block"
            titulo="Anular opção"
            descricao="Os palpites nesta opção serão devolvidos."
            ao_fechar={enviando ? () => {} : ao_fechar}
            largura={460}
            rodape={(
                <>
                    <GhostButton type="button" disabled={enviando} onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" $tom="#6d4c41" disabled={enviando} onClick={anular}>{enviando ? 'Anulando…' : 'Anular'}</Button>
                </>
            )}
        >
            {opcao && (
                <>
                    <Summary>
                        <SummaryRow><dt>Opção</dt><dd>{opcao.nome}</dd></SummaryRow>
                        <SummaryRow><dt>Cotação</dt><dd>{Number(opcao.cotacao).toFixed(2).replace('.', ',')}</dd></SummaryRow>
                        <SummaryRow><dt>Apostas em aberto</dt><dd>{opcao.total.quantidade}</dd></SummaryRow>
                        <SummaryRow><dt>Valor apostado</dt><dd>{formatar_valor(opcao.total.valor)}</dd></SummaryRow>
                    </Summary>
                    <Text>
                        Cada palpite nesta opção é cancelado como cotação 1,00 e o prêmio da aposta é recalculado; a aposta simples volta com o valor apostado. A opção sai do site e não pode ser reativada.
                    </Text>
                    {erro && <Error role="alert">{erro}</Error>}
                </>
            )}
        </CashModal>
    );
}
