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
                background: '#fff',
                borderBottom: '1px solid var(--cor-borda)',
                padding: '14px 20px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
            }}
        >
            <Link href={`/${empresa}`} style={{ display: 'flex', alignItems: 'center', gap: 10, textDecoration: 'none' }}>
                {info.logo_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img src={info.logo_url} alt={info.razao_social} style={{ height: 36, borderRadius: 4 }} />
                ) : null}
                <strong style={{ fontSize: 16, color: 'var(--cor-texto)' }}>{info.razao_social}</strong>
            </Link>
            <Link href={`/${empresa}/carrinho`} className="botao-secundario" style={{ textDecoration: 'none' }}>
                Carrinho {quantidadeTotal > 0 ? `(${quantidadeTotal})` : ''}
            </Link>
        </header>
    );
}
