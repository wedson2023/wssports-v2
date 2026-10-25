import { useEffect, useState } from 'react';

import CashModal, { Button, Error, Field, Fields, GhostButton, Hint, Input, Label } from '../CashModal';
import ToggleSwitch from '../ToggleSwitch';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Choose, Preview, SwitchRow } from './styles';

// data da API (ISO com -03:00) → valor do campo data e hora ("2026-10-27T13:00"), em Brasília
const para_campo = (data) => (data ? data.slice(0, 16) : '');

// cadastro e edição de um aviso da banca, o antigo pop-up (spec 021, FR-015): imagem, título, link,
// período de exibição (horário de Brasília) e ativo. Edição por POST com _method=PUT (tem arquivo)
export default function NoticeFormModal({ aberto, aviso = null, token, ao_fechar, ao_salvar }) {
    const [arquivo, definir_arquivo] = useState(null);
    const [previa, definir_previa] = useState(null);
    const [campos, definir_campos] = useState({});
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    // cada abertura começa com os dados do aviso (ou vazio no cadastro)
    useEffect(() => {
        if (!aberto) return;

        definir_arquivo(null);
        definir_previa(aviso?.imagem ?? null);
        definir_campos({
            titulo: aviso?.titulo ?? '',
            link: aviso?.link ?? '',
            inicio_em: para_campo(aviso?.inicio_em),
            fim_em: para_campo(aviso?.fim_em),
            ativo: aviso?.ativo ?? true,
        });
        definir_erros({});
    }, [aberto, aviso]);

    // prévia local da imagem escolhida
    useEffect(() => {
        if (!arquivo) return undefined;

        const endereco = URL.createObjectURL(arquivo);
        definir_previa(endereco);

        return () => URL.revokeObjectURL(endereco);
    }, [arquivo]);

    const mudar = (campo) => (evento) => definir_campos((atuais) => ({ ...atuais, [campo]: evento.currentTarget.value }));

    const salvar = async () => {
        const dados = new FormData();

        if (arquivo) dados.append('imagem', arquivo);
        ['titulo', 'link', 'inicio_em', 'fim_em'].forEach((campo) => dados.append(campo, campos[campo].trim()));
        dados.append('ativo', campos.ativo ? '1' : '0');
        if (aviso) dados.append('_method', 'PUT');

        definir_salvando(true);

        try {
            await api.post(aviso ? `/avisos/${aviso.id}` : '/avisos', dados, { headers: { Authorization: `Bearer ${token}` } });
            ao_salvar(aviso ? 'Aviso atualizado.' : 'Aviso cadastrado.');
        } catch (falha) {
            if (falha.response?.status === 422) {
                definir_erros(Object.fromEntries(Object.entries(falha.response.data?.errors ?? {}).map(([campo, mensagens]) => [campo, mensagens[0]])));
            } else {
                alerta_erro(falha);
            }
        } finally {
            definir_salvando(false);
        }
    };

    const erro = (campo) => erros[campo] && <Error role="alert">{erros[campo]}</Error>;

    return (
        <CashModal
            aberto={aberto}
            icone="campaign"
            titulo={aviso ? 'Editar aviso' : 'Cadastrar aviso'}
            descricao="Imagem que aparece ao abrir o site até o apostador marcar como lido. Tamanho recomendado 500x500."
            ao_fechar={ao_fechar}
            largura={620}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            {aberto && campos.ativo !== undefined && (
                <Fields>
                    <Field>
                        <Label htmlFor="aviso_imagem">Imagem</Label>
                        <Preview $proporcao="1 / 1">
                            {previa ? <img src={previa} alt="Prévia do aviso" /> : <span>Nenhuma imagem escolhida</span>}
                        </Preview>
                        <Choose>
                            <input id="aviso_imagem" type="file" accept="image/png,image/jpeg,image/webp" onChange={(evento) => definir_arquivo(evento.currentTarget.files?.[0] ?? null)} />
                        </Choose>
                        <Hint>png, jpg ou webp de até 2 MB.</Hint>
                        {erro('imagem')}
                    </Field>
                    <Field>
                        <Label htmlFor="aviso_titulo">Título (opcional)</Label>
                        <Input id="aviso_titulo" value={campos.titulo} maxLength={100} onChange={mudar('titulo')} $erro={Boolean(erros.titulo)} />
                        {erro('titulo')}
                        <Label htmlFor="aviso_link">Link (opcional)</Label>
                        <Input id="aviso_link" type="url" value={campos.link} placeholder="https://" onChange={mudar('link')} $erro={Boolean(erros.link)} />
                        {erro('link')}
                        <Label htmlFor="aviso_inicio">Início da exibição</Label>
                        <Input id="aviso_inicio" type="datetime-local" value={campos.inicio_em} onChange={mudar('inicio_em')} $erro={Boolean(erros.inicio_em)} />
                        {erro('inicio_em')}
                        <Label htmlFor="aviso_fim">Fim da exibição</Label>
                        <Input id="aviso_fim" type="datetime-local" value={campos.fim_em} onChange={mudar('fim_em')} $erro={Boolean(erros.fim_em)} />
                        <Hint>Sem fim, o aviso aparece até ser desativado.</Hint>
                        {erro('fim_em')}
                        <SwitchRow>
                            <ToggleSwitch ligado={campos.ativo} ao_trocar={(ligado) => definir_campos((atuais) => ({ ...atuais, ativo: ligado }))} rotulo="Aviso ativo" />
                            <span>{campos.ativo ? 'Ativo' : 'Inativo'}</span>
                        </SwitchRow>
                    </Field>
                </Fields>
            )}
        </CashModal>
    );
}
