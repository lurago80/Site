import { api } from '@/lib/api';
import Catalogo from '@/components/Catalogo';

export default async function PaginaLoja({ params }: { params: Promise<{ empresa: string }> }) {
    const { empresa } = await params;

    const [info, produtos] = await Promise.all([api.empresaInfo(empresa), api.produtos(empresa)]);

    const agenda = info.modulo_agendamento_ativo ? await api.agenda(empresa) : [];

    return (
        <div>
            <div
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'center',
                    textAlign: 'center',
                    gap: 10,
                    padding: '36px 20px 40px',
                    marginBottom: 32,
                    borderRadius: 'var(--raio)',
                    background: 'linear-gradient(135deg, var(--cor-primaria-clara), var(--cor-superficie))',
                    border: '1px solid var(--cor-borda)',
                }}
            >
                {info.logo_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={info.logo_url}
                        alt={info.nome_fantasia}
                        style={{ height: 64, width: 64, borderRadius: 14, objectFit: 'cover', boxShadow: 'var(--sombra-md)' }}
                    />
                ) : (
                    <div
                        style={{
                            height: 64,
                            width: 64,
                            borderRadius: 14,
                            background: 'var(--cor-primaria)',
                            color: '#fff',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontWeight: 700,
                            fontSize: 26,
                            boxShadow: 'var(--sombra-md)',
                        }}
                    >
                        {info.nome_fantasia.charAt(0).toUpperCase()}
                    </div>
                )}
                <h1 style={{ fontSize: 28, margin: 0, letterSpacing: '-.02em' }}>{info.nome_fantasia}</h1>
                {info.segmento && (
                    <span
                        style={{
                            fontSize: 12.5,
                            fontWeight: 600,
                            color: 'var(--cor-primaria)',
                            background: 'var(--cor-superficie)',
                            border: '1px solid var(--cor-borda)',
                            padding: '4px 12px',
                            borderRadius: 999,
                        }}
                    >
                        {info.segmento}
                    </span>
                )}
            </div>
            <Catalogo produtos={produtos} agenda={agenda} moduloAgendamentoAtivo={info.modulo_agendamento_ativo} />
        </div>
    );
}
