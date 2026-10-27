import { useEffect, useMemo, useState } from 'react';

import CashModal, { Button, Error, Field, Fields, GhostButton, Hint, Input, Label } from '../CashModal';
import ChampionshipPicker from '../ChampionshipPicker';
import { fuso_do_navegador } from '../../hooks/useConfrontosPainel';
import useNomesCotacoes from '../../hooks/useNomesCotacoes';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { erros_por_campo } from '../../utils/forms';
import { grupos_mercado } from '../../utils/quotes';
import { Group, Quote, Quotes, Remove, Select, Title } from './styles';

// Casa, Empate e Fora ficam sempre no modal; as demais entram por "Adicionar cotação"
const principais = [
    { codigo: 'odd1', rotulo: 'Casa' },
    { codigo: 'odd2', rotulo: 'Empate' },
    { codigo: 'odd3', rotulo: 'Fora' },
];

// situações que a spec 003 permite escolher num jogo manual
const situacoes = ['Aguardando', 'Adiado', 'Cancelado'];

const dois = (numero) => String(numero).padStart(2, '0');

// início do jogo (UTC da API) → valor do campo de data e hora no fuso do navegador
const para_campo = (texto) => {
    if (!texto) return '';

    const data = new Date(texto);

    return `${data.getFullYear()}-${dois(data.getMonth() + 1)}-${dois(data.getDate())}T${dois(data.getHours())}:${dois(data.getMinutes())}`;
};

const vazio = { campeonato: null, esporte: '', data_inicio: '', time_casa: '', escudo_casa: '', time_fora: '', escudo_fora: '', situacao: 'Aguardando' };

