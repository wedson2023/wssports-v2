import { useEffect, useState } from 'react';

import CashModal, { Button, Error, Field, Fields, GhostButton, Label } from '../CashModal';
import DecimalField from '../DecimalField';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { erros_por_campo } from '../../utils/forms';
import { Group, Title } from './styles';

// limite de valor apostado do jogo (spec 023, FR-013; antes um campo na linha do antigo): pré-jogo e,
// quando o jogo está ao vivo, o do ao vivo, gravados pelas rotas de limite da spec 003; erros do
// servidor no campo. ao_salvo(limites) recebe os valores gravados
export default function MatchLimitModal({ jogo, token, ao_fechar, ao_salvo }) {
    const aberto = Boolean(jogo);
    const [dados, definir_dados] = useState({ pre_jogo: null, ao_vivo: null });
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_erros({});
        definir_dados({ pre_jogo: jogo.limite_valor_apostado, ao_vivo: jogo.ao_vivo?.limite_valor_apostado ?? null });
    }, [jogo?.id]);

    const salvar = async () => {
        const cabecalhos = { headers: { Authorization: `Bearer ${token}` } };

        definir_salvando(true);
        definir_erros({});

        try {
            if (dados.pre_jogo !== jogo.limite_valor_apostado) {
                await api.patch(`/confrontos/${jogo.id}/limite`, { limite_valor_apostado: dados.pre_jogo }, cabecalhos);
            }

            if (jogo.ao_vivo && dados.ao_vivo !== jogo.ao_vivo.limite_valor_apostado) {
                try {
                    await api.patch(`/confrontos-ao-vivo/${jogo.ao_vivo.id}/limite`, { limite_valor_apostado: dados.ao_vivo }, cabecalhos);
                } catch (falha) {
                    // o erro do ao vivo vai para o campo dele
                    falha.campo_ao_vivo = true;
                    throw falha;
                }
            }

            ao_salvo(dados);
        } catch (falha) {
            if (falha.response?.status === 422) {
                const mensagem = erros_por_campo(falha).limite_valor_apostado;
                definir_erros(falha.campo_ao_vivo ? { ao_vivo: mensagem } : { pre_jogo: mensagem });
            } else {
                alerta_erro(falha);
            }
        } finally {
            definir_salvando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="price_check"
            titulo="Alterar limite"
            descricao={jogo ? `${jogo.time_casa} x ${jogo.time_fora}: valor máximo apostado no jogo.` : ''}
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando || !dados.pre_jogo} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            {jogo && (
                <Group>
                    <Title>Limites</Title>
                    <Fields>
                        <Field>
                            <Label htmlFor="limite_pre_jogo">Pré-jogo</Label>
                            <DecimalField id="limite_pre_jogo" tipo="valor" valor={dados.pre_jogo} erro={Boolean(erros.pre_jogo)} ao_mudar={(valor) => definir_dados((atual) => ({ ...atual, pre_jogo: valor }))} />
                            {erros.pre_jogo && <Error role="alert">{erros.pre_jogo}</Error>}
                        </Field>
                        {jogo.ao_vivo && (
                            <Field>
                                <Label htmlFor="limite_ao_vivo">Ao vivo</Label>
                                <DecimalField id="limite_ao_vivo" tipo="valor" valor={dados.ao_vivo} erro={Boolean(erros.ao_vivo)} ao_mudar={(valor) => definir_dados((atual) => ({ ...atual, ao_vivo: valor }))} />
                                {erros.ao_vivo && <Error role="alert">{erros.ao_vivo}</Error>}
                            </Field>
                        )}
                    </Fields>
                </Group>
            )}
        </CashModal>
    );
}
