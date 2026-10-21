import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

// cores do cartão, cabeçalho, bordas e campos em cada tema (padrão do painel de permissões, spec 015)
const fundo_grupo = ({ theme }) => (theme.modo === 'escuro' ? '#1b1b1b' : '#ffffff');
const fundo_cabecalho = ({ theme }) => (theme.modo === 'escuro' ? '#262626' : '#f3f3f3');
const borda = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#e2e2e2');
const borda_campo = ({ theme }) => (theme.modo === 'escuro' ? '#3a3a3a' : '#a8a8a8');

// cor do valor: aumento verde, redução vermelha (legíveis nos dois temas)
const cor_sinal = ({ $sinal, theme }) => {
    if ($sinal > 0) return theme.modo === 'escuro' ? '#66bb6a' : '#2e7d32';
    if ($sinal < 0) return theme.modo === 'escuro' ? '#ef5350' : '#c62828';

    return theme.texto_principal;
};

export const Toolbar = styled.div`
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
`;

export const SearchBox = styled.label`
    display: flex;
    flex: 1;
    align-items: center;
    gap: 6px;
    min-width: 200px;
    padding: 0 10px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${fundo_grupo};
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Search = styled.input`
    flex: 1;
    min-width: 0;
    padding: 10px 0;
    border: none;
    background: transparent;
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    &:focus {
        outline: none;
    }
`;

export const ApplyBox = styled.div`
    display: flex;
    gap: 6px;

    ${mobile} {
        flex: 1 1 100%;
    }
`;

export const Input = styled.input`
    width: 100px;
    padding: 10px;
    border: solid 1px ${borda_campo};
    border-radius: 6px;
    background-color: ${fundo_grupo};
    color: ${({ theme }) => theme.texto_principal};
    font: inherit;

    &:focus {
        outline: none;
        border-color: ${({ theme }) => theme.principal};
    }

    ${mobile} {
        flex: 1;
        width: auto;
    }
`;

export const ToolbarButton = styled.button`
    flex-shrink: 0;
    padding: 0 14px;
    min-height: 40px;
    border: solid 1px ${({ theme, $leve }) => ($leve ? borda_campo({ theme }) : theme.principal)};
    border-radius: 6px;
    background-color: ${({ theme, $leve }) => ($leve ? 'transparent' : theme.principal)};
    color: ${({ theme, $leve }) => ($leve ? theme.texto_principal : '#fff')};
    font: inherit;
    font-size: 0.85em;
    font-weight: 500;
    cursor: pointer;

    &:hover {
        filter: brightness(1.1);
    }
`;

export const ToolbarInfo = styled.div`
    display: flex;
    align-items: center;
    gap: 10px;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.85em;

    strong {
        color: ${({ theme }) => theme.principal};
        font-size: 1.1em;
    }

    ${mobile} {
        flex: 1 1 100%;
        justify-content: space-between;
    }
`;

export const Hint = styled.p`
    margin: 0;
    padding: 24px 10px;
    color: ${({ theme }) => theme.texto_apagado};
    text-align: center;
`;

// grupo de mercado; não encolhe dentro de áreas que rolam
export const Group = styled.section`
    flex-shrink: 0;
    border: solid 1px ${({ theme, $aberto }) => ($aberto ? theme.principal : borda({ theme }))};
    border-radius: 8px;
    background-color: ${fundo_grupo};
    overflow: hidden;
`;

export const GroupHeader = styled.div`
    background-color: ${fundo_cabecalho};

    > button {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        min-height: 48px;
        padding: 10px 12px;
        border: none;
        background: transparent;
        color: ${({ theme }) => theme.texto_principal};
        font: inherit;
        text-align: left;
        cursor: pointer;
    }
`;

export const GroupTitle = styled.span`
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    font-size: 0.88em;
    font-weight: 500;

    small {
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.85em;
        font-weight: 400;
    }
