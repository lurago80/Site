'use client';

import Link from 'next/link';
import { useCarrinho } from '@/lib/cart';
import type { EmpresaInfo } from '@/lib/types';

export default function Header({ empresa, info }: { empresa: string; info: EmpresaInfo }) {
    const { itens } = useCarrinho();
    const quantidadeTotal = itens.reduce((soma, item) => soma + item.quantidade, 0);

    return (
        <header
            style={{
                position: 'sticky',
                top: 0,
                zIndex: 10,
                background: 'rgba(255,255,255,.9)',
                backdropFilter: 'blur(8px)',
                borderBottom: '1px solid var(--cor-borda)',
            }}
        >
            <div
                className="container"
                style={{
                    padding: '14px 20px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 12,
                }}
            >
                <Link href={`/${empresa}`} style={{ display: 'flex', alignItems: 'center', gap: 10, textDecoration: 'none' }}>
                    {info.logo_url ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img
                            src={info.logo_url}
                            alt={info.razao_social}
                            style={{ height: 36, width: 36, borderRadius: 8, objectFit: 'cover' }}
                        />
                    ) : (
                        <div
                            style={{
                                height: 36,
                                width: 36,
                                borderRadius: 8,
                                background: 'var(--cor-primaria)',
                                color: '#fff',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                fontWeight: 700,
                                fontSize: 15,
                            }}
                        >
                            {info.razao_social.charAt(0).toUpperCase()}
                        </div>
                    )}
                    <strong style={{ fontSize: 16, color: 'var(--cor-texto)', letterSpacing: '-.01em' }}>{info.razao_social}</strong>
                </Link>

                <Link
                    href={`/${empresa}/carrinho`}
                    className="botao-secundario"
                    style={{ textDecoration: 'none', display: 'flex', alignItems: 'center', gap: 8 }}
                >
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <circle cx="9" cy="21" r="1" />
                        <circle cx="20" cy="21" r="1" />
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                    </svg>
                    Carrinho
                    {quantidadeTotal > 0 && (
                        <span
                            style={{
                                background: 'var(--cor-primaria)',
                                color: '#fff',
                                borderRadius: 999,
                                fontSize: 11,
                                fontWeight: 700,
                                minWidth: 18,
                                height: 18,
                                display: 'inline-flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                padding: '0 5px',
                            }}
                        >
                            {quantidadeTotal}
                        </span>
                    )}
                </Link>
            </div>
        </header>
    );
}
