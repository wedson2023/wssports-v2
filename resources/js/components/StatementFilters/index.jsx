import Spinner from '../Spinner';
import { Container, Select } from './styles';

const operacoes = [
    { valor: '', titulo: 'Todas operações' },
    { valor: 'palpites', titulo: 'Palpites' },
    { valor: 'depositos', titulo: 'Depósitos' },
    { valor: 'saques', titulo: 'Saque' },
    { valor: 'bonus', titulo: 'Bônus' },
    { valor: 'cashback', titulo: 'Cashback' },
    { valor: 'estornos', titulo: 'Estornos' },
];

const situacoes = [
    { valor: '', titulo: 'Todos palpites' },
    { valor: 'Aguardando', titulo: 'Aguardando' },
    { valor: 'Perdedor', titulo: 'Perdedor' },
    { valor: 'Vencedor', titulo: 'Vencedor' },
];

// filtros do extrato (ContainerFilter de screens/credits): a situação só vale para Palpites
export default function StatementFilters({ filtros, ao_mudar, carregando }) {
    return (
        <Container>
            {carregando ? <Spinner tamanho={24} cor="#999" /> : <i className="material-icons" aria-hidden="true">filter_list</i>}
            <Select
                aria-label="Operação"
                value={filtros.operacao}
                onChange={(evento) => ao_mudar({ operacao: evento.target.value, situacao: '' })}
            >
                {operacoes.map(({ valor, titulo }) => <option key={valor} value={valor}>{titulo}</option>)}
            </Select>
            <Select
                aria-label="Situação do palpite"
                value={filtros.situacao}
                disabled={filtros.operacao !== 'palpites'}
                onChange={(evento) => ao_mudar({ ...filtros, situacao: evento.target.value })}
            >
                {situacoes.map(({ valor, titulo }) => <option key={valor} value={valor}>{titulo}</option>)}
            </Select>
        </Container>
    );
}
