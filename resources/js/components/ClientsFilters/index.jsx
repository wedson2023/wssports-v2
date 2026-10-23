import { useEffect, useState } from 'react';

import AffiliatePicker from '../AffiliatePicker';
import CashModal, { Button, Error, Field, GhostButton, Input, Label, ler_valor, valor_para_campo } from '../CashModal';
import { chaves_filtros } from '../../hooks/useClientesPainel';
import useTelaMobile from '../../hooks/useTelaMobile';
import { Actions, Chip, Chips, ClearButton, Group, GroupTitle, Panel, Row, Select } from './styles';

const generos = ['Masculino', 'Feminino', 'Outro', 'Não informado'];

// filtros Sim/Não da API (1 ou 0)
const sim_nao = [
    { chave: 'com_saldo_promocional', rotulo: 'Com saldo promocional' },
    { chave: 'saque_bloqueado', rotulo: 'Saque bloqueado' },
    { chave: 'com_cpf', rotulo: 'Com CPF' },
    { chave: 'com_email', rotulo: 'Com e-mail' },
];

const vazio = { ...Object.fromEntries(chaves_filtros.map((chave) => [chave, null])), afiliado: null };

const dos_filtros = (filtros) => ({ ...Object.fromEntries(chaves_filtros.map((chave) => [chave, filtros[chave]])), afiliado: filtros.afiliado });

const formatar_data = (texto) => texto.split('-').reverse().join('/');

// etiquetas dos filtros ativos, para o ActiveFilters (chave = a removida ao tocar no x)
export const etiquetas_filtros = (filtros) => [
    filtros.ativo !== null && { chave: 'ativo', texto: filtros.ativo === '1' ? 'Ativos' : 'Inativos' },
    filtros.genero && { chave: 'genero', texto: filtros.genero },
    filtros.ddi && { chave: 'ddi', texto: `DDI +${filtros.ddi}` },
    filtros.afiliado && { chave: 'afiliado', texto: `Afiliado: ${filtros.afiliado.nome}` },
    filtros.cadastro_de && { chave: 'cadastro_de', texto: `Cadastro desde ${formatar_data(filtros.cadastro_de)}` },
    filtros.cadastro_ate && { chave: 'cadastro_ate', texto: `Cadastro até ${formatar_data(filtros.cadastro_ate)}` },
    filtros.idade_minima && { chave: 'idade_minima', texto: `Idade ≥ ${filtros.idade_minima}` },
    filtros.idade_maxima && { chave: 'idade_maxima', texto: `Idade ≤ ${filtros.idade_maxima}` },
    filtros.saldo_minimo && { chave: 'saldo_minimo', texto: `Saldo ≥ ${valor_para_campo(filtros.saldo_minimo)}` },
    filtros.saldo_maximo && { chave: 'saldo_maximo', texto: `Saldo ≤ ${valor_para_campo(filtros.saldo_maximo)}` },
    ...sim_nao.map(({ chave, rotulo }) => filtros[chave] !== null && { chave, texto: `${rotulo}: ${filtros[chave] === '1' ? 'sim' : 'não'}` }),
    filtros.deposito && { chave: 'deposito', texto: filtros.deposito === 'ja_depositou' ? 'Já depositou' : 'Nunca depositou' },
    filtros.pix_em_aberto && { chave: 'pix_em_aberto', texto: 'Gerou Pix e não pagou' },
    filtros.modalidade && { chave: 'modalidade', texto: filtros.modalidade === 'esportes' ? 'Jogou esportes no período' : 'Jogou cassino no período' },
    filtros.aniversariantes && { chave: 'aniversariantes', texto: 'Aniversariantes do período' },
    filtros.periodo_de && filtros.periodo_ate && { chave: 'periodo_de', texto: `Período ${formatar_data(filtros.periodo_de)} a ${formatar_data(filtros.periodo_ate)}` },
].filter(Boolean);

// remover uma etiqueta: o período leva junto os filtros que dependem dele
export const remover_filtro = (chave) => {
    if (chave === 'periodo_de') return { periodo_de: null, periodo_ate: null, modalidade: null, aniversariantes: null };

    return { [chave]: null };
};

