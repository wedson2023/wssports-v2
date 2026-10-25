import { useEffect, useState } from 'react';

import CashModal, { Button, Error, Field, Fields, GhostButton, Hint, Input, Label } from '../CashModal';
import ToggleSwitch from '../ToggleSwitch';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Choose, Preview, SwitchRow } from './styles';

// cadastro e edição de um banner do carrossel (spec 021, FR-014): imagem (ajustada pelo servidor
// para 1280x405), link e ativo. Edição por POST com _method=PUT, porque o envio tem arquivo
export default function BannerFormModal({ aberto, banner = null, token, ao_fechar, ao_salvar }) {
    const [arquivo, definir_arquivo] = useState(null);
    const [previa, definir_previa] = useState(null);
    const [link, definir_link] = useState('');
    const [ativo, definir_ativo] = useState(true);
    const [erros, definir_erros] = useState({});
    const [salvando, definir_salvando] = useState(false);

    // cada abertura começa com os dados do banner (ou vazio no cadastro)
    useEffect(() => {
        if (!aberto) return;

        definir_arquivo(null);
        definir_previa(banner?.imagem ?? null);
        definir_link(banner?.link ?? '');
        definir_ativo(banner?.ativo ?? true);
        definir_erros({});
    }, [aberto, banner]);

    // prévia local da imagem escolhida
    useEffect(() => {
        if (!arquivo) return undefined;

        const endereco = URL.createObjectURL(arquivo);
        definir_previa(endereco);

        return () => URL.revokeObjectURL(endereco);
    }, [arquivo]);

    const salvar = async () => {
        const dados = new FormData();

        if (arquivo) dados.append('imagem', arquivo);
        dados.append('link', link.trim());
        dados.append('ativo', ativo ? '1' : '0');
        if (banner) dados.append('_method', 'PUT');

        definir_salvando(true);

        try {
            await api.post(banner ? `/banners/${banner.id}` : '/banners', dados, { headers: { Authorization: `Bearer ${token}` } });
            ao_salvar(banner ? 'Banner atualizado.' : 'Banner cadastrado.');
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

    return (
        <CashModal
            aberto={aberto}
            icone="view_carousel"
            titulo={banner ? 'Editar banner' : 'Cadastrar banner'}
            descricao="A imagem é ajustada para 1280x405 e aparece no carrossel da página inicial."
            ao_fechar={ao_fechar}
            largura={640}
            rodape={(
                <>
                    <GhostButton type="button" onClick={ao_fechar}>Cancelar</GhostButton>
                    <Button type="button" disabled={salvando} onClick={salvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
                </>
            )}
        >
            <Fields>
                <Field $inteiro>
                    <Label htmlFor="banner_imagem">Imagem</Label>
                    <Preview $proporcao="1280 / 405">
                        {previa ? <img src={previa} alt="Prévia do banner" /> : <span>Nenhuma imagem escolhida</span>}
                    </Preview>
                    <Choose>
                        <input id="banner_imagem" type="file" accept="image/png,image/jpeg,image/webp" onChange={(evento) => definir_arquivo(evento.currentTarget.files?.[0] ?? null)} />
                    </Choose>
                    <Hint>png, jpg ou webp de até 4 MB. Tamanho recomendado 1280x405.</Hint>
                    {erros.imagem && <Error role="alert">{erros.imagem}</Error>}
                </Field>
                <Field $inteiro>
                    <Label htmlFor="banner_link">Link (opcional)</Label>
                    <Input id="banner_link" type="url" value={link} placeholder="https://" onChange={(evento) => definir_link(evento.currentTarget.value)} $erro={Boolean(erros.link)} />
                    {erros.link && <Error role="alert">{erros.link}</Error>}
                </Field>
                <Field>
                    <Label>Situação</Label>
                    <SwitchRow>
                        <ToggleSwitch ligado={ativo} ao_trocar={definir_ativo} rotulo="Banner ativo" />
                        <span>{ativo ? 'Ativo no carrossel' : 'Inativo'}</span>
                    </SwitchRow>
                </Field>
            </Fields>
        </CashModal>
    );
}
