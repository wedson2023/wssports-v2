import { useEffect, useState } from 'react';

import { Button } from '../CashModal';
import Spinner from '../Spinner';
import { esportes } from '../SportsBar';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Chip, Chips, Error, Field, Footer, Grid, Group, GroupTitle, Input, Label, Loading, Select } from './styles';

// esportes que existem no site (barra de esportes), sem as abas que não são esporte
const esportes_existentes = esportes.filter(({ chave }) => !['CASSINO', 'AO VIVO', 'ESPECIAL'].includes(chave));

// compara nomes como o backend: sem diferenciar maiúsculas nem acentos
const normalizar = (nome) => String(nome).normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().trim();

// configurações do cliente (spec 002, ClientesConfiguracoes::CAMPOS) em grupos; tipo: booleano,
// dinheiro, numero, inteiro, periodo ou esportes
const grupos = [
    {
        titulo: 'Apostas',
        campos: [
            ['realizar_aposta', 'Realizar aposta', 'booleano'],
            ['cancelar_aposta', 'Cancelar aposta', 'booleano'],
            ['aceita_promocao', 'Aceita promoção', 'booleano'],
            ['valor_minimo_aposta', 'Valor mínimo', 'dinheiro'],
            ['valor_maximo_aposta', 'Valor máximo', 'dinheiro'],
            ['premio_maximo', 'Prêmio máximo', 'dinheiro'],
            ['valor_maximo_diario', 'Máximo apostado por dia', 'dinheiro'],
            ['odd_minima', 'Odd mínima', 'numero'],
            ['odd_maxima', 'Odd máxima', 'numero'],
            ['quantidade_minima_opcoes', 'Nº mínimo de jogos', 'inteiro'],
            ['quantidade_maxima_opcoes', 'Nº máximo de jogos', 'inteiro'],
            ['multiplicador', 'Multiplicador', 'inteiro'],
            ['ganho_multiplo_palpites', 'Ganho por múltiplos (%)', 'numero'],
        ],
    },
    {
        titulo: 'Ao vivo e encerramento',
        campos: [
            ['apostar_ao_vivo', 'Apostar ao vivo', 'booleano'],
            ['delay_ao_vivo', 'Delay (s)', 'inteiro'],
            ['encerrar_aposta', 'Encerrar aposta', 'booleano'],
        ],
    },
    {
        titulo: 'Saques',
        campos: [
            ['bloquear_saque', 'Bloquear saque', 'booleano'],
            ['valor_maximo_saque_diario', 'Máximo sacado por dia', 'dinheiro'],
            ['quantidade_maxima_saques_diaria', 'Saques por dia', 'inteiro'],
        ],
    },
    {
        titulo: 'Jogos',
        campos: [
            ['periodo_jogos', 'Período de jogos', 'periodo'],
            ['apostar_jogadores', 'Apostar em jogadores', 'booleano'],
            ['apostar_outros_esportes', 'Outros esportes', 'booleano'],
            ['esportes_permitidos', 'Esportes permitidos', 'esportes'],
        ],
    },
];

const periodos = ['Hoje', 'Amanhã', 'Depois de amanhã'];

// configurações de aposta e saque do cliente (spec 019, FR-013): mostra sempre; altera só com
// clientes.editar_configuracoes, pelas regras da spec 002 (esportes escolhidos da lista do site)
export default function ClientSettingsForm({ cliente, token, pode_editar, ao_salvar }) {
    const [valores, definir_valores] = useState(null);
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);
    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };

    useEffect(() => {
        let ativo = true;
        definir_valores(null);

        api.get(`/clientes/${cliente.id}/configuracoes`, autorizacao)
            .then(({ data }) => ativo && definir_valores(data.data))
            .catch((erro) => ativo && alerta_erro(erro));

        return () => {
            ativo = false;
        };
    }, [cliente.id]);

    if (!valores) return <Loading><Spinner tamanho={30} cor="currentColor" /></Loading>;

    const mudar = (chave, valor) => definir_valores((atual) => ({ ...atual, [chave]: valor }));

    const salvar = async () => {
        definir_salvando(true);
        definir_erros({});

        try {
            const { updated_at, ...corpo } = valores;
            const { data } = await api.put(`/clientes/${cliente.id}/configuracoes`, corpo, autorizacao);
            definir_valores(data.data);
            ao_salvar('Configurações salvas.');
        } catch (erro) {
            if (erro.response?.status === 422) definir_erros(Object.fromEntries(Object.entries(erro.response.data.errors ?? {}).map(([campo, [mensagem]]) => [campo.split('.')[0], mensagem])));
            else alerta_erro(erro);
        } finally {
            definir_salvando(false);
        }
    };

    const entrada = ([chave, , tipo]) => {
        const valor = valores[chave];
        const comum = { id: `config-${chave}`, disabled: !pode_editar };

        if (tipo === 'booleano') {
            return (
                <Select {...comum} value={String(Boolean(valor))} onChange={(evento) => mudar(chave, evento.target.value === 'true')}>
                    <option value="true">Sim</option>
                    <option value="false">Não</option>
                </Select>
            );
        }

        if (tipo === 'periodo') {
            return (
                <Select {...comum} value={valor ?? 'Hoje'} onChange={(evento) => mudar(chave, evento.target.value)}>
                    {periodos.map((periodo) => <option key={periodo} value={periodo}>{periodo}</option>)}
                </Select>
            );
        }

        if (tipo === 'esportes') {
            const marcados = valor ?? [];
            const marcado = (esporte) => marcados.some((item) => normalizar(item) === normalizar(esporte.chave));
            const alternar = (esporte) => mudar(chave, esportes_existentes
                .filter((item) => (item.chave === esporte.chave ? !marcado(item) : marcado(item)))
                .map((item) => item.chave));

            return (
                <Chips id={comum.id} role="group" aria-label="Esportes permitidos">
                    {(pode_editar ? esportes_existentes : esportes_existentes.filter(marcado)).map((esporte) => (
                        <Chip key={esporte.chave} type="button" aria-pressed={marcado(esporte)} disabled={!pode_editar} $selecionado={marcado(esporte)} onClick={() => alternar(esporte)}>
                            <i className="material-icons" aria-hidden="true">{marcado(esporte) ? 'check' : esporte.icone}</i>
                            {esporte.titulo}
                        </Chip>
                    ))}
                </Chips>
            );
        }

        return (
            <Input
                {...comum}
                type="number"
                inputMode={tipo === 'inteiro' ? 'numeric' : 'decimal'}
                step={tipo === 'inteiro' ? '1' : '0.01'}
                min="0"
                value={valor ?? ''}
                placeholder={tipo === 'dinheiro' ? 'R$' : ''}
                onChange={(evento) => mudar(chave, evento.target.value)}
            />
        );
    };

    return (
        <>
            {grupos.map((grupo) => (
                <Group key={grupo.titulo}>
                    <GroupTitle>{grupo.titulo}</GroupTitle>
                    <Grid>
                        {grupo.campos.map((definicao) => (
                            <Field key={definicao[0]} $largo={definicao[2] === 'esportes'}>
                                <Label htmlFor={`config-${definicao[0]}`}>{definicao[1]}</Label>
                                {entrada(definicao)}
                                {erros[definicao[0]] && <Error>{erros[definicao[0]]}</Error>}
                            </Field>
                        ))}
                    </Grid>
                </Group>
            ))}
            {pode_editar && (
                <Footer>
                    <Button type="button" disabled={salvando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar configurações'}</Button>
                </Footer>
            )}
        </>
    );
}
