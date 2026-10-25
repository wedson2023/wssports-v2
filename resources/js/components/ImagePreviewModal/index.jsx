import CashModal from '../CashModal';
import { Image, Link } from './styles';

// imagem de um banner ou aviso em tamanho real (spec 021, FR-014 e FR-015), limitada à tela
export default function ImagePreviewModal({ imagem, titulo, link = null, ao_fechar }) {
    return (
        <CashModal aberto={Boolean(imagem)} icone="visibility" titulo={titulo} descricao="Veja a imagem como aparece no site." ao_fechar={ao_fechar} largura={1320}>
            {imagem && <Image src={imagem} alt={titulo} />}
            {link && (
                <Link href={link} target="_blank" rel="noopener noreferrer">
                    <i className="material-icons" aria-hidden="true">link</i>
                    {link}
                </Link>
            )}
        </CashModal>
    );
}
