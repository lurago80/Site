export interface EmpresaInfo {
    razao_social: string;
    nome_fantasia: string;
    segmento: string | null;
    logo_url: string | null;
    cor_primaria: string | null;
    modulo_agendamento_ativo: boolean;
}

export interface ProdutoVariacao {
    id: number;
    tamanho: string;
    estoque_atual: number;
    ativo: boolean;
}

export interface KitGrupoEscolha {
    produto_id: number;
    nome: string;
    quantidade: number;
    variacoes: { id: number; tamanho: string; estoque_atual: number }[];
}

export interface KitInfo {
    fixos: { produto_id: number; nome: string; quantidade: number }[];
    escolhas: KitGrupoEscolha[];
    disponivel: boolean;
}

export interface Produto {
    id: number;
    nome: string;
    descricao: string | null;
    preco_venda: string;
    estoque_atual: number | null;
    imagem_url: string | null;
    quantidade_minima_venda: number | null;
    variacoes?: ProdutoVariacao[];
    eh_kit?: boolean;
    kit?: KitInfo;
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

export interface CotacaoFrete {
    entrega: {
        disponivel: boolean;
        valor: number;
        gratis: boolean;
        prazo_dias: number | null;
        mensagem: string | null;
    };
    frete_gratis_acima: string | null;
    permite_retirada: boolean;
    instrucoes_retirada: string | null;
}

export interface ItemCarrinhoProduto {
    tipo: 'produto';
    produtoId: number;
    variacaoId?: number | null;
    tamanho?: string | null;
    nome: string;
    quantidade: number;
    valorUnitario: number;
    quantidadeMinima?: number | null;
    // Só em kit: o que vem fixo e o que o cliente escolheu (sempre 1 kit por linha).
    itensFixos?: string[];
    escolhas?: { variacaoId: number; rotulo: string; quantidade: number }[];
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

export interface ItemVendaResposta {
    id: number;
    quantidade: number;
    valor_unitario: string;
    valor_total: string;
    produto: { nome: string } | null;
    produto_variacao: { tamanho: string } | null;
    agenda_visitacao: { data_hora: string } | null;
    composicao?: { nome: string; tamanho: string | null; quantidade: number }[] | null;
}

export interface RespostaCheckout {
    id: number;
    valor_total: string;
    valor_desconto: string | null;
    valor_frete: string | null;
    tipo_entrega: 'entrega' | 'retirada' | null;
    status_pagamento: string;
    cobranca: Cobranca | null;
    itens: ItemVendaResposta[];
}

export interface PedidoPublico {
    id: number;
    status_pagamento: string;
    valor_total: string;
    valor_desconto: string | null;
    valor_frete: string | null;
    tipo_entrega: 'entrega' | 'retirada' | null;
    data_venda: string;
    cliente_primeiro_nome: string | null;
    itens: ItemVendaResposta[];
    empresa: {
        nome_fantasia: string;
        logo_url: string | null;
        cor_primaria: string | null;
    };
}

export interface CupomValidado {
    valido: boolean;
    codigo?: string;
    tipo?: 'percentual' | 'valor_fixo';
    valor_desconto?: number;
    mensagem?: string;
}
