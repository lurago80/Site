'use client';

import { use, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useCarrinho } from '@/lib/cart';
import { api, ErroApi } from '@/lib/api';
import type { ConfigPagamentoPublica, RespostaCheckout } from '@/lib/types';
import CardBrick from '@/components/CardBrick';

export default function PaginaCheckout({ params }: { params: Promise<{ empresa: string }> }) {
    const { empresa } = use(params);
    const router = useRouter();
    const { itens, total, limpar } = useCarrinho();

    const [nome, setNome] = useState('');
    const [cpfCnpj, setCpfCnpj] = useState('');
    const [email, setEmail] = useState('');
    const [telefone, setTelefone] = useState('');
    const [lgpd, setLgpd] = useState(false);
    const [formaPagamento, setFormaPagamento] = useState<'pix' | 'cartao'>('pix');

    const [configPagamento, setConfigPagamento] = useState<ConfigPagamentoPublica | null>(null);
    const [enviando, setEnviando] = useState(false);
    const [erro, setErro] = useState<string | null>(null);
    const [resultado, setResultado] = useState<RespostaCheckout | null>(null);
    const [dadosCartao, setDadosCartao] = useState<{ token: string; installments: number; payment_type_id: string } | null>(
        null,
    );

    useEffect(() => {
        api.configPagamentoPublica(empresa).then(setConfigPagamento).catch(() => setConfigPagamento({ gateway: null, public_key: null }));
    }, [empresa]);

    if (itens.length === 0 && !resultado) {
        return (
            <div>
                <h1 style={{ fontSize: 20 }}>Checkout</h1>
                <p style={{ color: 'var(--cor-texto-suave)' }}>Seu carrinho está vazio.</p>
                <Link href={`/${empresa}`} className="botao-primario" style={{ display: 'inline-block', textDecoration: 'none' }}>
                    Voltar à loja
                </Link>
            </div>
        );
    }

    if (resultado) {
        return <TelaConfirmacao empresa={empresa} resultado={resultado} />;
    }

    const aceitaCartaoOnline = configPagamento?.gateway === 'mercadopago' && !!configPagamento.public_key;

    async function finalizarPedido() {
        setErro(null);

        if (!nome.trim() || !lgpd) {
            setErro('Preencha seu nome e aceite o termo de consentimento para continuar.');
            return;
        }

        if (formaPagamento === 'cartao' && aceitaCartaoOnline && !dadosCartao) {
            setErro('Preencha os dados do cartão acima antes de finalizar.');
            return;
        }

        setEnviando(true);

        try {
            const agendaItem = itens.find((i) => i.tipo === 'agenda');
            const produtosItens = itens.filter((i) => i.tipo === 'produto');

            let reservaId: number | null = null;
            if (agendaItem && agendaItem.tipo === 'agenda') {
                const reserva = await api.criarReserva(empresa, {
                    agenda_visitacao_id: agendaItem.agendaId,
                    quantidade: agendaItem.quantidade,
                });
                reservaId = reserva.reserva_id;
            }

            const payload: Record<string, unknown> = {
                cliente: {
                    nome,
                    cpf_cnpj: cpfCnpj || null,
                    email: email || null,
                    telefone: telefone || null,
                    consentimento_lgpd: lgpd,
                },
                itens: produtosItens.map((i) => (i.tipo === 'produto' ? { produto_id: i.produtoId, quantidade: i.quantidade } : null)),
                reserva_id: reservaId,
                forma_pagamento: formaPagamento,
            };

            if (formaPagamento === 'cartao' && dadosCartao) {
                payload.cartao_token = dadosCartao.token;
                payload.cartao_parcelas = dadosCartao.installments;
                payload.cartao_metodo = dadosCartao.payment_type_id === 'debit_card' ? 'cartao_debito' : 'cartao_credito';
            }

            const resposta = await api.checkout(empresa, payload);
            setResultado(resposta);
            limpar();
        } catch (e) {
            setErro(e instanceof ErroApi ? e.message : 'Não foi possível finalizar o pedido. Tente novamente.');
        } finally {
            setEnviando(false);
        }
    }

    return (
        <div>
            <h1 style={{ fontSize: 20 }}>Finalizar pedido</h1>

            <div className="cartao" style={{ marginBottom: 16 }}>
                <h2 style={{ fontSize: 14, marginTop: 0 }}>Seus dados</h2>
                <div style={{ display: 'grid', gap: 10 }}>
                    <div>
                        <label>Nome completo</label>
                        <input value={nome} onChange={(e) => setNome(e.target.value)} required />
                    </div>
                    <div>
                        <label>CPF/CNPJ (opcional)</label>
                        <input value={cpfCnpj} onChange={(e) => setCpfCnpj(e.target.value)} />
                    </div>
                    <div>
                        <label>E-mail (opcional)</label>
                        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
                    </div>
                    <div>
                        <label>Telefone (opcional)</label>
                        <input value={telefone} onChange={(e) => setTelefone(e.target.value)} />
                    </div>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                        <input type="checkbox" style={{ width: 'auto' }} checked={lgpd} onChange={(e) => setLgpd(e.target.checked)} />
                        Concordo com o uso dos meus dados para esta compra, conforme a política de privacidade.
                    </label>
                </div>
            </div>

            <div className="cartao" style={{ marginBottom: 16 }}>
                <h2 style={{ fontSize: 14, marginTop: 0 }}>Forma de pagamento</h2>
                <div style={{ display: 'flex', gap: 12, marginBottom: 12 }}>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
                        <input
                            type="radio"
                            style={{ width: 'auto' }}
                            checked={formaPagamento === 'pix'}
                            onChange={() => setFormaPagamento('pix')}
                        />
                        Pix
                    </label>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
                        <input
                            type="radio"
                            style={{ width: 'auto' }}
                            checked={formaPagamento === 'cartao'}
                            onChange={() => setFormaPagamento('cartao')}
                        />
                        Cartão
                    </label>
                </div>

                {formaPagamento === 'cartao' && (
                    aceitaCartaoOnline && configPagamento?.public_key ? (
                        <CardBrick
                            publicKey={configPagamento.public_key}
                            valor={total}
                            onToken={setDadosCartao}
                            onErro={setErro}
                        />
                    ) : (
                        <p style={{ fontSize: 12, color: 'var(--cor-texto-suave)' }}>
                            Pagamento online por cartão ainda não está disponível para esta loja - escolha Pix.
                        </p>
                    )
                )}
            </div>

            {erro && <p className="msg-erro" style={{ marginBottom: 12 }}>{erro}</p>}

            <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 700, fontSize: 16, marginBottom: 16 }}>
                <span>Total</span>
                <span>R$ {total.toFixed(2)}</span>
            </div>

            <button className="botao-primario" onClick={finalizarPedido} disabled={enviando} style={{ width: '100%' }}>
                {enviando ? 'Enviando...' : 'Finalizar pedido'}
            </button>
        </div>
    );
}

