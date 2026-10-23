import { useEffect, useRef, useState } from 'react';

import CashModal, { Button, erro_do_campo, Error, Field, Fields, GhostButton, Hint, Input, Label, Textarea, Warning } from '../CashModal';
import ClientPicker from '../ClientPicker';
import api from '../../utils/api';
import { alerta_erro, confirmar } from '../../utils/alerts';
import { Chip, Chips, Counter, LimitRow, Preview, Reach } from './styles';

const publicos = [
    { chave: 'Todos', rotulo: 'Todos os inscritos' },
    { chave: 'Por ID', rotulo: 'Por ID' },
    { chave: 'Sem interagir', rotulo: 'Sem interagir' },
];

const LIMITE_TITULO = 50;
const LIMITE_MENSAGEM = 150;

// espera depois da última mudança do público antes de contar os alcançados
const ATRASO_CONTAGEM = 400;

const vazio = { publico: 'Todos', clientes: [], dias: '10', titulo: '', mensagem: '' };

// público no formato da API (contracts/api.md §6.3)
const publico_api = (dados) => ({
    publico: dados.publico,
    ...(dados.publico === 'Por ID' ? { clientes_ids: dados.clientes.map((cliente) => cliente.id) } : {}),
    ...(dados.publico === 'Sem interagir' ? { dias: Number(dados.dias) } : {}),
});

