import styled from 'styled-components';

import { mobile } from '../../theme/tokens';

export const Container = styled.div`
    position: relative;
    border-radius: 0.7em;
    overflow: hidden;
    border: solid 1px #fff;
    padding: 7px;

    &:hover > div {
        opacity: 1;
        visibility: visible;
    }
`;

export const Buttons = styled.div`
    transition-duration: 0.3s;
    position: absolute;
    background-color: #000;
    visibility: hidden;
    opacity: 0;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-around;
    align-items: center;
    padding: 10px 0;
`;

export const Button = styled.a`
    text-decoration: none;
    border: solid thin #ccc;
    background-color: transparent;
    color: #ccc;
    padding: 10px 15px;
    border-radius: 0.5em;
    cursor: pointer;

    &:hover {
        background-color: #ccc;
        color: #000;
    }
`;

export const Image = styled.img`
    width: 100%;
    height: 110px;
    border-radius: 0.7em;

    ${mobile} {
        height: 90px;
    }
`;

export const Title = styled.span`
    color: #fff;
    display: block;
    max-width: 20ch;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.9em;
`;

export const Provider = styled.small`
    display: block;
    max-width: 20ch;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.7em;
    text-transform: uppercase;
    margin-top: 3px;
    color: #999;
`;
