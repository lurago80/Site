'use client';

import { use } from 'react';
import Link from 'next/link';
import { useCarrinho } from '@/lib/cart';

export default function PaginaCarrinho({ params }: { params: Promise<{ empresa: string }> }) {
    const { empresa } = use(params);
    const { itens, total, removerItem } = useCarrinho();

    if (itens.length === 0) {
        return (
            <div style={{ textAlign: 'center', padding: '60px 20px' }}>
                <h1 style={{ fontSize: 22, marginBottom: 8 }}>Carrinho</h1>
                <p style={{ color: 'var(--cor-texto-suave)', marginBottom: 20 }}>Seu carrinho está vazio.</p>
                <Link href={`/${empresa}`} className="botao-primario" style={{ display: 'inline-block', textDecoration: 'none' }}>
                    Voltar à loja
                </Link>
            </div>
        );
    }

    return (
        <div style={{ maxWidth: 560 }}>
            <h1 style={{ fontSize: 22, marginBottom: 20 }}>Carrinho</h1>

            <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginBottom: 24 }}>
                {itens.map((item, index) => (
                    <div
                        key={index}
                        className="cartao"
                        style={{ display: 'flex', alignItems: 'center', gap: 14 }}
                    >
                        <div style={{ minWidth: 0, flex: 1 }}>
                            <strong style={{ fontSize: 14, display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                {item.nome}
                            </strong>
                            <div style={{ fontSize: 12.5, color: 'var(--cor-texto-suave)', marginTop: 3 }}>
                                {item.quantidade} × R$ {item.valorUnitario.toFixed(2)}
                            </div>
                        </div>

                        <strong style={{ fontSize: 15, whiteSpace: 'nowrap', flexShrink: 0 }}>
                            R$ {(item.quantidade * item.valorUnitario).toFixed(2)}
                        </strong>

                        <button
                            className="botao-secundario"
                            onClick={() => removerItem(index)}
                            style={{ flexShrink: 0, padding: '8px 10px' }}
                            aria-label={`Remover ${item.nome}`}
                            title="Remover"
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M3 6h18" />
                                <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                <line x1="10" y1="11" x2="10" y2="17" />
                                <line x1="14" y1="11" x2="14" y2="17" />
                            </svg>
                        </button>
                    </div>
                ))}
            </div>

            <div className="cartao" style={{ marginBottom: 20 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                    <span style={{ fontSize: 15, color: 'var(--cor-texto-suave)' }}>Total</span>
                    <span style={{ fontSize: 22, fontWeight: 700 }}>R$ {total.toFixed(2)}</span>
                </div>
            </div>

            <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                <Link
                    href={`/${empresa}/checkout`}
                    className="botao-primario"
                    style={{ display: 'inline-block', textDecoration: 'none', flex: 1, textAlign: 'center' }}
                >
                    Continuar para o checkout
                </Link>
                <Link
                    href={`/${empresa}`}
                    className="botao-secundario"
                    style={{ display: 'inline-flex', alignItems: 'center', textDecoration: 'none' }}
                >
                    Voltar à loja
                </Link>
            </div>
        </div>
    );
}