// notificação push manual da banca (spec 019, FR-031 a FR-033a, contracts/paginas §9; o sino do
// antigo): público, título, mensagem, prévia, quantos serão alcançados e quantos envios restam hoje;
// o Admin também altera o limite do dia
export default function PushNotificationModal({ aberto, token, ao_fechar, ao_concluir }) {
    const [dados, definir_dados] = useState(vazio);
    const [limite, definir_limite] = useState(null);
    const [novo_limite, definir_novo_limite] = useState('');
    const [alcancados, definir_alcancados] = useState(null);
    const [erro, definir_erro] = useState(null);
    const [enviando, definir_enviando] = useState(false);
    const espera = useRef(null);
    const autorizacao = { headers: { Authorization: `Bearer ${token}` } };

    const carregar_limite = () => api.get('/clientes/notificacoes/limite', autorizacao)
        .then(({ data }) => {
            definir_limite(data);
            definir_novo_limite(String(data.limite));
        })
        .catch(alerta_erro);

    useEffect(() => {
        if (!aberto) return;

        definir_dados(vazio);
        definir_erro(null);
        definir_alcancados(null);
        carregar_limite();
    }, [aberto]);

    // contagem dos alcançados a cada mudança do público
    const chave_publico = JSON.stringify(publico_api(dados));

    useEffect(() => {
        if (!aberto) return undefined;

        const publico = JSON.parse(chave_publico);
        const incompleto = (publico.publico === 'Por ID' && !publico.clientes_ids.length)
            || (publico.publico === 'Sem interagir' && !(publico.dias >= 1 && publico.dias <= 365));

        if (incompleto) {
            definir_alcancados(0);
            return undefined;
        }

        let ativo = true;
        window.clearTimeout(espera.current);
        espera.current = window.setTimeout(() => {
            definir_alcancados(null);
            api.post('/clientes/notificacoes/alcance', publico, autorizacao)
                .then(({ data }) => ativo && definir_alcancados(data.alcancados))
                .catch(() => ativo && definir_alcancados(0));
        }, ATRASO_CONTAGEM);

        return () => {
            ativo = false;
            window.clearTimeout(espera.current);
        };
    }, [aberto, chave_publico]);

    const mudar = (mudanca) => definir_dados((atual) => ({ ...atual, ...mudanca }));

    const salvar_limite = async () => {
        try {
            const { data } = await api.put('/clientes/notificacoes/limite', { limite: Number(novo_limite) }, autorizacao);
            definir_limite(data);
        } catch (falha) {
            alerta_erro(falha);
        }
    };

    const sem_restantes = limite && limite.restantes <= 0;
    const pode_enviar = !enviando && !sem_restantes && alcancados > 0 && dados.titulo.trim() && dados.mensagem.trim();

    const enviar = async () => {
        if (!(await confirmar(`Enviar a notificação para ${alcancados} ${alcancados === 1 ? 'cliente' : 'clientes'}?`, '#43a047'))) return;

        definir_enviando(true);
        definir_erro(null);

        try {
            const { data } = await api.post('/clientes/notificacoes', { ...publico_api(dados), titulo: dados.titulo.trim(), mensagem: dados.mensagem.trim() }, autorizacao);
            ao_concluir(`Notificação enviada para ${data.data.alcancados} ${data.data.alcancados === 1 ? 'cliente' : 'clientes'}.`);
        } catch (falha) {
            if (falha.response?.status === 422) {
                definir_erro(falha);
                carregar_limite();
            } else {
                alerta_erro(falha);
            }
        } finally {
            definir_enviando(false);
        }
    };

    return (
        <CashModal
            aberto={aberto}
            icone="notifications"
            titulo="Enviar notificação"
            descricao="Aviso no celular dos clientes inscritos."
            ao_fechar={ao_fechar}
            altura_fixa
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={!pode_enviar} onClick={enviar}>{enviando ? 'Enviando…' : 'Enviar'}</Button>
                </>
            )}
        >
            {limite && (
                <LimitRow>
                    <span>{`${limite.restantes} ${limite.restantes === 1 ? 'envio restante' : 'envios restantes'} hoje`}</span>
                    {limite.pode_alterar && (
                        <>
                            <Input type="number" min="1" max="50" aria-label="Limite por dia" value={novo_limite} onChange={(evento) => definir_novo_limite(evento.target.value)} />
                            <GhostButton type="button" onClick={salvar_limite}>Salvar limite</GhostButton>
                        </>
                    )}
                </LimitRow>
            )}
            {sem_restantes && (
                <Warning>
                    <i className="material-icons" aria-hidden="true">block</i>
                    Limite de envios de hoje atingido.
                </Warning>
            )}
            <Chips role="group" aria-label="Público">
                {publicos.map((publico) => (
                    <Chip key={publico.chave} type="button" aria-pressed={dados.publico === publico.chave} onClick={() => mudar({ publico: publico.chave })}>
                        {publico.rotulo}
                    </Chip>
                ))}
            </Chips>
            <Fields>
                {dados.publico === 'Por ID' && (
                    <Field $inteiro>
                        <Label htmlFor="notificacao_clientes">Clientes</Label>
                        <ClientPicker id="notificacao_clientes" valor={dados.clientes} token={token} ao_mudar={(clientes) => mudar({ clientes })} />
                    </Field>
                )}
                {dados.publico === 'Sem interagir' && (
                    <Field $inteiro>
                        <Label htmlFor="notificacao_dias">Sem apostar nem depositar há (dias)</Label>
                        <Input id="notificacao_dias" type="number" min="1" max="365" value={dados.dias} onChange={(evento) => mudar({ dias: evento.target.value })} />
                        <Hint>Inclui quem nunca apostou nem depositou.</Hint>
                    </Field>
                )}
                <Field $inteiro>
                    <Label htmlFor="notificacao_titulo">Título</Label>
                    <Input id="notificacao_titulo" maxLength={LIMITE_TITULO} value={dados.titulo} onChange={(evento) => mudar({ titulo: evento.target.value })} />
                    <Counter>{dados.titulo.length}/{LIMITE_TITULO}</Counter>
                    {erro_do_campo(erro, 'titulo') && <Error>{erro_do_campo(erro, 'titulo')}</Error>}
                </Field>
                <Field $inteiro>
                    <Label htmlFor="notificacao_mensagem">Mensagem</Label>
                    <Textarea id="notificacao_mensagem" maxLength={LIMITE_MENSAGEM} value={dados.mensagem} onChange={(evento) => mudar({ mensagem: evento.target.value })} />
                    <Counter>{dados.mensagem.length}/{LIMITE_MENSAGEM}</Counter>
                    {erro_do_campo(erro, 'mensagem') && <Error>{erro_do_campo(erro, 'mensagem')}</Error>}
                </Field>
            </Fields>
            <Preview aria-label="Prévia da notificação">
                <i className="material-icons" aria-hidden="true">notifications_active</i>
                <div>
                    <strong>{dados.titulo || 'Título da notificação'}</strong>
                    <span>{dados.mensagem || 'Mensagem da notificação'}</span>
                </div>
            </Preview>
            <Reach>{alcancados === null ? 'Contando os alcançados…' : `Alcançados: ${alcancados}`}</Reach>
            {erro_do_campo(erro, 'publico') && <Error role="alert">{erro_do_campo(erro, 'publico')}</Error>}
        </CashModal>
    );
}