// filtros da lista de clientes (spec 019, FR-008, contracts/paginas §3): os da API (situação, gênero,
// DDI, afiliado, cadastro, idade, saldo, Sim/Não) e os do modal antigo (depósito, Pix em aberto,
// modalidade e aniversariantes no período). Rascunho com Aplicar e Limpar; no computador, painel
// abaixo do cabeçalho; no celular, modal de tela cheia
export default function ClientsFilters({ aberto, filtros, token, ao_aplicar, ao_fechar }) {
    const e_mobile = useTelaMobile();
    const [rascunho, definir_rascunho] = useState(dos_filtros(filtros));
    const [saldos, definir_saldos] = useState({ minimo: '', maximo: '' });
    const [erro, definir_erro] = useState(null);

    // ao abrir, o rascunho parte dos filtros em vigor
    useEffect(() => {
        if (!aberto) return;

        definir_rascunho(dos_filtros(filtros));
        definir_saldos({ minimo: valor_para_campo(filtros.saldo_minimo ?? ''), maximo: valor_para_campo(filtros.saldo_maximo ?? '') });
        definir_erro(null);
    }, [aberto]);

    const alterar = (mudanca) => definir_rascunho((atual) => ({ ...atual, ...mudanca }));

    // chip que marca e desmarca o mesmo valor
    const alternar = (chave, valor) => alterar({ [chave]: rascunho[chave] === valor ? null : valor });

    const tem_periodo = Boolean(rascunho.periodo_de && rascunho.periodo_ate);

    const aplicar = () => {
        const ler = (texto) => (texto.trim() ? ler_valor(texto) : null);
        const saldo_minimo = ler(saldos.minimo);
        const saldo_maximo = ler(saldos.maximo);

        if ((saldos.minimo.trim() && saldo_minimo === null) || (saldos.maximo.trim() && saldo_maximo === null)) {
            definir_erro('Informe os saldos em reais, com até duas casas.');
            return;
        }

        if (Boolean(rascunho.periodo_de) !== Boolean(rascunho.periodo_ate) || (tem_periodo && rascunho.periodo_de > rascunho.periodo_ate)) {
            definir_erro('Informe as duas datas do período, com o início antes do fim.');
            return;
        }

        definir_erro(null);
        ao_aplicar({
            ...rascunho,
            saldo_minimo,
            saldo_maximo,
            // sem período, os filtros que dependem dele saem
            ...(tem_periodo ? {} : { modalidade: null, aniversariantes: null }),
        });
        ao_fechar();
    };

    const limpar = () => {
        definir_rascunho(vazio);
        definir_saldos({ minimo: '', maximo: '' });
        definir_erro(null);
    };

    const campos = (
        <>
            <Group>
                <GroupTitle>Situação</GroupTitle>
                <Chips role="group" aria-label="Situação">
                    <Chip type="button" aria-pressed={rascunho.ativo === '1'} onClick={() => alternar('ativo', '1')}>Ativos</Chip>
                    <Chip type="button" aria-pressed={rascunho.ativo === '0'} onClick={() => alternar('ativo', '0')}>Inativos</Chip>
                </Chips>
            </Group>
            <Group>
                <GroupTitle>Depósito</GroupTitle>
                <Chips role="group" aria-label="Depósito">
                    <Chip type="button" aria-pressed={rascunho.deposito === 'ja_depositou'} onClick={() => alternar('deposito', 'ja_depositou')}>Já depositou</Chip>
                    <Chip type="button" aria-pressed={rascunho.deposito === 'nunca_depositou'} onClick={() => alternar('deposito', 'nunca_depositou')}>Nunca depositou</Chip>
                    <Chip type="button" aria-pressed={rascunho.pix_em_aberto === '1'} onClick={() => alternar('pix_em_aberto', '1')}>Gerou Pix e não pagou</Chip>
                </Chips>
            </Group>
            <Field>
                <Label htmlFor="filtro_afiliado">Afiliado</Label>
                <AffiliatePicker id="filtro_afiliado" valor={rascunho.afiliado} token={token} ao_escolher={(afiliado) => alterar({ afiliado })} />
            </Field>
            <Field>
                <Label htmlFor="filtro_genero">Gênero</Label>
                <Select id="filtro_genero" value={rascunho.genero ?? ''} onChange={(evento) => alterar({ genero: evento.target.value || null })}>
                    <option value="">Todos</option>
                    {generos.map((genero) => <option key={genero} value={genero}>{genero}</option>)}
                </Select>
            </Field>
            <Field>
                <Label>Cadastro de / até</Label>
                <Row>
                    <Input type="date" aria-label="Cadastro de" value={rascunho.cadastro_de ?? ''} onChange={(evento) => alterar({ cadastro_de: evento.target.value || null })} />
                    <Input type="date" aria-label="Cadastro até" value={rascunho.cadastro_ate ?? ''} onChange={(evento) => alterar({ cadastro_ate: evento.target.value || null })} />
                </Row>
            </Field>
            <Field>
                <Label>Idade mínima / máxima</Label>
                <Row>
                    <Input type="number" min="0" inputMode="numeric" aria-label="Idade mínima" value={rascunho.idade_minima ?? ''} onChange={(evento) => alterar({ idade_minima: evento.target.value || null })} />
                    <Input type="number" min="0" inputMode="numeric" aria-label="Idade máxima" value={rascunho.idade_maxima ?? ''} onChange={(evento) => alterar({ idade_maxima: evento.target.value || null })} />
                </Row>
            </Field>
            <Field>
                <Label>Saldo mínimo / máximo (R$)</Label>
                <Row>
                    <Input inputMode="decimal" placeholder="0,00" aria-label="Saldo mínimo" value={saldos.minimo} onChange={(evento) => definir_saldos((atual) => ({ ...atual, minimo: evento.target.value }))} />
                    <Input inputMode="decimal" placeholder="0,00" aria-label="Saldo máximo" value={saldos.maximo} onChange={(evento) => definir_saldos((atual) => ({ ...atual, maximo: evento.target.value }))} />
                </Row>
            </Field>
            <Field>
                <Label htmlFor="filtro_ddi">DDI</Label>
                <Input id="filtro_ddi" inputMode="numeric" maxLength={3} placeholder="55" value={rascunho.ddi ?? ''} onChange={(evento) => alterar({ ddi: evento.target.value.replace(/\D/g, '') || null })} />
            </Field>
            {sim_nao.map(({ chave, rotulo }) => (
                <Group key={chave}>
                    <GroupTitle>{rotulo}</GroupTitle>
                    <Chips role="group" aria-label={rotulo}>
                        <Chip type="button" aria-pressed={rascunho[chave] === '1'} onClick={() => alternar(chave, '1')}>Sim</Chip>
                        <Chip type="button" aria-pressed={rascunho[chave] === '0'} onClick={() => alternar(chave, '0')}>Não</Chip>
                    </Chips>
                </Group>
            ))}
            <Field>
                <Label>Período do filtro (de / até)</Label>
                <Row>
                    <Input type="date" aria-label="Período de" value={rascunho.periodo_de ?? ''} onChange={(evento) => alterar({ periodo_de: evento.target.value || null })} />
                    <Input type="date" aria-label="Período até" value={rascunho.periodo_ate ?? ''} onChange={(evento) => alterar({ periodo_ate: evento.target.value || null })} />
                </Row>
            </Field>
            <Group title={tem_periodo ? '' : 'Escolha o período'}>
                <GroupTitle>{tem_periodo ? 'No período' : 'No período (escolha o período)'}</GroupTitle>
                <Chips role="group" aria-label="No período">
                    <Chip type="button" disabled={!tem_periodo} aria-pressed={rascunho.modalidade === 'esportes'} onClick={() => alternar('modalidade', 'esportes')}>Jogou esportes</Chip>
                    <Chip type="button" disabled={!tem_periodo} aria-pressed={rascunho.modalidade === 'cassino'} onClick={() => alternar('modalidade', 'cassino')}>Jogou cassino</Chip>
                    <Chip type="button" disabled={!tem_periodo} aria-pressed={rascunho.aniversariantes === '1'} onClick={() => alternar('aniversariantes', '1')}>Aniversariantes</Chip>
                </Chips>
            </Group>
            {erro && <Error role="alert">{erro}</Error>}
        </>
    );

    if (e_mobile) {
        return (
            <CashModal
                aberto={aberto}
                icone="filter_alt"
                titulo="Filtros"
                descricao="Refine a lista de clientes."
                ao_fechar={ao_fechar}
                altura_fixa
                rodape={(
                    <>
                        <GhostButton type="button" onClick={limpar}>Limpar</GhostButton>
                        <Button type="button" onClick={aplicar}>Aplicar</Button>
                    </>
                )}
            >
                {campos}
            </CashModal>
        );
    }

    if (!aberto) return null;

    return (
        <Panel aria-label="Filtros">
            {campos}
            <Actions>
                <ClearButton type="button" onClick={limpar}>
                    <i className="material-icons" aria-hidden="true">filter_alt_off</i>
                    Limpar filtros
                </ClearButton>
                <Button type="button" onClick={aplicar}>Aplicar</Button>
            </Actions>
        </Panel>
    );
}
