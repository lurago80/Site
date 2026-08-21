'use client';

import { useEffect, useId, useRef } from 'react';

declare global {
    interface Window {
        PagarmeCheckout?: {
            init: (
                success: (data: { pagarmetoken?: string; token?: string }) => void,
                fail: (error: unknown) => void,
            ) => void;
        };
    }
}

/**
 * Tokenização de cartão da Stone (via Pagar.me tokenizecard.js) - SDK
 * bem diferente do Mercado Pago Bricks (ver CardBrick.tsx): em vez de
 * uma API JS imperativa, ele intercepta o submit de um <form> marcado
 * com atributos data-pagarmecheckout-* e injeta o token no callback
 * de sucesso. Documentação: https://docs.pagar.me/reference/pagarme-js
 *
 * IMPORTANTE: implementado a partir da documentação oficial, ainda sem
 * validação end-to-end contra o sandbox real da Stone - testar o fluxo
 * completo com uma conta de teste antes de liberar para clientes reais.
 */
export default function StoneCardForm({
    publicKey,
    onToken,
    onErro,
}: {
    publicKey: string;
    onToken: (token: string) => void;
    onErro: (mensagem: string) => void;
}) {
    const formId = useId().replace(/:/g, '');
    const formRef = useRef<HTMLFormElement>(null);

    useEffect(() => {
        let cancelado = false;

        function carregarScript(): Promise<void> {
            if (window.PagarmeCheckout) return Promise.resolve();

            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://checkout.pagar.me/v1/tokenizecard.js';
                script.setAttribute('data-pagarmecheckout-app-id', publicKey);
                script.async = true;
                script.onload = () => resolve();
                script.onerror = () => reject(new Error('Falha ao carregar o script de pagamento da Stone.'));
                document.body.appendChild(script);
            });
        }

        carregarScript()
            .then(() => {
                if (cancelado || !window.PagarmeCheckout) return;

                window.PagarmeCheckout.init(
                    (data) => {
                        const token = data.pagarmetoken || data.token;
                        if (token) {
                            onToken(token);
                        } else {
                            onErro('Não foi possível gerar o token do cartão.');
                        }
                    },
                    (erro) => {
                        onErro(
                            erro instanceof Error
                                ? erro.message
                                : 'Não foi possível processar os dados do cartão. Confira os campos e tente de novo.',
                        );
                    },
                );
            })
            .catch((erro) => onErro(erro.message));

        return () => {
            cancelado = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [publicKey]);

    return (
        <form ref={formRef} id={formId} data-pagarmecheckout-form style={{ display: 'grid', gap: 10 }}>
            <div>
                <label>Nome no cartão</label>
                <input data-pagarmecheckout-element="holder_name" required />
            </div>
            <div>
                <label>Número do cartão</label>
                <input data-pagarmecheckout-element="number" inputMode="numeric" required />
            </div>
            <div style={{ display: 'flex', gap: 10 }}>
                <div style={{ flex: 1 }}>
                    <label>Mês de validade</label>
                    <input data-pagarmecheckout-element="exp_month" inputMode="numeric" placeholder="MM" required />
                </div>
                <div style={{ flex: 1 }}>
                    <label>Ano de validade</label>
                    <input data-pagarmecheckout-element="exp_year" inputMode="numeric" placeholder="AA" required />
                </div>
                <div style={{ flex: 1 }}>
                    <label>CVV</label>
                    <input data-pagarmecheckout-element="cvv" inputMode="numeric" required />
                </div>
            </div>
            <button type="submit" className="botao-secundario">Confirmar cartão</button>
        </form>
    );
}
