import { notFound } from 'next/navigation';
import { api, ErroApi } from '@/lib/api';
import { CarrinhoProvider } from '@/lib/cart';
import Header from '@/components/Header';

export default async function EmpresaLayout({
    children,
    params,
}: {
    children: React.ReactNode;
    params: Promise<{ empresa: string }>;
}) {
    const { empresa } = await params;

    let info;
    try {
        info = await api.empresaInfo(empresa);
    } catch (erro) {
        if (erro instanceof ErroApi && erro.status === 404) {
            notFound();
        }
        throw erro;
    }

    const corPrimaria = info.cor_primaria || '#394285';

    return (
        <div style={{ ['--cor-primaria' as string]: corPrimaria, minHeight: '100vh' }}>
            <CarrinhoProvider empresa={empresa}>
                <Header empresa={empresa} info={info} />
                <main style={{ maxWidth: 960, margin: '0 auto', padding: '20px' }}>{children}</main>
            </CarrinhoProvider>
        </div>
    );
}
