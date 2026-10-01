'use client';

import { use, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { useCarrinho } from '@/lib/cart';
import { api, ErroApi } from '@/lib/api';
import type { ConfigPagamentoPublica, CotacaoFrete, CupomValidado, RespostaCheckout } from '@/lib/types';
import CardBrick from '@/components/CardBrick';
import StoneCardForm from '@/components/StoneCardForm';

export default function PaginaCheckout({ params }: { params: Promise<{ empresa: string }> }) {
    const { empresa } = use(params);
    const router = useRouter();
    const { itens, total, limpar } = useCarrinho();

    const [nome, setNome] = useState('');
    const [cpfCnpj, setCpfCnpj] = useState('');
    const [rg, setRg] = useState('');
    const [inscricaoEstadual, setInscricaoEstadual] = useState('');
    const [email, setEmail] = useState('');
    const [telefone, setTelefone] = useState('');
    const [cep, setCep] = useState('');
    const [logradouro, setLogradouro] = useState('');
    const [numero, setNumero] = useState('');
    const [bairro, setBairro] = useState('');
    const [municipio, setMunicipio] = useState('');
    const [uf, setUf] = useState('');
    const [codigoIbgeMunicipio, setCodigoIbgeMunicipio] = useState('');
    const [buscandoCep, setBuscandoCep] = useState(false);
    const [lgpd, setLgpd] = useState(false);
    const [formaPagamento, setFormaPagamento] = useState<'pix' | 'cartao'>('pix');
    const [tipoEntrega, setTipoEntrega] = useState<'entrega' | 'retirada'>('entrega');
    const [cotacaoFrete, setCotacaoFrete] = useState<CotacaoFrete | null>(null);

    const [configPagamento, setConfigPagamento] = useState<ConfigPagamentoPublica | null>(null);
    const [enviando, setEnviando] = useState(false);
    const [erro, setErro] = useState<string | null>(null);
    const [resultado, setResultado] = useState<RespostaCheckout | null>(null);

    const [codigoCupom, setCodigoCupom] = useState('');
    const [cupomAplicado, setCupomAplicado] = useState<CupomValidado | null>(null);
    const [validandoCupom, setValidandoCupom] = useState(false);
    const [erroCupom, setErroCupom] = useState<string | null>(null);

    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [clienteEncontrado, setClienteEncontrado] = useState(false);

    async function buscarClientePorCpf() {
        const documento = cpfCnpj.trim();
        // Evita disparar busca em documento incompleto (só ao sair do
        // campo, e só se tiver o tamanho mínimo de um CPF sem máscara).
        if (documento.replace(/\D/g, '').length < 11) return;

        setBuscandoCliente(true);
        setClienteEncontrado(false);

        try {
            const resultado = await api.buscarCliente(empresa, documento);
            if (resultado.encontrado) {
                setNome((atual) => atual || resultado.nome || '');
                setEmail((atual) => atual || resultado.email || '');
                setTelefone((atual) => atual || resultado.telefone || '');
                setRg((atual) => atual || resultado.rg || '');
                setInscricaoEstadual((atual) => atual || resultado.inscricao_estadual || '');
                setCep((atual) => atual || resultado.cep || '');
                setLogradouro((atual) => atual || resultado.logradouro || '');
                setNumero((atual) => atual || resultado.numero || '');
                setBairro((atual) => atual || resultado.bairro || '');
                setMunicipio((atual) => atual || resultado.municipio || '');
                setUf((atual) => atual || resultado.uf || '');
                setCodigoIbgeMunicipio((atual) => atual || resultado.codigo_ibge_municipio || '');
                setClienteEncontrado(true);
            }
        } catch {
            // Falha silenciosa - é só uma conveniência de preenchimento,
            // não deve impedir o cliente de continuar digitando na mão.
        } finally {
            setBuscandoCliente(false);
        }
    }

    async function buscarEnderecoPorCep() {
        const cepLimpo = cep.replace(/\D/g, '');
        if (cepLimpo.length !== 8) return;

        setBuscandoCep(true);

        try {
            const resposta = await fetch(`https://viacep.com.br/ws/${cepLimpo}/json/`);
            const dados = await resposta.json();
            if (!dados.erro) {
                setLogradouro((atual) => atual || dados.logradouro || '');
                setBairro((atual) => atual || dados.bairro || '');
                setMunicipio(dados.localidade || '');
                setUf(dados.uf || '');
                setCodigoIbgeMunicipio(dados.ibge || '');
            }
        } catch {
            // Falha silenciosa - conveniência de preenchimento; o cliente
            // ainda pode completar o endereço manualmente.
        } finally {
            setBuscandoCep(false);
        }
    }
    const [dadosCartao, setDadosCartao] = useState<{ token: string; installments: number; payment_type_id: string } | null>(
        null,
    );

    useEffect(() => {
        api.configPagamentoPublica(empresa).then(setConfigPagamento).catch(() => setConfigPagamento({ gateway: null, public_key: null }));
    }, [empresa]);

    // Cotação de frete: refaz quando muda a UF ou o subtotal de produtos
    // (o frete grátis depende do valor). Só o servidor decide o valor final.
    const subtotalProdutos = itens.reduce((acc, i) => (i.tipo === 'produto' ? acc + i.valorUnitario * i.quantidade : acc), 0);
    useEffect(() => {
        if (subtotalProdutos <= 0) return;
        api.frete(empresa, uf.length === 2 ? uf : '', subtotalProdutos).then(setCotacaoFrete).catch(() => setCotacaoFrete(null));
    }, [empresa, uf, subtotalProdutos]);

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

    const aceitaCartaoOnline =
        (configPagamento?.gateway === 'mercadopago' || configPagamento?.gateway === 'stone') &&
        !!configPagamento.public_key;
    const desconto = cupomAplicado?.valido ? (cupomAplicado.valor_desconto ?? 0) : 0;

    // Cupom só desconta a visita agendada, nunca produtos - por isso o
    // botão de aplicar cupom só aparece quando há uma visita no carrinho.
    const itemAgendaCarrinho = itens.find((i) => i.tipo === 'agenda');
    const subtotalVisitas = itemAgendaCarrinho ? itemAgendaCarrinho.valorUnitario * itemAgendaCarrinho.quantidade : 0;
    const quantidadeTicketsCarrinho = itemAgendaCarrinho ? itemAgendaCarrinho.quantidade : 0;

    // Endereço só é obrigatório quando há produto físico no carrinho - é
    // usado para o envio; visita agendada sozinha não precisa dele.
    const temProdutoFisico = itens.some((i) => i.tipo === 'produto');
    const permiteRetirada = !!cotacaoFrete?.permite_retirada;
    const retirando = temProdutoFisico && permiteRetirada && tipoEntrega === 'retirada';
    const entregando = temProdutoFisico && !retirando;
    const entregaCotada = uf.length === 2 ? cotacaoFrete?.entrega : undefined;
    const valorFrete = entregando && entregaCotada?.disponivel ? entregaCotada.valor : 0;
    const totalComDesconto = Math.max(0, total - desconto) + valorFrete;
    const documentoLimpo = cpfCnpj.replace(/\D/g, '');
    const pessoaJuridica = documentoLimpo.length === 14;

    const totalPorProduto = new Map<number, number>();
    const minimoPorProduto = new Map<number, number>();
    itens.forEach((item) => {
        if (item.tipo !== 'produto') return;
        totalPorProduto.set(item.produtoId, (totalPorProduto.get(item.produtoId) ?? 0) + item.quantidade);
        if (item.quantidadeMinima) minimoPorProduto.set(item.produtoId, item.quantidadeMinima);
    });
    const avisosMinimo = Array.from(minimoPorProduto.entries())
        .filter(([produtoId, minimo]) => (totalPorProduto.get(produtoId) ?? 0) < minimo)
        .map(([produtoId, minimo]) => {
            const item = itens.find((i) => i.tipo === 'produto' && i.produtoId === produtoId);
            const nomeBase = item?.nome.replace(/\s*\([^)]*\)\s*$/, '') ?? '';
            return `${nomeBase}: venda mínima de ${minimo} unidades (faltam ${minimo - (totalPorProduto.get(produtoId) ?? 0)}).`;
        });

    async function aplicarCupom() {
        if (!codigoCupom.trim()) return;

        setErroCupom(null);
        setValidandoCupom(true);

        try {
            const resposta = await api.validarCupom(empresa, codigoCupom.trim(), subtotalVisitas, quantidadeTicketsCarrinho);
            setCupomAplicado(resposta);
        } catch (e) {
            setCupomAplicado(null);
            setErroCupom(e instanceof ErroApi ? e.message : 'Não foi possível validar o cupom.');
        } finally {
            setValidandoCupom(false);
        }
    }

    function removerCupom() {
        setCupomAplicado(null);
        setCodigoCupom('');
        setErroCupom(null);
    }

    async function finalizarPedido() {
        setErro(null);

        if (!nome.trim() || !cpfCnpj.trim() || !email.trim() || !telefone.trim() || !lgpd) {
            setErro('Preencha nome, CPF/CNPJ, e-mail, telefone e aceite o termo de consentimento para continuar.');
            return;
        }

        if (documentoLimpo.length !== 11 && documentoLimpo.length !== 14) {
            setErro('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.');
            return;
        }

        if (pessoaJuridica && !inscricaoEstadual.trim()) {
            setErro('Informe a Inscrição Estadual para pessoa jurídica.');
            return;
        }

        if (!pessoaJuridica && !rg.trim()) {
            setErro('Informe o RG.');
            return;
        }

        if (entregando && (!cep.trim() || !logradouro.trim() || !numero.trim() || !bairro.trim() || !municipio.trim() || !uf.trim())) {
            setErro('Informe o endereço completo para envio do produto.');
            return;
        }

        if (entregando && entregaCotada && !entregaCotada.disponivel) {
            setErro(entregaCotada.mensagem || 'Não realizamos entregas para este estado.');
            return;
        }

        if (avisosMinimo.length > 0) {
            setErro(avisosMinimo[0]);
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
                    cpf_cnpj: cpfCnpj,
                    rg: rg || null,
                    inscricao_estadual: inscricaoEstadual || null,
                    email,
                    telefone,
                    cep: cep || null,
                    logradouro: logradouro || null,
                    numero: numero || null,
                    bairro: bairro || null,
                    municipio: municipio || null,
                    uf: uf || null,
                    codigo_ibge_municipio: codigoIbgeMunicipio || null,
                    consentimento_lgpd: lgpd,
                },
                itens: produtosItens.map((i) =>
                    i.tipo === 'produto'
                        ? { produto_id: i.produtoId, variacao_id: i.variacaoId ?? null, quantidade: i.quantidade }
                        : null,
                ),
                reserva_id: reservaId,
                forma_pagamento: formaPagamento,
                cupom_codigo: cupomAplicado?.valido ? cupomAplicado.codigo : undefined,
                tipo_entrega: temProdutoFisico ? (retirando ? 'retirada' : 'entrega') : undefined,
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
                        <label>CPF/CNPJ</label>
                        <input
                            value={cpfCnpj}
                            onChange={(e) => {
                                setCpfCnpj(e.target.value);
                                setClienteEncontrado(false);
                            }}
                            onBlur={buscarClientePorCpf}
                            required
                        />
                        {buscandoCliente && (
                            <span style={{ fontSize: 12, color: 'var(--cor-texto-suave)' }}>Verificando cadastro...</span>
                        )}
                        {clienteEncontrado && !buscandoCliente && (
                            <span style={{ fontSize: 12, color: 'var(--cor-ok-texto)' }}>
                                Encontramos seu cadastro - dados preenchidos automaticamente.
                            </span>
                        )}
                    </div>
                    {pessoaJuridica ? (
                        <div>
                            <label>Inscrição Estadual</label>
                            <input value={inscricaoEstadual} onChange={(e) => setInscricaoEstadual(e.target.value)} required />
                        </div>
                    ) : (
                        <div>
                            <label>RG</label>
                            <input value={rg} onChange={(e) => setRg(e.target.value)} required />
                        </div>
                    )}
                    <div>
                        <label>E-mail</label>
                        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
                    </div>
                    <div>
                        <label>Telefone</label>
                        <input value={telefone} onChange={(e) => setTelefone(e.target.value)} required />
                    </div>
                    <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
                        <input type="checkbox" style={{ width: 'auto' }} checked={lgpd} onChange={(e) => setLgpd(e.target.checked)} />
                        Concordo com o uso dos meus dados para esta compra, conforme a política de privacidade.
                    </label>
                </div>
            </div>

            {temProdutoFisico && permiteRetirada && (
                <div className="cartao" style={{ marginBottom: 16 }}>
                    <h2 style={{ fontSize: 14, marginTop: 0 }}>Como você quer receber?</h2>
                    <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }}>
                        <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
                            <input
                                type="radio"
                                style={{ width: 'auto' }}
                                checked={tipoEntrega === 'entrega'}
                                onChange={() => setTipoEntrega('entrega')}
                            />
                            Receber em casa (entrega)
                        </label>
                        <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13 }}>
                            <input
                                type="radio"
                                style={{ width: 'auto' }}
                                checked={tipoEntrega === 'retirada'}
                                onChange={() => setTipoEntrega('retirada')}
                            />
                            Retirar na loja (sem frete)
                        </label>
                    </div>
                    {retirando && cotacaoFrete?.instrucoes_retirada && (
                        <p style={{ fontSize: 12.5, color: 'var(--cor-texto-suave)', marginBottom: 0 }}>
                            {cotacaoFrete.instrucoes_retirada}
                        </p>
                    )}
                </div>
            )}

            {entregando && (
                <div className="cartao" style={{ marginBottom: 16 }}>
                    <h2 style={{ fontSize: 14, marginTop: 0 }}>Endereço de entrega</h2>
                    <p style={{ fontSize: 12, color: 'var(--cor-texto-suave)', marginTop: -6 }}>
                        Necessário para o envio do produto.
                    </p>
                    <div style={{ display: 'grid', gap: 10 }}>
                        <div>
                            <label>CEP</label>
                            <input
                                value={cep}
                                onChange={(e) => setCep(e.target.value)}
                                onBlur={buscarEnderecoPorCep}
                                required
                            />
                            {buscandoCep && (
                                <span style={{ fontSize: 12, color: 'var(--cor-texto-suave)' }}>Buscando endereço...</span>
                            )}
                        </div>
                        <div style={{ display: 'flex', gap: 10 }}>
                            <div style={{ flex: 3 }}>
                                <label>Logradouro</label>
                                <input value={logradouro} onChange={(e) => setLogradouro(e.target.value)} required />
                            </div>
                            <div style={{ flex: 1 }}>
                                <label>Número</label>
                                <input value={numero} onChange={(e) => setNumero(e.target.value)} required />
                            </div>
                        </div>
                        <div>
                            <label>Bairro</label>
                            <input value={bairro} onChange={(e) => setBairro(e.target.value)} required />
                        </div>
                        <div style={{ display: 'flex', gap: 10 }}>
                            <div style={{ flex: 3 }}>
                                <label>Cidade</label>
                                <input value={municipio} onChange={(e) => setMunicipio(e.target.value)} required />
                            </div>
                            <div style={{ flex: 1 }}>
                                <label>UF</label>
                                <input value={uf} onChange={(e) => setUf(e.target.value.toUpperCase())} maxLength={2} required />
                            </div>
                        </div>
                        {entregaCotada && (
                            entregaCotada.disponivel ? (
                                <span style={{ fontSize: 13, color: entregaCotada.gratis ? 'var(--cor-ok-texto)' : 'var(--cor-texto-suave)' }}>
                                    {entregaCotada.gratis ? 'Frete grátis' : `Frete: R$ ${entregaCotada.valor.toFixed(2)}`}
                                    {entregaCotada.prazo_dias !== null ? ` - prazo de ${entregaCotada.prazo_dias} dia(s)` : ''}
                                </span>
                            ) : (
                                <span className="msg-erro" style={{ fontSize: 13 }}>{entregaCotada.mensagem}</span>
                            )
                        )}
                        {!entregaCotada && cotacaoFrete?.frete_gratis_acima && (
                            <span style={{ fontSize: 12, color: 'var(--cor-texto-suave)' }}>
                                Frete grátis para compras de produtos a partir de R$ {Number(cotacaoFrete.frete_gratis_acima).toFixed(2)}.
                            </span>
                        )}
                    </div>
                </div>
            )}

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
                    aceitaCartaoOnline && configPagamento?.public_key && configPagamento.gateway === 'mercadopago' ? (
                        <CardBrick
                            publicKey={configPagamento.public_key}
                            valor={totalComDesconto}
                            onToken={setDadosCartao}
                            onErro={setErro}
                        />
                    ) : aceitaCartaoOnline && configPagamento?.public_key && configPagamento.gateway === 'stone' ? (
                        <StoneCardForm
                            publicKey={configPagamento.public_key}
                            onToken={(token, tipo, parcelas) =>
                                setDadosCartao({
                                    token,
                                    installments: parcelas,
                                    payment_type_id: tipo === 'debito' ? 'debit_card' : 'credit_card',
                                })
                            }
                            onErro={setErro}
                        />
                    ) : (
                        <p style={{ fontSize: 12, color: 'var(--cor-texto-suave)' }}>
                            Pagamento online por cartão ainda não está disponível para esta loja - escolha Pix.
                        </p>
                    )
                )}
            </div>

            {itemAgendaCarrinho && (
                <div className="cartao" style={{ marginBottom: 16 }}>
                    <h2 style={{ fontSize: 14, marginTop: 0 }}>Cupom de desconto</h2>
                    <p style={{ fontSize: 12, color: 'var(--cor-texto-suave)', marginTop: -6 }}>
                        O cupom desconta só o valor da visitação, não os produtos.
                    </p>

                    {cupomAplicado?.valido ? (
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10 }}>
                            <span className="msg-ok" style={{ flex: 1 }}>
                                Cupom <strong>{cupomAplicado.codigo}</strong> aplicado - desconto de R$ {desconto.toFixed(2)}.
                            </span>
                            <button className="botao-secundario" onClick={removerCupom}>Remover</button>
                        </div>
                    ) : (
                        <div style={{ display: 'flex', gap: 10 }}>
                            <input
                                value={codigoCupom}
                                onChange={(e) => setCodigoCupom(e.target.value)}
                                placeholder="Código do cupom"
                                style={{ textTransform: 'uppercase' }}
                                onKeyDown={(e) => e.key === 'Enter' && aplicarCupom()}
                            />
                            <button className="botao-secundario" onClick={aplicarCupom} disabled={validandoCupom || !codigoCupom.trim()} style={{ flexShrink: 0 }}>
                                {validandoCupom ? 'Validando...' : 'Aplicar'}
                            </button>
                        </div>
                    )}
                    {erroCupom && <p className="msg-erro" style={{ marginTop: 10 }}>{erroCupom}</p>}
                </div>
            )}

            {avisosMinimo.length > 0 && (
                <div className="cartao msg-erro" style={{ marginBottom: 16 }}>
                    <strong style={{ display: 'block', marginBottom: 4, fontSize: 13 }}>
                        Complete a quantidade mínima para continuar:
                    </strong>
                    {avisosMinimo.map((aviso) => (
                        <div key={aviso} style={{ fontSize: 13 }}>{aviso}</div>
                    ))}
                </div>
            )}

            {erro && <p className="msg-erro" style={{ marginBottom: 12 }}>{erro}</p>}

            <div className="cartao" style={{ marginBottom: 16, display: 'flex', flexDirection: 'column', gap: 6 }}>
                {(desconto > 0 || entregando) && (
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14, color: 'var(--cor-texto-suave)' }}>
                        <span>Subtotal</span>
                        <span>R$ {total.toFixed(2)}</span>
                    </div>
                )}
                {desconto > 0 && (
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14, color: 'var(--cor-ok-texto)' }}>
                        <span>Desconto</span>
                        <span>- R$ {desconto.toFixed(2)}</span>
                    </div>
                )}
                {entregando && (
                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14, color: 'var(--cor-texto-suave)' }}>
                        <span>Frete</span>
                        <span>{!entregaCotada ? 'informe o CEP/UF' : !entregaCotada.disponivel ? 'indisponível' : valorFrete > 0 ? `R$ ${valorFrete.toFixed(2)}` : 'Grátis'}</span>
                    </div>
                )}
                <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 700, fontSize: 18 }}>
                    <span>Total</span>
                    <span>R$ {totalComDesconto.toFixed(2)}</span>
                </div>
            </div>

            <button className="botao-primario" onClick={finalizarPedido} disabled={enviando || avisosMinimo.length > 0} style={{ width: '100%' }}>
                {enviando ? 'Enviando...' : 'Finalizar pedido'}
            </button>
        </div>
    );
}

