'use client';

import { use } from 'react';
import Link from 'next/link';
import { useCarrinho } from '@/lib/cart';

export default function PaginaCarrinho({ params }: { params: Promise<{ empresa: string }> }) {
    const { empresa } = use(params);
    const { itens, total, removerItem } = useCarrinho();

    if (itens.length === 0) {
        return (
            <div>
                <h1 style={{ fontSize: 20 }}>Carrinho</h1>
                <p style={{ color: 'var(--cor-texto-suave)' }}>Seu carrinho está vazio.</p>
                <Link href={`/${empresa}`} className="botao-primario" style={{ display: 'inline-block', textDecoration: 'none' }}>
                    Voltar à loja
                </Link>
            </div>
        );
    }

    return (
        <div>
            <h1 style={{ fontSize: 20 }}>Carrinho</h1>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 20 }}>
                {itens.map((item, index) => (
                    <div
                        key={index}
                        className="cartao"
                        style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}
                    >
                        <div>
                            <strong style={{ fontSize: 14 }}>{item.nome}</strong>
                            <div style={{ fontSize: 12, color: 'var(--cor-texto-suave)' }}>
                                {item.quantidade} × R$ {item.valorUnitario.toFixed(2)}
                            </div>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                            <strong>R$ {(item.quantidade * item.valorUnitario).toFixed(2)}</strong>
                            <button className="botao-secundario" onClick={() => removerItem(index)}>
                                Remover
                            </button>
                        </div>
                    </div>
                ))}
            </div>
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    fontSize: 18,
                    fontWeight: 700,
                    marginBottom: 20,
                }}
            >
                <span>Total</span>
                <span>R$ {total.toFixed(2)}</span>
            </div>
            <Link
                href={`/${empresa}/checkout`}
                className="botao-primario"
                style={{ display: 'inline-block', textDecoration: 'none' }}
            >
                Continuar para o checkout
            </Link>
        </div>
    );
}
