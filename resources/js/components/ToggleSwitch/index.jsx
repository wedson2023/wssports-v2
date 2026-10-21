import { Knob, Track } from './styles';

// chave liga/desliga acessível (role switch, Espaço/Enter), na cor do tema
export default function ToggleSwitch({ ligado, ao_trocar, desabilitado = false, carregando = false, rotulo = '' }) {
    const trocar = () => {
        if (!desabilitado && !carregando) ao_trocar(!ligado);
    };

    return (
        <Track
            type="button"
            role="switch"
            aria-checked={ligado}
            aria-label={rotulo}
            aria-busy={carregando}
            disabled={desabilitado}
            $ligado={ligado}
            $carregando={carregando}
            onClick={trocar}
        >
            <Knob $ligado={ligado} />
        </Track>
    );
}
