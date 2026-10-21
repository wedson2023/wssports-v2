import Backdrop from '../Backdrop';
import ModalHeader from '../ModalHeader';
import PermissionsPanel from '../PermissionsPanel';
import useVoltarFecha from '../../hooks/useVoltarFecha';
import { Body, Container } from './styles';

// modal de permissões de um usuário da rede (spec 015, FR-017); cada chave grava na hora
export default function PermissionsModal({ usuario, ao_fechar }) {
    const visivel = Boolean(usuario);
    useVoltarFecha(visivel, ao_fechar);

    return (
        <>
            <Backdrop visivel={visivel} ao_fechar={ao_fechar} camada={45} />
            <Container $visivel={visivel} role="dialog" aria-label="Permissões do usuário">
                <ModalHeader
                    icone="vpn_key"
                    titulo={usuario ? `Permissões · ${usuario.nome}` : 'Permissões'}
                    descricao={usuario && `Escolha o que este ${usuario.funcao.toLowerCase()} pode fazer no painel. Cada mudança é gravada na hora.`}
                    ao_fechar={ao_fechar}
                />
                {usuario && (
                    <Body>
                        <PermissionsPanel usuario_id={usuario.id} />
                    </Body>
                )}
            </Container>
        </>
    );
}
