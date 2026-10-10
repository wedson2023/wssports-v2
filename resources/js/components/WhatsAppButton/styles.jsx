import styled from 'styled-components';

// Call de main/styles.js
export const Button = styled.img`
    position: fixed;
    z-index: 25;
    bottom: 15px;
    left: 15px;
    width: 55px;
    height: 55px;
    padding: 15px;
    border-radius: 50%;
    background-color: #35cd96;
    transition-duration: 0.5s;
    cursor: pointer;
    outline: none;

    &:hover {
        background-color: #075e54;
    }
`;
