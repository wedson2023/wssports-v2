import { Button } from './styles';

// alterna entre modo claro (sol) e escuro (lua) na hora, sem recarregar (FR-053, FR-056)
export default function ThemeToggle({ modo, ao_alternar }) {
    const claro = modo === 'claro';

    return (
        <Button
            role="button"
            onClick={ao_alternar}
            title={claro ? 'Mudar para o modo escuro' : 'Mudar para o modo claro'}
            aria-label={claro ? 'Mudar para o modo escuro' : 'Mudar para o modo claro'}
        >
            {claro ? 'light_mode' : 'dark_mode'}
        </Button>
    );
}
