import Spinner from '../Spinner';
import { Bar, Info, Save, Undo } from './styles';

// rodapé do editor de cotações (spec 016, FR-006): quantas alterações não salvas, Desfazer e Salvar;
// some quando não há pendentes
export default function SaveBar({ quantidade, salvando = false, ao_desfazer, ao_salvar }) {
    if (!quantidade) return null;

    return (
        <Bar role="status">
            <Info>
                <i className="material-icons" aria-hidden="true">edit_note</i>
                {quantidade} {quantidade === 1 ? 'alteração não salva' : 'alterações não salvas'}
            </Info>
            <Undo type="button" onClick={ao_desfazer} disabled={salvando}>Desfazer</Undo>
            <Save type="button" onClick={ao_salvar} disabled={salvando} aria-busy={salvando}>
                {salvando ? <Spinner tamanho={18} /> : 'Salvar'}
            </Save>
        </Bar>
    );
}
