import { useEffect, useState } from 'react';

import CashModal, {
    Button, erro_do_campo, Error, Field, GhostButton, Hint, Input, Label, ler_valor, valor_para_campo,
} from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Suffix, Wrapper } from './styles';

// percentual da comissão do gerente (spec 017, FR-027): de 0 a 100, sobre o líquido dos vendedores
// dele no período; zero quando não houver lucro
export default function ManagerCommissionModal({ gerente, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(gerente);
    const [percentual, definir_percentual] = useState('');
    const [erro, definir_erro] = useState(null);
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        definir_percentual(valor_para_campo(gerente?.percentual_comissao_gerente ?? '0.00'));
        definir_erro(null);
    }, [gerente?.id]);

    const salvar = async () => {
        const valor = ler_valor(percentual);

        if (valor === null || Number(valor) > 100) {
            definir_erro('O percentual deve estar entre 0 e 100, com até duas casas.');
            return;
        }

        definir_salvando(true);

        try {
            await api.put(`/caixa/gerentes/${gerente.id}/comissao`, { percentual: valor }, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir(`Comissão de ${gerente.nome} atualizada.`);
        } catch (falha) {
            const mensagem = erro_do_campo(falha, 'percentual');

            if (mensagem) definir_erro(mensagem);
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="pie_chart"
            titulo={gerente ? `Comissão do gerente · ${gerente.nome}` : 'Comissão do gerente'}
            descricao="Percentual do gerente sobre o líquido dos vendedores dele."
            ao_fechar={ao_fechar}
            largura={440}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            <Field>
                <Label htmlFor="comissao_percentual">Percentual</Label>
                <Wrapper>
                    <Input id="comissao_percentual" inputMode="decimal" value={percentual} $erro={Boolean(erro)} onChange={(evento) => definir_percentual(evento.target.value)} />
                    <Suffix>%</Suffix>
                </Wrapper>
                {erro && <Error>{erro}</Error>}
            </Field>
            <Hint>
                Calculada sobre o líquido dos vendedores dele (entradas − saídas − comissão do vendedor) no período
                consultado inteiro; é zero quando esse líquido for negativo ou zero. Não entra na prestação de contas
                dos vendedores.
            </Hint>
        </CashModal>
    );
}
