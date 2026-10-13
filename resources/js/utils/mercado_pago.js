// MercadoPago.js v2 (spec 007, research R-08): os campos do cartão são iframes do Mercado Pago,
// então o número, a validade e o código do cartão nunca passam pelo nosso servidor; só o token

const url_sdk = 'https://sdk.mercadopago.com/js/v2';

let carregando = null;

// injeta o script uma vez e reaproveita a mesma promessa nas próximas aberturas
export const carregar_sdk = () => {
    if (window.MercadoPago) return Promise.resolve();

    if (!carregando) {
        carregando = new Promise((resolver, rejeitar) => {
            const script = document.createElement('script');
            script.src = url_sdk;
            script.async = true;
            script.onload = resolver;
            script.onerror = () => {
                carregando = null;
                rejeitar(new Error('Não foi possível carregar o pagamento com cartão.'));
            };
            document.head.appendChild(script);
        });
    }

    return carregando;
};

// monta os três campos seguros nos ids informados e avisa quando os 6 primeiros dígitos mudam
export const criar_campos = async ({ public_key, ids, estilo, ao_mudar_bin }) => {
    await carregar_sdk();

    const mp = new window.MercadoPago(public_key, { locale: 'pt-BR' });
    const campos = {
        numero: mp.fields.create('cardNumber', { placeholder: 'Número do cartão', style: estilo }).mount(ids.numero),
        validade: mp.fields.create('expirationDate', { placeholder: 'MM/AA', style: estilo }).mount(ids.validade),
        codigo: mp.fields.create('securityCode', { placeholder: 'CVV', style: estilo }).mount(ids.codigo),
    };

    campos.numero.on('binChange', ({ bin }) => ao_mudar_bin(bin ?? null));

    // bandeira e parcelas do cartão para o valor do depósito
    const consultar = async (bin, valor) => {
        const { results } = await mp.getPaymentMethods({ bin });
        const metodo = results?.[0] ?? null;
        const parcelas = await mp.getInstallments({ amount: valor, bin, paymentTypeId: 'credit_card' });
        const ofertas = parcelas?.[0] ?? null;

        return {
            payment_method_id: metodo?.id ?? null,
            bandeira: metodo?.name ?? null,
            thumbnail: metodo?.secure_thumbnail ?? null,
            issuer_id: ofertas?.issuer?.id ? String(ofertas.issuer.id) : null,
            opcoes_parcelas: (ofertas?.payer_costs ?? []).map((custo) => ({
                parcelas: custo.installments,
                texto: custo.recommended_message,
                total: custo.total_amount,
            })),
        };
    };

    const gerar_token = async ({ nome, cpf }) => {
        const token = await mp.fields.createCardToken({
            cardholderName: nome,
            identificationType: 'CPF',
            identificationNumber: cpf.replace(/\D/g, ''),
        });

        return token.id;
    };

    const desmontar = () => Object.values(campos).forEach((campo) => campo.unmount());

    return { consultar, gerar_token, desmontar };
};