`;

// contagem de ajustadas: verde todas, amarelo algumas, cinza nenhuma
export const Badge = styled.span`
    flex-shrink: 0;
    padding: 2px 8px;
    border-radius: 10px;
    background-color: ${({ $todas, $nenhuma, theme }) => {
        if ($todas) return '#2e7d32';
        if ($nenhuma) return theme.modo === 'escuro' ? '#3a3a3a' : '#dcdcdc';
        return '#f9a825';
    }};
    color: ${({ $todas, $nenhuma, theme }) => ($todas ? '#fff' : ($nenhuma ? theme.texto_secundario : '#000'))};
    font-size: 0.72em;
    font-weight: 500;
    white-space: nowrap;
`;

export const Chevron = styled.i.attrs(() => ({ className: 'material-icons', 'aria-hidden': true }))`
    flex-shrink: 0;
    color: ${({ theme }) => theme.texto_secundario};
`;

export const Items = styled.div`
    display: flex;
    flex-direction: column;
`;

export const GroupApply = styled.div`
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
    padding: 10px 14px;
    border-top: solid 1px ${borda};
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.82em;

    ${mobile} {
        span {
            flex: 1;
        }
    }
`;

// linha da cotação; pendente com faixa na cor do tema, erro com faixa vermelha
export const Item = styled.div`
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 52px;
    padding: 8px 14px;
    border-top: solid 1px ${borda};
    border-left: solid 3px ${({ theme, $pendente, $erro }) => {
        if ($erro) return '#e53935';
        return $pendente ? theme.principal : 'transparent';
    }};
`;

export const Name = styled.div`
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    color: ${({ theme }) => theme.texto_principal};
    font-size: 0.88em;

    small {
        color: ${({ theme }) => theme.texto_apagado};
        font-size: 0.85em;
    }

    em {
        color: #e53935;
        font-size: 0.82em;
        font-style: normal;
    }
`;

export const Provider = styled.div`
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    color: ${({ theme }) => theme.texto_secundario};
    font-size: 0.78em;
    white-space: nowrap;

    span {
        color: ${({ theme }) => theme.texto_principal};
        font-weight: 500;
    }
`;

export const FieldBox = styled.div`
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
`;

// campo do valor: pendente com fundo suave na cor do tema, erro com borda vermelha
export const Field = styled.label`
    display: flex;
    align-items: center;
    width: 112px;
    border: solid 1px ${({ theme, $pendente, $erro }) => {
        if ($erro) return '#e53935';
        return $pendente ? theme.principal : borda_campo({ theme });
    }};
    border-radius: 6px;
    background-color: ${({ theme, $pendente }) => ($pendente ? `${theme.principal}1f` : fundo_grupo({ theme }))};

    input {
        width: 100%;
        min-width: 0;
        padding: 10px 4px 10px 10px;
        border: none;
        background: transparent;
        color: ${cor_sinal};
        font: inherit;
        font-weight: 500;
        text-align: right;

        &:focus {
            outline: none;
        }

        &:disabled {
            opacity: 0.6;
        }
    }

    &:focus-within {
        border-color: ${({ theme }) => theme.principal};
    }

    ${mobile} {
        width: 96px;
    }
`;

export const Suffix = styled.span`
    padding-right: 10px;
    color: ${({ theme }) => theme.texto_apagado};
    font-size: 0.85em;
`;

// cadeado: fechado na cor do tema, aberto apagado
export const Lock = styled.button`
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: solid 1px ${({ theme, $fechado }) => ($fechado ? theme.principal : borda_campo({ theme }))};
    border-radius: 8px;
    background-color: ${({ theme, $fechado }) => ($fechado ? `${theme.principal}1f` : 'transparent')};
    color: ${({ theme, $fechado }) => ($fechado ? theme.principal : theme.texto_apagado)};
    cursor: pointer;

    i {
        font-size: 20px;
    }

    &:disabled {
        cursor: default;
        opacity: 0.75;
    }
`;
