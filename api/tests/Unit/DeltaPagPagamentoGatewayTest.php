<?php

namespace Tests\Unit;

use App\Models\ConfigPagamento;
use App\Models\Venda;
use App\Services\Pagamento\DeltaPagPagamentoGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Não há credenciais reais de sandbox ainda, então a chamada de API é
 * simulada (Http::fake) com base no que a documentação oficial descreve -
 * ver comentário no topo de DeltaPagPagamentoGateway.php. Objetivo aqui é
 * só garantir que a classe monta a requisição certa e interpreta a
 * resposta certa - não substitui testar contra o sandbox de verdade antes
 * de ativar para uma empresa cliente.
 */
class DeltaPagPagamentoGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_criar_cobranca_pix_monta_requisicao_e_mapeia_resposta(): void
    {
        config(['app.url' => 'https://app.exemplo.com.br']);

        Http::fake([
            'sandbox-api.zoov.com.br/api/v2/sellers/*/orders/pix' => Http::response('', 201, [
                'Content-ID' => '9988',
            ]),
            'sandbox-api.zoov.com.br/api/v2/charges/9988' => Http::response([
                'id' => 9988,
                'status' => 'PENDING',
                'transactions' => [[
                    'paymentMethod' => 'PIX',
                    'status' => 'AWAITING_PAYMENT',
                    'emv' => '00020126580014br.gov.bcb.pix...',
                    'expirationDate' => 1900000000000,
                ]],
            ], 200),
            'sandbox-api.zoov.com.br/api/v2/charges/9988/qrcode' => Http::response('fake-png-bytes', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $config = new ConfigPagamento([
            'gateway' => 'deltapag',
            'ambiente' => 'sandbox',
            'access_token' => 'token-de-teste',
            'client_id' => 'seller-123',
            'ativo' => true,
        ]);

        $venda = new Venda(['valor_total' => 55.90]);
        $venda->id = 42;

        $resultado = (new DeltaPagPagamentoGateway())->criarCobrancaPix($venda, $config);

        $this->assertSame('pendente', $resultado->status);
        $this->assertSame('9988', $resultado->referenciaExterna);
        $this->assertStringStartsWith('V42-', $resultado->referenciaPedido);
        $this->assertSame('00020126580014br.gov.bcb.pix...', $resultado->qrCode);
        $this->assertSame(base64_encode('fake-png-bytes'), $resultado->qrCodeBase64);
        $this->assertNotNull($resultado->expiraEm);

        Http::assertSent(function (HttpRequest $request) {
            if (! str_contains($request->url(), '/orders/pix')) {
                return true; // deixa passar as outras chamadas (consulta/qrcode)
            }

            $body = $request->data();

            return $request->hasHeader('Authorization', 'Bearer token-de-teste')
                && str_contains($request->url(), '/sellers/seller-123/orders/pix')
                && $body['amount'] === 5590
                && $body['payments'][0]['paymentMethod'] === 'PIX'
                && $body['payments'][0]['amount'] === 5590
                && str_starts_with($body['notificationUrl'], 'https://app.exemplo.com.br/api/webhooks/pagamento/deltapag?ref=V42-');
        });
    }

    public function test_cartao_debito_nao_e_suportado(): void
    {
        $config = new ConfigPagamento(['gateway' => 'deltapag', 'ambiente' => 'sandbox', 'client_id' => 'seller-123']);
        $venda = new Venda(['id' => 1, 'valor_total' => 10]);

        $this->expectException(\RuntimeException::class);

        (new DeltaPagPagamentoGateway())->criarCobrancaCartao($venda, $config, 'token-cartao', 1, 'cartao_debito');
    }

    public function test_mapeia_status_pago_na_consulta(): void
    {
        Http::fake([
            'sandbox-api.zoov.com.br/api/v2/charges/555' => Http::response(['id' => 555, 'status' => 'PAY'], 200),
        ]);

        $config = new ConfigPagamento(['gateway' => 'deltapag', 'ambiente' => 'sandbox', 'access_token' => 'x', 'client_id' => 'seller-123']);

        $status = (new DeltaPagPagamentoGateway())->consultarStatus($config, '555');

        $this->assertSame('aprovado', $status);
    }
}
