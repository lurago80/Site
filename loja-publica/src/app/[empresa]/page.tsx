import { api } from '@/lib/api';
import Catalogo from '@/components/Catalogo';

export default async function PaginaLoja({ params }: { params: Promise<{ empresa: string }> }) {
    const { empresa } = await params;

    const [info, produtos] = await Promise.all([api.empresaInfo(empresa), api.produtos(empresa)]);

    const agenda = info.modulo_agendamento_ativo ? await api.agenda(empresa) : [];

    return (
        <div>
            <div style={{ marginBottom: 32 }}>
                <h1 style={{ fontSize: 26, margin: 0, letterSpacing: '-.02em' }}>{info.razao_social}</h1>
                {info.segmento && (
                    <p style={{ fontSize: 14, color: 'var(--cor-texto-suave)', marginTop: 6 }}>{info.segmento}</p>
                )}
            </div>
            <Catalogo produtos={produtos} agenda={agenda} moduloAgendamentoAtivo={info.modulo_agendamento_ativo} />
        </div>
    );
}