function TelaConfirmacao({ empresa, resultado }: { empresa: string; resultado: RespostaCheckout }) {
    const pago = resultado.status_pagamento === 'pago';

    return (
        <div>
            <h1 style={{ fontSize: 20 }}>{pago ? 'Pedido confirmado!' : 'Pedido recebido'}</h1>
            <p>Pedido #{resultado.id} - total R$ {Number(resultado.valor_total).toFixed(2)}</p>

            {!pago && resultado.cobranca?.qr_code && (
                <div className="cartao" style={{ marginTop: 16 }}>
                    <h2 style={{ fontSize: 14, marginTop: 0 }}>Pague com Pix para confirmar</h2>
                    {resultado.cobranca.qr_code_base64 && (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img
                            src={`data:image/png;base64,${resultado.cobranca.qr_code_base64}`}
                            alt="QR code Pix"
                            style={{ width: 220, height: 220 }}
                        />
                    )}
                    <p style={{ fontSize: 12, color: 'var(--cor-texto-suave)', wordBreak: 'break-all' }}>
                        {resultado.cobranca.qr_code}
                    </p>
                    <p style={{ fontSize: 12 }}>Copie o código acima no app do seu banco, ou escaneie o QR code.</p>
                </div>
            )}

            {pago && <p className="msg-ok">Pagamento confirmado - obrigado pela compra!</p>}

            <Link href={`/${empresa}`} className="botao-secundario" style={{ display: 'inline-block', textDecoration: 'none', marginTop: 16 }}>
                Voltar à loja
            </Link>
        </div>
    );
}
