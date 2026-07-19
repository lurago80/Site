'use client';

import { useEffect, useRef } from 'react';

declare global {
    interface Window {
        MercadoPago?: new (publicKey: string) => {
            bricks: () => {
                create: (
                    tipo: string,
                    containerId: string,
                    settings: Record<string, unknown>,
                ) => Promise<{ unmount: () => void }>;
            };
        };
    }
}

interface DadosCartao {
    token: string;
    installments: number;
    payment_type_id: string; // 'credit_card' | 'debit_card'
}

/**
 * Card Payment Brick do Mercado Pago - tokeniza o cartão direto no
 * navegador do cliente (o número do cartão nunca passa pelo nosso
 * backend, exigência de segurança PCI-DSS). Documentação:
 * https://www.mercadopago.com.br/developers/pt/docs/checkout-bricks/card-payment-brick/introduction
 */
export default function CardBrick({
    publicKey,
    valor,
    onToken,
    onErro,
}: {
    publicKey: string;
    valor: number;
    onToken: (dados: DadosCartao) => void;
    onErro: (mensagem: string) => void;
}) {
    const containerId = useRef(`card-brick-${Math.random().toString(36).slice(2)}`);
    const brickRef = useRef<{ unmount: () => void } | null>(null);

    useEffect(() => {
        let cancelado = false;

        async function iniciar() {
            if (!window.MercadoPago) {
                const script = document.createElement('script');
                script.src = 'https://sdk.mercadopago.com/js/v2';
                script.async = true;
                document.body.appendChild(script);
                await new Promise((resolve) => {
                    script.onload = resolve;
                });
            }

            if (cancelado || !window.MercadoPago) return;

            const mp = new window.MercadoPago(publicKey);

            brickRef.current = await mp.bricks().create('cardPayment', containerId.current, {
                initialization: { amount: valor },
                callbacks: {
                    onReady: () => {},
                    onSubmit: (formData: DadosCartao) => {
                        onToken(formData);
                        return Promise.resolve();
                    },
                    onError: (erro: unknown) => {
                        onErro(
                            erro instanceof Error
                                ? erro.message
                                : 'Não foi possível processar os dados do cartão. Confira os campos e tente de novo.',
                        );
                    },
                },
            });
        }

        iniciar();

        return () => {
            cancelado = true;
            brickRef.current?.unmount();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [publicKey, valor]);

    return <div id={containerId.current} />;
}
