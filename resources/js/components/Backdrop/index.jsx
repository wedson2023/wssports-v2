import { Screen } from './styles';

// fundo escurecido atrás de modais e da gaveta; clicar fecha
export default function Backdrop({ visivel, ao_fechar, camada = 25 }) {
    return <Screen $visivel={visivel} $camada={camada} onClick={ao_fechar} />;
}
