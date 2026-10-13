import styled from 'styled-components';
import { mobile } from '../../theme/tokens';

export const Banner = styled.div`
    position: sticky;
    top: 0;
    z-index: 40;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: -15px -10px 10px;
    padding: 10px 14px;
    background: #f5a623;
    color: #222;
    text-align: left;

    i {
        font-size: 22px;
    }

    ${mobile} {
        flex-wrap: wrap;
    }
`;

export const Text = styled.p`
    flex: 1;
    min-width: 0;
    font-size: 13px;
`;

export const Button = styled.button`
    padding: 8px 14px;
    border: none;
    border-radius: 8px;
    background: #222;
    color: #fff;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;

    &:hover {
        opacity: 0.85;
    }
`;
