import type {
    ConfigPagamentoPublica,
    EmpresaInfo,
    HorarioAgenda,
    Produto,
    RespostaCheckout,
} from './types';

const BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

class ErroApi extends Error {
    constructor(
        message: string,
        public status: number,
        public erros?: Record<string, string[]>,
    ) {
        super(message);
    }
}

async function requisitar<T>(caminho: string, opcoes?: RequestInit): Promise<T> {
    const resposta = await fetch(`${BASE_URL}${caminho}`, {
        ...opcoes,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...opcoes?.headers },
        cache: 'no-store',
    });

    const corpo = await resposta.json().catch(() => null);

    if (!resposta.ok) {
        throw new ErroApi(corpo?.message || 'Não foi possível completar a operação.', resposta.status, corpo?.errors);
    }

    return corpo as T;
}

export const api = {
    empresaInfo: (empresa: string) => requisitar<EmpresaInfo>(`/loja/${empresa}/info`),

    configPagamentoPublica: (empresa: string) =>
        requisitar<ConfigPagamentoPublica>(`/loja/${empresa}/config-pagamento-publica`),

    produtos: (empresa: string, busca?: string) =>
        requisitar<Produto[]>(`/loja/${empresa}/produtos${busca ? `?busca=${encodeURIComponent(busca)}` : ''}`),

    agenda: (empresa: string) => requisitar<HorarioAgenda[]>(`/loja/${empresa}/agenda`),

    criarReserva: (empresa: string, dados: { agenda_visitacao_id: number; quantidade: number }) =>
        requisitar<{ reserva_id: number; quantidade: number; expira_em: string; status: string }>(
            `/loja/${empresa}/reservas`,
            { method: 'POST', body: JSON.stringify(dados) },
        ),

    checkout: (empresa: string, dados: Record<string, unknown>) =>
        requisitar<RespostaCheckout>(`/loja/${empresa}/checkout`, {
            method: 'POST',
            body: JSON.stringify(dados),
        }),
};

export { ErroApi };
