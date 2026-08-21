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
        <div style={{ ['--cor-primaria' as string]: corPrimaria, minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
            <CarrinhoProvider empresa={empresa}>
                <Header empresa={empresa} info={info} />
                <main className="container" style={{ flex: 1, padding: '28px 20px 60px' }}>
                    {children}
                </main>
            </CarrinhoProvider>
        </div>
    );
}