function TelaConfirmacao({ empresa, resultado }: { empresa: string; resultado: RespostaCheckout }) {
    const pago = resultado.status_pagamento === 'pago';
    const itens = resultado.itens ?? [];
    const itensProduto = itens.filter((i) => i.produto);
    const itensVisita = itens.filter((i) => i.agenda_visitacao);

    return (
        <div>
            <h1 style={{ fontSize: 20 }}>{pago ? 'Pedido confirmado!' : 'Pedido recebido'}</h1>
            <p>Pedido #{resultado.id} - total R$ {Number(resultado.valor_total).toFixed(2)}</p>

            {itens.length > 0 && (
                <div className="cartao" style={{ marginTop: 16 }}>
                    <h2 style={{ fontSize: 14, marginTop: 0 }}>Recibo do pedido</h2>

                    {itensProduto.length > 0 && (
                        <>
                            <h3 style={{ fontSize: 12, textTransform: 'uppercase', color: 'var(--cor-texto-suave)', marginBottom: 6 }}>
                                Produtos
                            </h3>
                            {itensProduto.map((item) => (
                                <div key={item.id} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 4 }}>
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
                            <h3 style={{ fontSize: 12, textTransform: 'uppercase', color: 'var(--cor-texto-suave)', marginTop: itensProduto.length ? 12 : 0, marginBottom: 6 }}>
                                Visitas agendadas
                            </h3>
                            {itensVisita.map((item) => (
                                <div key={item.id} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 4 }}>
                                    <span>
                                        {item.quantidade}x visita
                                        {item.agenda_visitacao
                                            ? ` - ${new Date(item.agenda_visitacao.data_hora).toLocaleString('pt-BR')}`
                                            : ''}
                                    </span>
                                    <span>R$ {Number(item.valor_total).toFixed(2)}</span>
                                </div>
                            ))}
                        </>
                    )}

                    {resultado.valor_desconto && Number(resultado.valor_desconto) > 0 && (
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginTop: 10, color: 'var(--cor-ok-texto)' }}>
                            <span>Desconto do cupom</span>
                            <span>- R$ {Number(resultado.valor_desconto).toFixed(2)}</span>
                        </div>
                    )}

                    {resultado.tipo_entrega === 'entrega' && (
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginTop: 10 }}>
                            <span>Frete</span>
                            <span>{Number(resultado.valor_frete) > 0 ? `R$ ${Number(resultado.valor_frete).toFixed(2)}` : 'Grátis'}</span>
                        </div>
                    )}
                    {resultado.tipo_entrega === 'retirada' && (
                        <div style={{ fontSize: 13, marginTop: 10 }}>Retirada na loja (sem frete)</div>
                    )}

                    <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 700, fontSize: 15, marginTop: 10, paddingTop: 10, borderTop: '1px solid var(--cor-borda)' }}>
                        <span>Total</span>
                        <span>R$ {Number(resultado.valor_total).toFixed(2)}</span>
                    </div>
                </div>
            )}

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

            <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                <Link href={`/${empresa}/pedido/${resultado.id}`} className="botao-primario" style={{ textDecoration: 'none' }}>
                    Ver recibo completo
                </Link>
                <Link href={`/${empresa}`} className="botao-secundario" style={{ textDecoration: 'none' }}>
                    Voltar à loja
                </Link>
            </div>
        </div>
    );
}
