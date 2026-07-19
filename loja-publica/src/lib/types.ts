export interface EmpresaInfo {
    razao_social: string;
    segmento: string | null;
    logo_url: string | null;
    cor_primaria: string | null;
    modulo_agendamento_ativo: boolean;
}

export interface Produto {
    id: number;
    nome: string;
    descricao: string | null;
    preco_venda: string;
    estoque_atual: number | null;
}

export interface HorarioAgenda {
    id: number;
    data_hora: string;
    vagas_disponiveis: number;
    valor_visita: string;
}

export interface ConfigPagamentoPublica {
    gateway: string | null;
    public_key: string | null;
}

export interface ItemCarrinhoProduto {
    tipo: 'produto';
    produtoId: number;
    nome: string;
    quantidade: number;
    valorUnitario: number;
}

export interface ItemCarrinhoAgenda {
    tipo: 'agenda';
    agendaId: number;
    nome: string;
    quantidade: number;
    valorUnitario: number;
}

export type ItemCarrinho = ItemCarrinhoProduto | ItemCarrinhoAgenda;

export interface Cobranca {
    status: string;
    qr_code: string | null;
    qr_code_base64: string | null;
    expira_em: string | null;
}

export interface RespostaCheckout {
    id: number;
    valor_total: string;
    status_pagamento: string;
    cobranca: Cobranca | null;
}
