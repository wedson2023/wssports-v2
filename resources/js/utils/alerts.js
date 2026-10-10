import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

// alertas e confirmações com os mesmos títulos, botões e cores do sistema antigo
// (wssports.bet/resources/js/utils/helpers.js: swal_success, swal_warning, swal_ask, message)

export const alerta_sucesso = (texto) =>
    Swal.fire({ title: 'Sucesso', text: texto, icon: 'success', confirmButtonText: 'OK' });

export const alerta_atencao = (texto) =>
    Swal.fire({ title: 'Atenção', text: texto, icon: 'warning', confirmButtonText: 'Entendi' });

// devolve true quando o usuário confirma
export const confirmar = async (texto, cor_confirmar = '#c40808') => {
    const { isConfirmed } = await Swal.fire({
        title: 'Confirme por favor',
        text: texto,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim',
        cancelButtonText: 'Não',
        confirmButtonColor: cor_confirmar,
        cancelButtonColor: '#999',
    });

    return isConfirmed;
};

// mensagem para o apostador: na validação (422), só a primeira mensagem, sem o "(and N more
// errors)" que o Laravel acrescenta ao texto geral
const mensagem_da_resposta = (data) => {
    const primeira_validacao = Object.values(data?.errors ?? {})[0]?.[0];

    return primeira_validacao ?? data?.message ?? 'Não foi possível concluir a operação.';
};

// erro de uma requisição (axios): mensagem do backend ou aviso de conexão
export const alerta_erro = (erro) => {
    if (erro?.response) {
        const { status, data } = erro.response;
        const atencao = status === 400 || status === 401;

        return Swal.fire({
            title: atencao ? 'Atenção!' : 'Erro!',
            text: mensagem_da_resposta(data),
            icon: atencao ? 'warning' : 'error',
            confirmButtonText: 'Entendi',
        });
    }

    return Swal.fire({
        title: 'Erro!',
        text: 'Verifique sua conexão com a internet.',
        icon: 'warning',
        confirmButtonText: 'Entendi',
    });
};
