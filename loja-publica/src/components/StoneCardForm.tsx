'use client';

import { useEffect, useId, useState } from 'react';

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
    onToken: (token: string, tipo: 'credito' | 'debito', parcelas: number) => void;
    onErro: (mensagem: string) => void;
}) {
    const formId = useId().replace(/:/g, '');
    const [tipo, setTipo] = useState<'credito' | 'debito'>('credito');
    const [parcelas, setParcelas] = useState(1);

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
                            onToken(token, tipo, tipo === 'debito' ? 1 : parcelas);
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
    }, [publicKey, tipo, parcelas]);

    return (
        <form id={formId} data-pagarmecheckout-form style={{ display: 'grid', gap: 10 }}>
            <div style={{ display: 'flex', gap: 12 }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
                    <input type="radio" style={{ width: 'auto' }} checked={tipo === 'credito'} onChange={() => setTipo('credito')} />
                    Crédito
                </label>
                <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
                    <input type="radio" style={{ width: 'auto' }} checked={tipo === 'debito'} onChange={() => setTipo('debito')} />
                    Débito
                </label>
            </div>

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

            {tipo === 'credito' && (
                <div>
                    <label>Parcelas</label>
                    <select value={parcelas} onChange={(e) => setParcelas(Number(e.target.value))} style={{ width: 120 }}>
                        {Array.from({ length: 12 }, (_, i) => i + 1).map((n) => (
                            <option key={n} value={n}>{n}x</option>
                        ))}
                    </select>
                </div>
            )}

            <button type="submit" className="botao-secundario">Confirmar cartão</button>
        </form>
    );
}
