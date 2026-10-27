import { useEffect, useState } from 'react';

import CashModal, { Button, Error, Field, Fields, GhostButton, Input, Label } from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { erros_por_campo, somente_digitos } from '../../utils/forms';
import { GoalRow, Group, Remove, SmallButton, Title } from './styles';

// seções de placar do modal (FR-023): campos casa e fora de cada uma, todos obrigatórios
const secoes = [
    { titulo: 'Placar final', casa: 'placar_casa', fora: 'placar_fora' },
    { titulo: '1º tempo', casa: 'gols_primeiro_tempo_casa', fora: 'gols_primeiro_tempo_fora' },
    { titulo: '2º tempo', casa: 'gols_segundo_tempo_casa', fora: 'gols_segundo_tempo_fora' },
    { titulo: 'Escanteios', casa: 'escanteios_casa', fora: 'escanteios_fora' },
];

const campos = secoes.flatMap((secao) => [secao.casa, secao.fora]);

const vazio = () => Object.fromEntries(campos.map((campo) => [campo, '']));

// resultado gravado (gaveta) → campos do modal
const do_resultado = (resultado) => Object.fromEntries(campos.map((campo) => [campo, resultado[campo] === null || resultado[campo] === undefined ? '' : String(resultado[campo])]));

// lançar ou corrigir o resultado do jogo (spec 023, FR-023; o antigo modal "placar"): placar
// final, 1º e 2º tempo e escanteios obrigatórios (a soma dos tempos tem de bater com o placar, regra
// do servidor) e os gols por jogador, opcionais. Só o Admin; vale para jogos do provedor e manuais.
// resultado = o atual (correção) ou null
export default function MatchResultModal({ jogo, resultado, token, ao_fechar, ao_salvo }) {
    const aberto = Boolean(jogo);
    const [dados, definir_dados] = useState(vazio);
    const [gols, definir_gols] = useState([]);
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_erros({});
        definir_dados(resultado ? do_resultado(resultado) : vazio());
        definir_gols((resultado?.gols ?? []).map((gol) => ({ nome: gol.nome, minuto: gol.minuto ?? '', tempo_jogo: gol.tempo_jogo ?? '' })));
    }, [jogo?.id, resultado]);

    const alterar = (campo, valor) => definir_dados((atual) => ({ ...atual, [campo]: somente_digitos(valor).slice(0, 2) }));

    const alterar_gol = (indice, mudanca) => definir_gols((atuais) => atuais.map((gol, posicao) => (posicao === indice ? { ...gol, ...mudanca } : gol)));

    const salvar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            await api.put(`/confrontos/${jogo.id}/resultado`, {
                ...Object.fromEntries(campos.map((campo) => [campo, dados[campo] === '' ? null : Number(dados[campo])])),
                // sem gols na lista, os gravados são apagados (lista vazia)
                gols: gols.map((gol, indice) => ({
                    ordem: indice + 1,
                    nome: gol.nome.trim(),
                    minuto: gol.minuto.trim() || null,
                    tempo_jogo: gol.tempo_jogo.trim() || null,
                })),
            }, { headers: { Authorization: `Bearer ${token}` } });
            ao_salvo(resultado ? 'Resultado corrigido.' : 'Resultado lançado.');
        } catch (falha) {
            if (falha.response?.status === 422) definir_erros(erros_por_campo(falha));
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    const completo = campos.every((campo) => dados[campo] !== '');
    const erro_gols = Object.entries(erros).find(([campo]) => campo.startsWith('gols'))?.[1];

    return (
        <CashModal
            aberto={aberto}
            icone="scoreboard"
            titulo={resultado ? 'Corrigir resultado' : 'Lançar resultado'}
            descricao={jogo ? `${jogo.time_casa} x ${jogo.time_fora}: as apostas do jogo são apuradas ao salvar.` : ''}
            ao_fechar={ao_fechar}
            largura={640}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando || !completo} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            {jogo && (
                <>
                    {erros.confronto && <Error role="alert">{erros.confronto}</Error>}
                    {secoes.map((secao) => (
                        <Group key={secao.titulo}>
                            <Title>{secao.titulo}</Title>
                            <Fields>
                                {[['casa', secao.casa, jogo.time_casa], ['fora', secao.fora, jogo.time_fora]].map(([lado, campo, time]) => (
                                    <Field key={lado}>
                                        <Label htmlFor={`resultado_${campo}`}>{time}</Label>
                                        <Input id={`resultado_${campo}`} inputMode="numeric" value={dados[campo]} $erro={Boolean(erros[campo])} onChange={(evento) => alterar(campo, evento.target.value)} />
                                        {erros[campo] && <Error role="alert">{erros[campo]}</Error>}
                                    </Field>
                                ))}
                            </Fields>
                        </Group>
                    ))}
                    <Group>
                        <Title>Gols (opcional)</Title>
                        {gols.map((gol, indice) => (
                            // eslint-disable-next-line react/no-array-index-key
                            <GoalRow key={indice}>
                                <Field>
                                    <Label htmlFor={`gol_nome_${indice}`}>{`${indice + 1}º gol · jogador`}</Label>
                                    <Input id={`gol_nome_${indice}`} value={gol.nome} maxLength={150} onChange={(evento) => alterar_gol(indice, { nome: evento.target.value })} />
                                </Field>
                                <Field>
                                    <Label htmlFor={`gol_minuto_${indice}`}>Minuto</Label>
                                    <Input id={`gol_minuto_${indice}`} value={gol.minuto} placeholder="45+1" maxLength={6} onChange={(evento) => alterar_gol(indice, { minuto: evento.target.value })} />
                                </Field>
                                <Field>
                                    <Label htmlFor={`gol_tempo_${indice}`}>Tempo</Label>
                                    <Input id={`gol_tempo_${indice}`} value={gol.tempo_jogo} placeholder="1T" maxLength={20} onChange={(evento) => alterar_gol(indice, { tempo_jogo: evento.target.value })} />
                                </Field>
                                <Remove type="button" title="Remover gol" aria-label={`Remover o ${indice + 1}º gol`} onClick={() => definir_gols((atuais) => atuais.filter((_, posicao) => posicao !== indice))}>
                                    <i className="material-icons" aria-hidden="true">delete_outline</i>
                                </Remove>
                            </GoalRow>
                        ))}
                        {erro_gols && <Error role="alert">{erro_gols}</Error>}
                        <SmallButton type="button" onClick={() => definir_gols((atuais) => [...atuais, { nome: '', minuto: '', tempo_jogo: '' }])}>
                            <i className="material-icons" aria-hidden="true">add</i>
                            Adicionar gol
                        </SmallButton>
                    </Group>
                </>
            )}
        </CashModal>
    );
}
