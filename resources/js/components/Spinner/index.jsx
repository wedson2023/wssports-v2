import { useId } from 'react';
import { Svg } from './styles';

// indicador "TailSpin" do react-loader-spinner usado no Finalizar do sistema antigo (R-22)
export default function Spinner({ tamanho = 20, cor = '#fff' }) {
    const id_gradiente = useId();

    return (
        <Svg width={tamanho} height={tamanho} viewBox="0 0 38 38" aria-label="Carregando">
            <defs>
                <linearGradient x1="8.042%" y1="0%" x2="65.682%" y2="23.865%" id={id_gradiente}>
                    <stop stopColor={cor} stopOpacity="0" offset="0%" />
                    <stop stopColor={cor} stopOpacity=".631" offset="63.146%" />
                    <stop stopColor={cor} offset="100%" />
                </linearGradient>
            </defs>
            <g fill="none" fillRule="evenodd">
                <g transform="translate(1 1)">
                    <path d="M36 18c0-9.94-8.06-18-18-18" stroke={`url(#${id_gradiente})`} strokeWidth="2">
                        <animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="0.9s" repeatCount="indefinite" />
                    </path>
                    <circle fill={cor} cx="36" cy="18" r="1">
                        <animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="0.9s" repeatCount="indefinite" />
                    </circle>
                </g>
            </g>
        </Svg>
    );
}