// cadastro e edição de jogo manual (spec 023, FR-030 e FR-031, R-15), sobre as rotas da spec 003:
// Jogo (campeonato manual da lista, esporte da lista, data e hora no fuso do navegador, só no
// futuro), Times e Cotações (Casa, Empate e Fora e outros códigos por mercado, cada um ≥ 1,00);
// na edição, também a situação. jogo = {} para cadastrar; campeonato_inicial no "Adicionar jogo"
export default function MatchFormModal({ jogo, campeonato_inicial = null, token, ao_fechar, ao_salvar }) {
    const aberto = Boolean(jogo);
    const edicao = Boolean(jogo?.id);
    const nomes = useNomesCotacoes();
    const [dados, definir_dados] = useState(vazio);
    const [cotacoes, definir_cotacoes] = useState({});
    const [esportes, definir_esportes] = useState([]);
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    const cabecalhos = { headers: { Authorization: `Bearer ${token}` } };

    useEffect(() => {
        if (!aberto) return;

        definir_erros({});
        definir_dados(edicao ? {
            campeonato: jogo.campeonato ? { id: jogo.campeonato.id, nome: jogo.campeonato.nome } : null,
            esporte: jogo.esporte ?? '',
            data_inicio: para_campo(jogo.data_inicio),
            time_casa: jogo.time_casa ?? '',
            escudo_casa: jogo.escudo_casa ?? '',
            time_fora: jogo.time_fora ?? '',
            escudo_fora: jogo.escudo_fora ?? '',
            situacao: jogo.situacao ?? 'Aguardando',
        } : { ...vazio, campeonato: campeonato_inicial });
        definir_cotacoes(Object.fromEntries(Object.entries(jogo.cotacoes ?? {}).map(([codigo, valor]) => [codigo, String(valor).replace('.', ',')])));
    }, [jogo?.id, aberto]);

    // esportes já usados pelos jogos, uma vez por abertura
    useEffect(() => {
        if (!aberto || esportes.length) return undefined;

        let ativo = true;
        api.get('/confrontos/esportes', cabecalhos)
            .then(({ data }) => ativo && definir_esportes(data.data))
            .catch(() => ativo && definir_esportes([]));

        return () => {
            ativo = false;
        };
    }, [aberto]);

    // códigos que ainda podem ser acrescentados, agrupados por mercado (sem os principais, os já
    // escolhidos e o de jogador, que não é cotação do jogo)
    const grupos = useMemo(() => {
        const usados = new Set([...principais.map((item) => item.codigo), ...Object.keys(cotacoes), 'jogador']);

        return grupos_mercado(Object.keys(nomes ?? {}))
            .map((grupo) => ({ ...grupo, codigos: grupo.codigos.filter((codigo) => !usados.has(codigo) && nomes?.[codigo]) }))
            .filter((grupo) => grupo.codigos.length);
    }, [nomes, cotacoes]);

    const alterar = (mudanca) => definir_dados((atual) => ({ ...atual, ...mudanca }));
    const alterar_cotacao = (codigo, valor) => definir_cotacoes((atuais) => ({ ...atuais, [codigo]: valor }));

    const remover_cotacao = (codigo) => definir_cotacoes((atuais) => {
        const { [codigo]: _removida, ...resto } = atuais;

        return resto;
    });

    const salvar = async () => {
        definir_salvando(true);
        definir_erros({});

        const corpo = {
            campeonatos_id: dados.campeonato?.id ?? null,
            esporte: dados.esporte,
            data_inicio: dados.data_inicio.replace('T', ' '),
            fuso_horario: fuso_do_navegador(),
            time_casa: dados.time_casa.trim(),
            escudo_casa: dados.escudo_casa.trim() || null,
            time_fora: dados.time_fora.trim(),
            escudo_fora: dados.escudo_fora.trim() || null,
            // só os códigos preenchidos; vírgula vira ponto
            cotacoes: Object.fromEntries(Object.entries(cotacoes).filter(([, valor]) => String(valor).trim() !== '').map(([codigo, valor]) => [codigo, String(valor).trim().replace(',', '.')])),
            ...(edicao ? { situacao: dados.situacao } : {}),
        };

        try {
            if (edicao) await api.put(`/confrontos/${jogo.id}`, corpo, cabecalhos);
            else await api.post('/confrontos', corpo, cabecalhos);

            ao_salvar(edicao ? 'Jogo alterado.' : 'Jogo cadastrado.');
        } catch (falha) {
            if (falha.response?.status === 422) definir_erros(erros_por_campo(falha));
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    const extras = Object.keys(cotacoes).filter((codigo) => !principais.some((item) => item.codigo === codigo));

    const campo_cotacao = (codigo, rotulo, removivel) => (
        <Quote key={codigo}>
            <Field>
                <Label htmlFor={`cotacao_${codigo}`}>{rotulo}</Label>
                <Input id={`cotacao_${codigo}`} inputMode="decimal" placeholder="1,00" value={cotacoes[codigo] ?? ''} onChange={(evento) => alterar_cotacao(codigo, evento.target.value)} />
            </Field>
            {removivel && (
                <Remove type="button" title="Remover cotação" aria-label={`Remover a cotação ${rotulo}`} onClick={() => remover_cotacao(codigo)}>
                    <i className="material-icons" aria-hidden="true">delete_outline</i>
                </Remove>
            )}
        </Quote>
    );

    return (
        <CashModal
            aberto={aberto}
            icone="sports_soccer"
            titulo={edicao ? 'Editar jogo' : 'Cadastrar jogo'}
            descricao="Jogo manual: aparece no site como os do provedor."
            ao_fechar={ao_fechar}
            largura={680}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            {aberto && (
                <>
                    <Group>
                        <Title>Jogo</Title>
                        <Fields>
                            <Field $inteiro>
                                <Label htmlFor="jogo_campeonato">Campeonato manual</Label>
                                <ChampionshipPicker id="jogo_campeonato" valor={dados.campeonato} token={token} so_manuais ao_escolher={(campeonato) => alterar({ campeonato })} />
                                {erros.campeonatos_id && <Error role="alert">{erros.campeonatos_id}</Error>}
                            </Field>
                            <Field>
                                <Label htmlFor="jogo_esporte">Esporte</Label>
                                <Select id="jogo_esporte" value={dados.esporte} $erro={Boolean(erros.esporte)} onChange={(evento) => alterar({ esporte: evento.target.value })}>
                                    <option value="">Selecione</option>
                                    {dados.esporte && !esportes.includes(dados.esporte) && <option value={dados.esporte}>{dados.esporte}</option>}
                                    {esportes.map((esporte) => <option key={esporte} value={esporte}>{esporte}</option>)}
                                </Select>
                                {erros.esporte && <Error role="alert">{erros.esporte}</Error>}
                            </Field>
                            <Field>
                                <Label htmlFor="jogo_inicio">Data e hora</Label>
                                <Input id="jogo_inicio" type="datetime-local" value={dados.data_inicio} $erro={Boolean(erros.data_inicio)} onChange={(evento) => alterar({ data_inicio: evento.target.value })} />
                                {erros.data_inicio && <Error role="alert">{erros.data_inicio}</Error>}
                            </Field>
                            {edicao && (
                                <Field>
                                    <Label htmlFor="jogo_situacao">Situação</Label>
                                    <Select id="jogo_situacao" value={dados.situacao} $erro={Boolean(erros.situacao)} onChange={(evento) => alterar({ situacao: evento.target.value })}>
                                        {situacoes.map((situacao) => <option key={situacao} value={situacao}>{situacao}</option>)}
                                    </Select>
                                    {erros.situacao && <Error role="alert">{erros.situacao}</Error>}
                                    <Hint>Cancelado devolve os palpites do jogo.</Hint>
                                </Field>
                            )}
                        </Fields>
                    </Group>

                    <Group>
                        <Title>Times</Title>
                        <Fields>
                            <Field>
                                <Label htmlFor="jogo_casa">Time da casa</Label>
                                <Input id="jogo_casa" maxLength={150} value={dados.time_casa} $erro={Boolean(erros.time_casa)} onChange={(evento) => alterar({ time_casa: evento.target.value })} />
                                {erros.time_casa && <Error role="alert">{erros.time_casa}</Error>}
                            </Field>
                            <Field>
                                <Label htmlFor="jogo_fora">Time de fora</Label>
                                <Input id="jogo_fora" maxLength={150} value={dados.time_fora} $erro={Boolean(erros.time_fora)} onChange={(evento) => alterar({ time_fora: evento.target.value })} />
                                {erros.time_fora && <Error role="alert">{erros.time_fora}</Error>}
                            </Field>
                            <Field>
                                <Label htmlFor="jogo_escudo_casa">Escudo da casa (opcional)</Label>
                                <Input id="jogo_escudo_casa" maxLength={255} value={dados.escudo_casa} $erro={Boolean(erros.escudo_casa)} onChange={(evento) => alterar({ escudo_casa: evento.target.value })} />
                                {erros.escudo_casa && <Error role="alert">{erros.escudo_casa}</Error>}
                            </Field>
                            <Field>
                                <Label htmlFor="jogo_escudo_fora">Escudo de fora (opcional)</Label>
                                <Input id="jogo_escudo_fora" maxLength={255} value={dados.escudo_fora} $erro={Boolean(erros.escudo_fora)} onChange={(evento) => alterar({ escudo_fora: evento.target.value })} />
                                {erros.escudo_fora && <Error role="alert">{erros.escudo_fora}</Error>}
                            </Field>
                        </Fields>
                    </Group>

                    <Group>
                        <Title>Cotações</Title>
                        <Quotes>
                            {principais.map((item) => campo_cotacao(item.codigo, item.rotulo, false))}
                            {extras.map((codigo) => campo_cotacao(codigo, nomes?.[codigo] ?? codigo, true))}
                        </Quotes>
                        {erros.cotacoes && <Error role="alert">{erros.cotacoes}</Error>}
                        <Field>
                            <Label htmlFor="jogo_adicionar_cotacao">Adicionar cotação</Label>
                            <Select id="jogo_adicionar_cotacao" value="" disabled={!grupos.length} onChange={(evento) => evento.target.value && alterar_cotacao(evento.target.value, '')}>
                                <option value="">{nomes ? 'Escolha um mercado e a opção' : 'Carregando…'}</option>
                                {grupos.map((grupo) => (
                                    <optgroup key={grupo.chave} label={`${grupo.aba} · ${grupo.titulo}`}>
                                        {grupo.codigos.map((codigo) => <option key={codigo} value={codigo}>{nomes[codigo]}</option>)}
                                    </optgroup>
                                ))}
                            </Select>
                            <Hint>Cada cotação deve ser maior ou igual a 1,00, com até 2 casas; deixe em branco as que o jogo não tem.</Hint>
                        </Field>
                    </Group>
                </>
            )}
        </CashModal>
    );
}
