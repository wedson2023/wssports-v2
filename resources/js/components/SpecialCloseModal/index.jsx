import { useEffect, useState } from 'react';

import CashModal, { Button, Error, Field, GhostButton, Hint, Input, Label, Warning } from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { erros_por_campo } from '../../utils/forms';
import { Check, Option, Options, Preview, Select } from './styles';

// cotação em texto da API ("2.00") → "2,00"
const cotacao_texto = (valor) => Number(valor).toFixed(2).replace('.', ',');

// mesma conta do servidor (R-06): cotação × vagas ÷ empatadas, truncada em centavos; só prévia
const cotacao_dividida = (cotacao, vagas, empatadas) => {
    const centavos = Math.round(Number(cotacao) * 100);

    return (Math.floor((centavos * vagas) / empatadas) / 100).toFixed(2);
};

const textos = {
    encerrar: {
        titulo: 'Encerrar especial',
        descricao: 'Marque as opções vencedoras para apurar as apostas.',
        botao: 'Encerrar',
        rota: 'encerrar',
    },
    corrigir: {
        titulo: 'Corrigir resultado',
        descricao: 'Troque as vencedoras: as apostas serão reapuradas.',
        botao: 'Corrigir',
        rota: 'corrigir-resultado',
    },
};

// encerrar um especial com uma ou mais vencedoras ou corrigir o resultado (spec 024, FR-013 a
// FR-017 e FR-030, contracts/paginas §3.2). Com mais marcadas que a quantidade de vencedoras, pede
// "Dividir por empate" e quais empataram na última vaga, com a prévia da cotação aplicada; na
// correção, também a senha. especial = carregado com as opções
export default function SpecialCloseModal({ modo, especial, token, ao_fechar, ao_concluir }) {
    const aberto = Boolean(especial && modo);
    const texto = textos[modo] ?? textos.encerrar;
    const [vencedoras, definir_vencedoras] = useState([]);
    const [empatadas, definir_empatadas] = useState([]);
    const [dividir, definir_dividir] = useState(false);
    const [senha, definir_senha] = useState('');
    const [erros, definir_erros] = useState({});
    const [enviando, definir_enviando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        const atuais = modo === 'corrigir' ? especial.opcoes.filter((opcao) => opcao.vencedora) : [];

        definir_vencedoras(atuais.map((opcao) => opcao.id));
        definir_empatadas(atuais.filter((opcao) => opcao.empatada).map((opcao) => opcao.id));
        definir_dividir(modo === 'corrigir' && especial.dividir_empate);
        definir_senha('');
        definir_erros({});
    }, [especial?.id, modo, aberto]);

    if (!aberto) return <CashModal aberto={false} icone="flag" titulo={texto.titulo} ao_fechar={ao_fechar} />;

    const quantidade = especial.quantidade_vencedoras;
    const marcadas = vencedoras.length;
    const com_empate = marcadas > quantidade;
    const vagas = quantidade - (marcadas - empatadas.length);
    const data_futura = modo === 'encerrar' && new Date(especial.data_limite) > new Date();

    const alternar = (lista, definir, id) => definir(lista.includes(id) ? lista.filter((item) => item !== id) : [...lista, id]);

    const marcar_vencedora = (id) => {
        alternar(vencedoras, definir_vencedoras, id);

        // a que deixa de vencer não fica empatada
        if (vencedoras.includes(id)) definir_empatadas((atuais) => atuais.filter((item) => item !== id));
    };

    const enviar = async () => {
        definir_enviando(true);
        definir_erros({});

        const usar_divisao = com_empate && dividir;
        const corpo = {
            vencedoras,
            empatadas: usar_divisao ? empatadas : [],
            dividir_empate: usar_divisao,
            ...(modo === 'corrigir' ? { password: senha } : {}),
        };

        try {
            const { data } = await api.post(`/especiais/${especial.id}/${texto.rota}`, corpo, { headers: { Authorization: `Bearer ${token}` } });
            ao_concluir(data);
        } catch (falha) {
            if (falha.response?.status === 422) definir_erros(erros_por_campo(falha));
            else alerta_erro(falha);
        } finally {
            definir_enviando(false);
        }
    };

    // erro de uma opção (vencedoras.N) aparece junto do erro da lista
    const erro_vencedoras = erros.vencedoras ?? Object.entries(erros).find(([campo]) => campo.startsWith('vencedoras.'))?.[1] ?? null;
    const erro_categoria = erros.especial ?? null;

    return (
        <CashModal
            aberto
            icone="flag"
            titulo={texto.titulo}
            descricao={texto.descricao}
            ao_fechar={enviando ? () => {} : ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" disabled={enviando} onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={enviando || !marcadas || (modo === 'corrigir' && !senha)} onClick={enviar}>
                        {enviando ? 'Enviando…' : texto.botao}
                    </Button>
                </>
            )}
        >
            <Hint>
                {quantidade === 1 ? 'Escolha 1 opção vencedora.' : `Escolha ${quantidade} opções vencedoras.`}
                {modo === 'encerrar' && ` ${(especial.apostas_em_aberto ?? 0).toLocaleString('pt-BR')} apostas em aberto serão apuradas.`}
            </Hint>

            <Options role="group" aria-label="Opções vencedoras">
                {especial.opcoes.map((opcao) => {
                    const anulada = opcao.situacao === 'Anulada';
                    const vencedora = vencedoras.includes(opcao.id);
                    const empatada = empatadas.includes(opcao.id);

                    return (
                        <Option key={opcao.id} $marcada={vencedora} $anulada={anulada}>
                            <Check>
                                <input type="checkbox" checked={vencedora} disabled={anulada} onChange={() => marcar_vencedora(opcao.id)} />
                                <span>{opcao.nome}</span>
                                <small>{anulada ? 'Anulada' : cotacao_texto(opcao.cotacao)}</small>
                            </Check>
                            {com_empate && dividir && vencedora && (
                                <Check $secundario>
                                    <input type="checkbox" checked={empatada} onChange={() => alternar(empatadas, definir_empatadas, opcao.id)} />
                                    <span>Empatada</span>
                                    {empatada && vagas > 0 && empatadas.length > vagas && (
                                        <Preview>{`Cotação aplicada: ${cotacao_texto(cotacao_dividida(opcao.cotacao, vagas, empatadas.length))}`}</Preview>
                                    )}
                                </Check>
                            )}
                        </Option>
                    );
                })}
            </Options>
            {erro_vencedoras && <Error role="alert">{erro_vencedoras}</Error>}
            {erros.empatadas && <Error role="alert">{erros.empatadas}</Error>}

            {com_empate && (
                <Field>
                    <Label htmlFor="especial_dividir_empate">Dividir por empate</Label>
                    <Select id="especial_dividir_empate" value={dividir ? '1' : '0'} $erro={Boolean(erros.dividir_empate)} onChange={(evento) => definir_dividir(evento.target.value === '1')}>
                        <option value="0">Não</option>
                        <option value="1">Sim</option>
                    </Select>
                    <Hint>Marque as vencedoras empatadas na última vaga: a cotação delas é dividida.</Hint>
                    {erros.dividir_empate && <Error role="alert">{erros.dividir_empate}</Error>}
                </Field>
            )}

            {data_futura && (
                <Warning $tom="#ef6c00">
                    <i className="material-icons" aria-hidden="true">schedule</i>
                    A data limite ainda não passou: a categoria deixa de aceitar palpites ao encerrar.
                </Warning>
            )}

            {modo === 'corrigir' && (
                <>
                    <Warning $tom="#e53935">
                        <i className="material-icons" aria-hidden="true">warning</i>
                        Prêmios já pagos a mais serão estornados; o saldo do cliente pode ficar negativo.
                    </Warning>
                    <Field>
                        <Label htmlFor="especial_senha">Senha</Label>
                        <Input id="especial_senha" type="password" autoComplete="current-password" value={senha} $erro={Boolean(erros.password)} onChange={(evento) => definir_senha(evento.target.value)} />
                        {erros.password && <Error role="alert">{erros.password}</Error>}
                    </Field>
                </>
            )}

            {erro_categoria && <Error role="alert">{erro_categoria}</Error>}
        </CashModal>
    );
}
