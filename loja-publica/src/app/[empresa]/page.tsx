import { api } from '@/lib/api';
import Catalogo from '@/components/Catalogo';

export default async function PaginaLoja({ params }: { params: Promise<{ empresa: string }> }) {
    const { empresa } = await params;

    const [info, produtos] = await Promise.all([api.empresaInfo(empresa), api.produtos(empresa)]);

    const agenda = info.modulo_agendamento_ativo ? await api.agenda(empresa) : [];

    return <Catalogo produtos={produtos} agenda={agenda} moduloAgendamentoAtivo={info.modulo_agendamento_ativo} />;
}
