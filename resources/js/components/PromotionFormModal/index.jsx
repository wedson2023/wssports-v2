import { useEffect, useState } from 'react';

import AffiliatePicker from '../AffiliatePicker';
import CashModal, {
    Button, erro_do_campo, Error, Field, Fields, GhostButton, Hint, Input, Label, ler_valor, Textarea, valor_para_campo, Warning,
} from '../CashModal';
import { categorias, modalidades } from '../../hooks/useBonusPainel';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Group, Select, Title } from './styles';

const de_deposito = (categoria) => ['Primeiro depósito', 'Qualquer depósito'].includes(categoria);

// campos que não mudam depois que a promoção foi dada a algum cliente
const travados_apos_aplicada = ['valor', 'tipo_ganho', 'categoria', 'modalidade'];

// valores em reais do formulário (texto do campo ↔ "10.50" da API)
const campos_valor = ['valor_maximo_deposito', 'valor_minimo_aposta', 'valor_maximo_aposta', 'valor_maximo_conversao'];

// data e hora da API (UTC) → "AAAA-MM-DDTHH:MM" de Brasília, para o campo datetime-local
const para_campo_data = (texto) => {
    if (!texto) return '';

    const partes = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
        timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date(texto)).map((parte) => [parte.type, parte.value]));

    return `${partes.year}-${partes.month}-${partes.day}T${partes.hour}:${partes.minute}`;
};

// campo datetime-local (Brasília) → ISO em UTC para a API
const para_api_data = (texto) => (texto ? new Date(`${texto}:00-03:00`).toISOString() : null);

const em_branco = {
    nome: '',
    descricao: '',
    ativa: true,
    categoria: 'Primeiro depósito',
    modalidade: 'Esportes',
    tipo_ganho: 'Fixo',
    valor: '',
    valor_maximo_deposito: '',
    quantidade_indicacoes: '',
    data_inicio: '',
    data_fim: '',
    rollover: '1',
    rollover_primeiro_deposito: false,
    dias_cumprir: '30',
    valor_minimo_aposta: '',
    valor_maximo_aposta: '',
    odd_minima_aposta_simples: '',
    odd_minima_aposta_multipla: '',
    valor_maximo_conversao: '',
    afiliado: null,
};

const do_servidor = (promocao) => ({
    ...em_branco,
    ...Object.fromEntries(Object.keys(em_branco).filter((chave) => promocao[chave] !== undefined).map((chave) => [chave, promocao[chave] ?? ''])),
    valor: promocao.tipo_ganho === 'Percentual' ? String(Number(promocao.valor)).replace('.', ',') : valor_para_campo(promocao.valor),
    ...Object.fromEntries(campos_valor.map((chave) => [chave, promocao[chave] ? valor_para_campo(promocao[chave]) : ''])),
    rollover: String(promocao.rollover ?? ''),
    dias_cumprir: String(promocao.dias_cumprir ?? ''),
    quantidade_indicacoes: promocao.quantidade_indicacoes ? String(promocao.quantidade_indicacoes) : '',
    odd_minima_aposta_simples: valor_para_campo(promocao.odd_minima_aposta_simples),
    odd_minima_aposta_multipla: valor_para_campo(promocao.odd_minima_aposta_multipla),
    data_inicio: para_campo_data(promocao.data_inicio),
    data_fim: para_campo_data(promocao.data_fim),
    afiliado: promocao.afiliado ? { id: promocao.afiliado.id, nome: promocao.afiliado.nome } : null,
});

// número com vírgula ou ponto ("1,50") → texto da API ("1.50"); vazio → null
const ler_numero = (texto) => {
    const limpo = String(texto ?? '').trim().replace(',', '.');

    return limpo === '' ? null : limpo;
};

