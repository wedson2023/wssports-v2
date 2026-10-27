import { useEffect, useState } from 'react';

import CashModal, { Button, Error, Field, Fields, GhostButton, Input, Label } from '../CashModal';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { erros_por_campo } from '../../utils/forms';
import { Group, Select, Title } from './styles';

// escolha do país que abre o campo de texto
const OUTRO_PAIS = '__outro__';

// cadastro e edição de campeonato manual (spec 023, FR-029 e FR-031), sobre as rotas da spec 003:
// nome, país (da lista dos já usados, ou outro digitado) e bandeira opcional; o nome repetido entre
// os manuais é recusado pelo servidor no campo. campeonato = {} para cadastrar
export default function ChampionshipFormModal({ campeonato, token, ao_fechar, ao_salvar }) {
    const aberto = Boolean(campeonato);
    const edicao = Boolean(campeonato?.id);
    const [dados, definir_dados] = useState({ nome: '', pais: '', bandeira: '' });
    const [outro_pais, definir_outro_pais] = useState(false);
    const [paises, definir_paises] = useState([]);
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    const cabecalhos = { headers: { Authorization: `Bearer ${token}` } };

    useEffect(() => {
        if (!aberto) return;

        definir_erros({});
        definir_outro_pais(false);
        definir_dados({ nome: campeonato.nome ?? '', pais: campeonato.pais ?? '', bandeira: campeonato.bandeira ?? '' });
    }, [campeonato?.id, aberto]);

    useEffect(() => {
        if (!aberto || paises.length) return undefined;

        let ativo = true;
        api.get('/campeonatos/paises', cabecalhos)
            .then(({ data }) => ativo && definir_paises(data.data))
            .catch(() => ativo && definir_paises([]));

        return () => {
            ativo = false;
        };
    }, [aberto]);

    const alterar = (mudanca) => definir_dados((atual) => ({ ...atual, ...mudanca }));

    const salvar = async () => {
        definir_salvando(true);
        definir_erros({});

        const corpo = { nome: dados.nome.trim(), pais: dados.pais.trim(), bandeira: dados.bandeira.trim() || null };

        try {
            if (edicao) await api.put(`/campeonatos/${campeonato.id}`, corpo, cabecalhos);
            else await api.post('/campeonatos', corpo, cabecalhos);

            ao_salvar(edicao ? 'Campeonato alterado.' : 'Campeonato cadastrado.');
        } catch (falha) {
            if (falha.response?.status === 422) definir_erros(erros_por_campo(falha));
            else alerta_erro(falha);
        } finally {
            definir_salvando(false);
        }
    };

    const valor_pais = outro_pais || (dados.pais && !paises.includes(dados.pais) && paises.length) ? OUTRO_PAIS : dados.pais;

    return (
        <CashModal
            aberto={aberto}
            icone="emoji_events"
            titulo={edicao ? 'Editar campeonato' : 'Cadastrar campeonato'}
            descricao="Campeonato manual, para jogos que o provedor não traz."
            ao_fechar={ao_fechar}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando || !dados.nome.trim() || !dados.pais.trim()} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            {aberto && (
                <Group>
                    <Title>Campeonato</Title>
                    <Fields>
                        <Field $inteiro>
                            <Label htmlFor="campeonato_nome">Nome</Label>
                            <Input id="campeonato_nome" maxLength={150} value={dados.nome} $erro={Boolean(erros.nome)} onChange={(evento) => alterar({ nome: evento.target.value })} />
                            {erros.nome && <Error role="alert">{erros.nome}</Error>}
                        </Field>
                        <Field>
                            <Label htmlFor="campeonato_pais">País</Label>
                            <Select
                                id="campeonato_pais"
                                value={valor_pais}
                                $erro={Boolean(erros.pais)}
                                onChange={(evento) => {
                                    const escolhido = evento.target.value;
                                    definir_outro_pais(escolhido === OUTRO_PAIS);
                                    alterar({ pais: escolhido === OUTRO_PAIS ? '' : escolhido });
                                }}
                            >
                                <option value="">Selecione</option>
                                {paises.map((pais) => <option key={pais} value={pais}>{pais}</option>)}
                                <option value={OUTRO_PAIS}>Outro país</option>
                            </Select>
                            {erros.pais && !(valor_pais === OUTRO_PAIS) && <Error role="alert">{erros.pais}</Error>}
                        </Field>
                        {valor_pais === OUTRO_PAIS && (
                            <Field>
                                <Label htmlFor="campeonato_outro_pais">Nome do país</Label>
                                <Input id="campeonato_outro_pais" maxLength={100} value={dados.pais} $erro={Boolean(erros.pais)} onChange={(evento) => alterar({ pais: evento.target.value })} />
                                {erros.pais && <Error role="alert">{erros.pais}</Error>}
                            </Field>
                        )}
                        <Field>
                            <Label htmlFor="campeonato_bandeira">Bandeira (opcional)</Label>
                            <Input id="campeonato_bandeira" maxLength={255} placeholder="br" value={dados.bandeira} $erro={Boolean(erros.bandeira)} onChange={(evento) => alterar({ bandeira: evento.target.value })} />
                            {erros.bandeira && <Error role="alert">{erros.bandeira}</Error>}
                        </Field>
                    </Fields>
                </Group>
            )}
        </CashModal>
    );
}
