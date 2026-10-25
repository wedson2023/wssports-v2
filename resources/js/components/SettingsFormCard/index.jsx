import { useEffect } from 'react';
import Swal from 'sweetalert2';

import SaveBar from '../SaveBar';
import SettingsForm from '../SettingsForm';
import SettingsSection from '../SettingsSection';
import useSecaoConfiguracoes from '../../hooks/useSecaoConfiguracoes';
import { alerta_erro } from '../../utils/alerts';
import { Notice } from './styles';

// aviso curto no canto, sem bloquear a tela
const avisar = (texto) => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: texto, showConfirmButton: false, timer: 2500 });

// seção da aba Operação (spec 021, research R-17): sanfona que, ao abrir, carrega a sua rota, mostra
// os campos da definição (utils/settings_fields.js) e salva só o que mudou, com a barra de salvar
// própria; avisa a página quantas alterações estão pendentes
export default function SettingsFormCard({ definicao, aberta, ao_alternar, ao_pendencias }) {
    const secao = useSecaoConfiguracoes({ rota: definicao.rota, habilitado: aberta });
    const { valores, erros, quantidade, carregando, salvando, erro_carga } = secao;

    useEffect(() => {
        ao_pendencias?.(definicao.chave, quantidade);
    }, [definicao.chave, quantidade]);

    const salvar = async () => {
        try {
            if (await secao.salvar()) avisar('Configurações salvas.');
        } catch (falha) {
            alerta_erro(falha);
        }
    };

    const somente_leitura = valores && definicao.somente_leitura ? definicao.somente_leitura(valores) : false;

    return (
        <SettingsSection
            icone={definicao.icone}
            titulo={definicao.titulo}
            descricao={definicao.descricao}
            recolhivel
            aberta={aberta}
            ao_alternar={ao_alternar}
            carregando={carregando || (!valores && !erro_carga)}
            erro={erro_carga}
            ao_tentar_de_novo={secao.recarregar}
        >
            {valores && (
                <>
                    {definicao.aviso_leitura && somente_leitura && <Notice>{definicao.aviso_leitura}</Notice>}
                    <SettingsForm campos={definicao.campos} valores={valores} erros={erros} ao_mudar={secao.mudar} somente_leitura={somente_leitura} />
                    <SaveBar quantidade={quantidade} salvando={salvando} ao_desfazer={secao.desfazer} ao_salvar={salvar} />
                </>
            )}
        </SettingsSection>
    );
}