// cadastrar e editar promoção (spec 020, FR-012 a FR-017, contracts/paginas §4; o antigo "Cadastrar
// bônus"): campos agrupados e escondidos quando não se aplicam; na promoção já aplicada, valor, tipo
// de ganho, categoria e modalidade ficam travados. promocao = null (fechado), {} (cadastro) ou a
// promoção (edição)
export default function PromotionFormModal({ promocao, token, e_admin, ao_fechar, ao_salvar }) {
    const aberto = Boolean(promocao);
    const edicao = Boolean(promocao?.id);
    const travado = edicao && promocao.aplicada;
    const [dados, definir_dados] = useState(em_branco);
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    useEffect(() => {
        if (!aberto) return;

        definir_dados(edicao ? do_servidor(promocao) : em_branco);
        definir_erros({});
    }, [promocao]);

    const alterar = (mudanca) => definir_dados((atual) => {
        const proximo = { ...atual, ...mudanca };

        // fora das categorias de depósito, o ganho é sempre fixo e não há depósito máximo nem
        // rollover no primeiro depósito
        if (!de_deposito(proximo.categoria)) proximo.tipo_ganho = 'Fixo';
        if (proximo.categoria !== 'Primeiro depósito') proximo.rollover_primeiro_deposito = false;

        return proximo;
    });

    const campo = (chave) => ({
        id: `promocao_${chave}`,
        value: dados[chave],
        $erro: Boolean(erros[chave]),
        onChange: (evento) => alterar({ [chave]: evento.target.value }),
    });

    const erro = (chave) => erros[chave] && <Error role="alert">{erros[chave]}</Error>;

    const corpo = () => {
        const percentual = dados.tipo_ganho === 'Percentual';
        const valor = percentual ? ler_numero(dados.valor) : ler_valor(dados.valor);

        return {
            nome: dados.nome.trim(),
            descricao: dados.descricao.trim() || null,
            ativa: dados.ativa,
            categoria: dados.categoria,
            modalidade: dados.modalidade,
            tipo_ganho: dados.tipo_ganho,
            valor: valor ?? dados.valor,
            valor_maximo_deposito: percentual ? (ler_valor(dados.valor_maximo_deposito) ?? (dados.valor_maximo_deposito || null)) : null,
            quantidade_indicacoes: dados.categoria === 'Indicação' ? (dados.quantidade_indicacoes || null) : null,
            data_inicio: para_api_data(dados.data_inicio),
            data_fim: para_api_data(dados.data_fim),
            rollover: dados.rollover,
            rollover_primeiro_deposito: dados.categoria === 'Primeiro depósito' && dados.rollover_primeiro_deposito,
            dias_cumprir: dados.dias_cumprir,
            valor_minimo_aposta: ler_valor(dados.valor_minimo_aposta) ?? dados.valor_minimo_aposta,
            valor_maximo_aposta: ler_valor(dados.valor_maximo_aposta) ?? dados.valor_maximo_aposta,
            odd_minima_aposta_simples: ler_numero(dados.odd_minima_aposta_simples),
            odd_minima_aposta_multipla: ler_numero(dados.odd_minima_aposta_multipla),
            valor_maximo_conversao: ler_valor(dados.valor_maximo_conversao) ?? dados.valor_maximo_conversao,
            afiliados_id: dados.afiliado?.id ?? null,
        };
    };

    const salvar = async () => {
        definir_salvando(true);
        definir_erros({});

        const enviado = corpo();

        // na promoção aplicada, os campos travados não vão (o servidor recusaria a mudança)
        if (travado) travados_apos_aplicada.forEach((chave) => delete enviado[chave]);

        // datas não alteradas não vão: o campo não tem segundos e o fim já passado de uma promoção
        // encerrada seria recusado sem o usuário ter mexido nele (FR-016)
        if (edicao) {
            ['data_inicio', 'data_fim'].forEach((chave) => {
                if (dados[chave] === para_campo_data(promocao[chave])) delete enviado[chave];
            });
        }

        try {
            const autorizacao = { headers: { Authorization: `Bearer ${token}` } };

            if (edicao) await api.put(`/clientes-promocoes/${promocao.id}`, enviado, autorizacao);
            else await api.post('/clientes-promocoes', enviado, autorizacao);

            ao_salvar(edicao ? 'Promoção alterada.' : 'Promoção cadastrada.');
        } catch (falha) {
            if (falha.response?.status === 422) {
                const recebidos = falha.response.data.errors ?? {};
                definir_erros({
                    ...Object.fromEntries(Object.keys(recebidos).map((chave) => [chave, erro_do_campo(falha, chave)])),
                    // o afiliado aparece no campo do seletor
                    ...(recebidos.afiliados_id ? { afiliado: erro_do_campo(falha, 'afiliados_id') } : {}),
                });
            } else {
                alerta_erro(falha);
            }
        } finally {
            definir_salvando(false);
        }
    };

    // campo de seleção de uma lista fixa (categoria, modalidade, tipo de ganho, sim/não)
    const selecao = (chave, opcoes, { desabilitado = false, valor = dados[chave], ao_mudar = (texto) => alterar({ [chave]: texto }) } = {}) => (
        <Select id={`promocao_${chave}`} value={valor} disabled={desabilitado} $erro={Boolean(erros[chave])} onChange={(evento) => ao_mudar(evento.target.value)}>
            {opcoes.map((opcao) => <option key={opcao.valor ?? opcao} value={opcao.valor ?? opcao}>{opcao.rotulo ?? opcao}</option>)}
        </Select>
    );

    const sim_nao = (chave) => selecao(chave, [{ valor: 'sim', rotulo: 'Sim' }, { valor: 'nao', rotulo: 'Não' }], {
        valor: dados[chave] ? 'sim' : 'nao',
        ao_mudar: (texto) => alterar({ [chave]: texto === 'sim' }),
    });

    const percentual = dados.tipo_ganho === 'Percentual';

    return (
        <CashModal
            aberto={aberto}
            icone="card_giftcard"
            titulo={edicao ? 'Editar promoção' : 'Cadastrar promoção'}
            descricao="Defina quem ganha o bônus, quanto e as regras para usar."
            ao_fechar={ao_fechar}
            altura_fixa
            largura={720}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            {travado && (
                <Warning>
                    <i className="material-icons" aria-hidden="true">lock</i>
                    Esta promoção já foi dada a clientes; valor, tipo de ganho, categoria e modalidade não podem mudar.
                </Warning>
            )}
            {erros.promocao && <Error role="alert">{erros.promocao}</Error>}

            <Group>
                <Title>Identificação</Title>
                <Fields>
                    <Field $inteiro>
                        <Label htmlFor="promocao_nome">Nome</Label>
                        <Input {...campo('nome')} maxLength={150} placeholder="Ex.: Boas-vindas 100%" />
                        {erro('nome')}
                    </Field>
                    <Field $inteiro>
                        <Label htmlFor="promocao_descricao">Descrição</Label>
                        <Textarea {...campo('descricao')} placeholder="Texto que explica a promoção (opcional)" />
                        {erro('descricao')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_ativa">Ativa</Label>
                        {sim_nao('ativa')}
                        {erro('ativa')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_afiliado">{e_admin ? 'Afiliado (vazio = geral)' : 'Afiliado da sua rede'}</Label>
                        <AffiliatePicker id="promocao_afiliado" valor={dados.afiliado} token={token} pode_limpar={e_admin} ao_escolher={(afiliado) => alterar({ afiliado })} />
                        {erro('afiliado')}
                    </Field>
                </Fields>
            </Group>

            <Group>
                <Title>Ganho</Title>
                <Fields>
                    <Field>
                        <Label htmlFor="promocao_categoria">Categoria</Label>
                        {selecao('categoria', categorias, { desabilitado: travado })}
                        {erro('categoria')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_modalidade">Modalidade</Label>
                        {selecao('modalidade', modalidades, { desabilitado: travado })}
                        {erro('modalidade')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_tipo_ganho">Tipo de ganho</Label>
                        {/* percentual só nas categorias de depósito; nas outras fica Fixo */}
                        {selecao('tipo_ganho', ['Fixo', 'Percentual'], { desabilitado: travado || !de_deposito(dados.categoria) })}
                        {erro('tipo_ganho')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_valor">{percentual ? 'Percentual do depósito (%)' : 'Valor do bônus (R$)'}</Label>
                        <Input {...campo('valor')} inputMode="decimal" placeholder={percentual ? '100' : '0,00'} disabled={travado} />
                        {erro('valor')}
                    </Field>
                    {percentual && (
                        <Field>
                            <Label htmlFor="promocao_valor_maximo_deposito">Depósito máximo (R$)</Label>
                            <Input {...campo('valor_maximo_deposito')} inputMode="decimal" placeholder="0,00" />
                            {erro('valor_maximo_deposito')}
                        </Field>
                    )}
                    {dados.categoria === 'Indicação' && (
                        <Field>
                            <Label htmlFor="promocao_quantidade_indicacoes">Quantidade de indicações</Label>
                            <Input {...campo('quantidade_indicacoes')} type="number" min="1" inputMode="numeric" placeholder="Ex.: 5" />
                            {erro('quantidade_indicacoes')}
                        </Field>
                    )}
                </Fields>
            </Group>

            <Group>
                <Title>Período</Title>
                <Fields>
                    <Field>
                        <Label htmlFor="promocao_data_inicio">Início</Label>
                        <Input {...campo('data_inicio')} type="datetime-local" />
                        {erro('data_inicio')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_data_fim">Fim (vazio = sem fim)</Label>
                        <Input {...campo('data_fim')} type="datetime-local" />
                        {erro('data_fim')}
                    </Field>
                </Fields>
            </Group>

            <Group>
                <Title>Rollover e prazo</Title>
                <Fields>
                    <Field>
                        <Label htmlFor="promocao_rollover">Rollover (vezes o bônus)</Label>
                        <Input {...campo('rollover')} type="number" min="0" inputMode="numeric" />
                        {erro('rollover')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_dias_cumprir">Dias para cumprir</Label>
                        <Input {...campo('dias_cumprir')} type="number" min="1" max="3650" inputMode="numeric" />
                        {erro('dias_cumprir')}
                    </Field>
                    {dados.categoria === 'Primeiro depósito' && (
                        <Field>
                            <Label htmlFor="promocao_rollover_primeiro_deposito">Rollover no primeiro depósito</Label>
                            {sim_nao('rollover_primeiro_deposito')}
                            <Hint>Aplica o rollover também ao depósito que deu o bônus.</Hint>
                            {erro('rollover_primeiro_deposito')}
                        </Field>
                    )}
                </Fields>
            </Group>

            <Group>
                <Title>Regras de aposta</Title>
                <Fields>
                    <Field>
                        <Label htmlFor="promocao_valor_minimo_aposta">Aposta mínima (R$)</Label>
                        <Input {...campo('valor_minimo_aposta')} inputMode="decimal" placeholder="0,00" />
                        {erro('valor_minimo_aposta')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_valor_maximo_aposta">Aposta máxima (R$)</Label>
                        <Input {...campo('valor_maximo_aposta')} inputMode="decimal" placeholder="0,00" />
                        {erro('valor_maximo_aposta')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_odd_minima_aposta_simples">Odd mínima simples</Label>
                        <Input {...campo('odd_minima_aposta_simples')} inputMode="decimal" placeholder="1,50" />
                        {erro('odd_minima_aposta_simples')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_odd_minima_aposta_multipla">Odd mínima múltipla</Label>
                        <Input {...campo('odd_minima_aposta_multipla')} inputMode="decimal" placeholder="1,30" />
                        {erro('odd_minima_aposta_multipla')}
                    </Field>
                    <Field>
                        <Label htmlFor="promocao_valor_maximo_conversao">Conversão máxima (R$)</Label>
                        <Input {...campo('valor_maximo_conversao')} inputMode="decimal" placeholder="0,00" />
                        <Hint>Máximo que passa para o saldo real ao converter.</Hint>
                        {erro('valor_maximo_conversao')}
                    </Field>
                </Fields>
            </Group>
        </CashModal>
    );
}
