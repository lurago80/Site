'use client';

import { use, useEffect, useState } from 'react';
import Link from 'next/link';
import { api, ErroApi } from '@/lib/api';
import type { PedidoPublico } from '@/lib/types';

export default function PaginaRecibo({ params }: { params: Promise<{ empresa: string; id: string }> }) {
    const { empresa, id } = use(params);

    const [pedido, setPedido] = useState<PedidoPublico | null>(null);
    const [erro, setErro] = useState<string | null>(null);
    const [carregando, setCarregando] = useState(true);
    const [copiado, setCopiado] = useState(false);

    useEffect(() => {
        api.pedido(empresa, Number(id))
            .then(setPedido)
            .catch((e) => setErro(e instanceof ErroApi ? e.message : 'Não foi possível carregar o pedido.'))
            .finally(() => setCarregando(false));
    }, [empresa, id]);

    async function compartilhar() {
        const url = window.location.href;

        if (navigator.share) {
            try {
                await navigator.share({ title: `Pedido #${id}`, url });
            } catch {
                // Cliente cancelou o compartilhamento - não é um erro a reportar.
            }
            return;
        }

        await navigator.clipboard.writeText(url);
        setCopiado(true);
        setTimeout(() => setCopiado(false), 2500);
    }

    if (carregando) {
        return <p style={{ color: 'var(--cor-texto-suave)' }}>Carregando recibo...</p>;
    }

    if (erro || !pedido) {
        return (
            <div>
                <p className="msg-erro">{erro || 'Pedido não encontrado.'}</p>
                <Link href={`/${empresa}`} className="botao-secundario" style={{ display: 'inline-block', textDecoration: 'none', marginTop: 12 }}>
                    Voltar à loja
                </Link>
            </div>
        );
    }

    const pago = pedido.status_pagamento === 'pago';
    const itensProduto = pedido.itens.filter((i) => i.produto);
    const itensVisita = pedido.itens.filter((i) => i.agenda_visitacao);
    const dataFormatada = new Date(pedido.data_venda).toLocaleString('pt-BR');

    return (
        <div style={{ maxWidth: 480, margin: '0 auto' }}>
            <div className="sem-impressao" style={{ display: 'flex', gap: 10, marginBottom: 16 }}>
                <button className="botao-secundario" onClick={() => window.print()} style={{ flex: 1 }}>
                    Imprimir
                </button>
                <button className="botao-secundario" onClick={compartilhar} style={{ flex: 1 }}>
                    {copiado ? 'Link copiado!' : 'Compartilhar'}
                </button>
            </div>

            <div className="cartao" style={{ padding: 24 }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 18, paddingBottom: 18, borderBottom: '1px solid var(--cor-borda)' }}>
                    {pedido.empresa.logo_url ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img
                            src={pedido.empresa.logo_url}
                            alt={pedido.empresa.nome_fantasia}
                            style={{ height: 48, width: 48, borderRadius: 8, objectFit: 'cover' }}
                        />
                    ) : (
                        <div
                            style={{
                                height: 48, width: 48, borderRadius: 8, background: 'var(--cor-primaria)', color: '#fff',
                                display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700, fontSize: 20,
                            }}
                        >
                            {pedido.empresa.nome_fantasia.charAt(0).toUpperCase()}
                        </div>
                    )}
                    <div>
                        <strong style={{ fontSize: 16 }}>{pedido.empresa.nome_fantasia}</strong>
                        <p style={{ fontSize: 12, color: 'var(--cor-texto-suave)', margin: '2px 0 0' }}>Comprovante de pedido</p>
                    </div>
                </div>

                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
                    <div>
                        <p style={{ fontSize: 20, fontWeight: 700, margin: 0 }}>Pedido #{pedido.id}</p>
                        <p style={{ fontSize: 12, color: 'var(--cor-texto-suave)', margin: '2px 0 0' }}>{dataFormatada}</p>
                        {pedido.cliente_primeiro_nome && (
                            <p style={{ fontSize: 12, color: 'var(--cor-texto-suave)', margin: '2px 0 0' }}>Cliente: {pedido.cliente_primeiro_nome}</p>
                        )}
                    </div>
                    <span
                        className={pago ? 'msg-ok' : 'msg-erro'}
                        style={{
                            padding: '4px 12px', borderRadius: 999, fontSize: 11, fontWeight: 700,
                            textTransform: 'uppercase', letterSpacing: '.03em',
                        }}
                    >
                        {pago ? 'Pago' : pedido.status_pagamento}
                    </span>
                </div>

                {itensProduto.length > 0 && (
                    <>
                        <h3 style={{ fontSize: 11, textTransform: 'uppercase', letterSpacing: '.03em', color: 'var(--cor-texto-suave)', marginBottom: 8 }}>
                            Produtos
                        </h3>
                        {itensProduto.map((item) => (
                            <div key={item.id} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13.5, marginBottom: 6 }}>
                                <span>
                                    {item.quantidade}x {item.produto?.nome}
                                    {item.produto_variacao ? ` (${item.produto_variacao.tamanho})` : ''}
                                </span>
                                <span>R$ {Number(item.valor_total).toFixed(2)}</span>
                            </div>
                        ))}
                    </>
                )}

                {itensVisita.length > 0 && (
                    <>
                        <h3 style={{ fontSize: 11, textTransform: 'uppercase', letterSpacing: '.03em', color: 'var(--cor-texto-suave)', marginTop: itensProduto.length ? 14 : 0, marginBottom: 8 }}>
                            Visitas agendadas
                        </h3>
                        {itensVisita.map((item) => (
                            <div key={item.id} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13.5, marginBottom: 6 }}>
                                <span>
                                    {item.quantidade}x visita
                                    {item.agenda_visitacao ? ` - ${new Date(item.agenda_visitacao.data_hora).toLocaleString('pt-BR')}` : ''}
                                </span>
                                <span>R$ {Number(item.valor_total).toFixed(2)}</span>
                            </div>
                        ))}
                        <p style={{ fontSize: 11.5, color: 'var(--cor-texto-suave)', marginTop: 8 }}>
                            Apresente este comprovante (impresso ou no celular) na chegada da visita.
                        </p>
                    </>
                )}

                {pedido.valor_desconto && Number(pedido.valor_desconto) > 0 && (
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13.5, marginTop: 10, color: 'var(--cor-ok-texto)' }}>
                        <span>Desconto do cupom</span>
                        <span>- R$ {Number(pedido.valor_desconto).toFixed(2)}</span>
                    </div>
                )}

                <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 700, fontSize: 17, marginTop: 14, paddingTop: 14, borderTop: '1px solid var(--cor-borda)' }}>
                    <span>Total</span>
                    <span>R$ {Number(pedido.valor_total).toFixed(2)}</span>
                </div>
            </div>

            <div className="sem-impressao" style={{ marginTop: 16 }}>
                <Link href={`/${empresa}`} className="botao-secundario" style={{ display: 'inline-block', textDecoration: 'none' }}>
                    Voltar à loja
                </Link>
            </div>
        </div>
    );
}
