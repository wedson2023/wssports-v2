import { useRef, useState } from 'react';
import Swal from 'sweetalert2';

import Spinner from '../Spinner';
import useSessao from '../../hooks/useSessao';
import api from '../../utils/api';
import { alerta_erro } from '../../utils/alerts';
import { Actions, Error, Hint, Icon, Icons, Preview, Previews, Upload, Warning } from './styles';

// ícones mostrados depois do envio (os demais tamanhos também são gerados)
const tamanhos_mostrados = ['32x32', '96x96', '192x192', '512x512'];

// logo da banca (spec 021, US2): prévia sobre fundo escuro e claro, envio da nova logo e os ícones
// do aplicativo gerados dela; o aviso de imagem pequena aparece em faixa amarela (FR-013)
export default function LogoUploader({ logo, icones, ao_enviar }) {
    const { sessao } = useSessao();
    const entrada = useRef(null);
    const [enviando, definir_enviando] = useState(false);
    const [erro, definir_erro] = useState(null);
    const [resultado, definir_resultado] = useState(null);

    const atual = resultado?.logo ?? logo;
    const lista_icones = (resultado?.icones ?? icones)?.manifest?.filter((icone) => tamanhos_mostrados.includes(icone.sizes)) ?? [];

    const enviar = async (evento) => {
        const arquivo = evento.currentTarget.files?.[0];
        evento.currentTarget.value = '';

        if (!arquivo) return;

        const dados = new FormData();
        dados.append('logo', arquivo);
        definir_enviando(true);
        definir_erro(null);

        try {
            const { data } = await api.post('/configuracoes/logo', dados, { headers: { Authorization: `Bearer ${sessao?.token}` } });

            definir_resultado(data.data);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Logo atualizada.', showConfirmButton: false, timer: 2500 });
            ao_enviar?.();
        } catch (falha) {
            if (falha.response?.status === 422) definir_erro(falha.response.data?.errors?.logo?.[0] ?? falha.response.data?.message);
            else alerta_erro(falha);
        } finally {
            definir_enviando(false);
        }
    };

    return (
        <>
            <Previews>
                <Preview $fundo="#000000"><img src={atual} alt="Logo atual no fundo escuro" /></Preview>
                <Preview $fundo="#FFFFFF"><img src={atual} alt="Logo atual no fundo claro" /></Preview>
            </Previews>
            <Actions>
                <input ref={entrada} type="file" accept="image/png,image/jpeg,image/webp" hidden onChange={enviar} />
                <Upload type="button" disabled={enviando} onClick={() => entrada.current?.click()}>
                    {enviando ? <Spinner tamanho={18} /> : <i className="material-icons" aria-hidden="true">upload</i>}
                    Enviar nova logo
                </Upload>
                <Hint>PNG quadrado de 512x512, até 1 MB (png, jpg ou webp).</Hint>
            </Actions>
            {erro && <Error role="alert">{erro}</Error>}
            {resultado?.aviso && (
                <Warning role="status">
                    <i className="material-icons" aria-hidden="true">warning</i>
                    {resultado.aviso}
                </Warning>
            )}
            {lista_icones.length > 0 && (
                <Icons aria-label="Ícones do aplicativo">
                    {lista_icones.map((icone) => (
                        <Icon key={icone.sizes}>
                            <div><img src={icone.src} alt="" /></div>
                            <span>{icone.sizes}</span>
                        </Icon>
                    ))}
                </Icons>
            )}
        </>
    );
}
