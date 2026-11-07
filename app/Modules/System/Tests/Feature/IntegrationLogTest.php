<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\AdminRole;
use App\Modules\Identity\Models\AdminUser;
use App\Modules\System\Jobs\WriteIntegrationLog;
use App\Modules\System\Models\IntegrationLog;
use App\Modules\System\Support\IntegrationHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('logs an external call with its outcome and no secrets', function (): void {
    Http::fake(['omtestapi.tipax.ir/*' => Http::response(['accessToken' => 'issued-token-xyz', 'price' => 125_000])]);

    $response = IntegrationHttp::for('tipax', 'pricing.quote', orderId: 7)
        ->withToken('bearer-secret-123')
        ->post('https://omtestapi.tipax.ir/api/OM/v4/Pricing?apiKey=key-in-url', [
            'username' => 'pharmacy',
            'password' => 'pass-secret-456',
            'nested' => ['apiKey' => 'nested-key-789', 'cityId' => 85],
        ]);

    $log = IntegrationLog::query()->sole();
    $stored = $log->getUrl().$log->getRequest().$log->getResponse();
    expect($response->json('price'))->toBe(125_000)
        ->and($log->getService())->toBe('tipax')
        ->and($log->getOperation())->toBe('pricing.quote')
        ->and($log->getMethod())->toBe('POST')
        ->and($log->getHttpStatus())->toBe(200)
        ->and($log->getOrderId())->toBe(7)
        ->and($log->getDurationMs())->toBeGreaterThanOrEqual(0)
        ->and($log->getCorrelationId())->toMatch('/^[0-9a-f-]{36}$/')
        ->and($stored)->not->toContain('bearer-secret-123')
        ->not->toContain('pass-secret-456')
        ->not->toContain('nested-key-789')
        ->not->toContain('key-in-url')
        ->not->toContain('issued-token-xyz')
        ->toContain('pharmacy')
        ->toContain('"cityId":85')
        ->toContain('"price":125000')
        ->toContain('[REDACTED]');
});

it('hides form-encoded secrets and the extra fields a call marks as secret', function (): void {
    Http::fake(['rest.payamak-panel.com/*' => Http::response(['Value' => '5123456789012345678', 'RetStatus' => 1])]);

    IntegrationHttp::for('melipayamak', 'sms.otp', redact: ['text'])
        ->asForm()
        ->post('https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber', [
            'username' => 'pharmacy',
            'password' => 'MELI-API-KEY',
            'text' => '48213',
            'to' => '09121234567',
            'bodyId' => '12345',
        ]);

    $request = IntegrationLog::query()->sole()->getRequest();
    expect($request)->not->toContain('MELI-API-KEY')
        ->not->toContain('48213')
        ->toContain('bodyId=12345')
        ->toContain('username=pharmacy');
});

it('logs a failed connection', function (): void {
    Http::fake(['*' => Http::failedConnection()]);

    expect(fn () => IntegrationHttp::for('zarinpal', 'payment.request')->post('https://sandbox.zarinpal.com/pg/v4/payment/request.json'))
        ->toThrow(ConnectionException::class);

    $log = IntegrationLog::query()->sole();
    expect($log->getHttpStatus())->toBeNull()
        ->and($log->isFailure())->toBeTrue()
        ->and($log->getError())->toContain('ConnectException');
});

it('logs error responses as failures', function (): void {
    Http::fake(['*' => Http::response(['message' => 'CityNotFound'], 422)]);

    IntegrationHttp::for('tipax', 'pricing.quote')->post('https://omtestapi.tipax.ir/api/OM/v4/Pricing');

    expect(IntegrationLog::query()->sole()->isFailure())->toBeTrue();
});

it('queues the log immediately so a rolled-back transaction does not lose it', function (): void {
    Queue::fake();
    Http::fake(['*' => Http::response(['code' => -51], 200)]);

    try {
        DB::transaction(function (): void {
            IntegrationHttp::for('zarinpal', 'payment.verify')->post('https://sandbox.zarinpal.com/pg/v4/payment/verify.json');

            throw new RuntimeException('verify failed, rolling back');
        });
    } catch (RuntimeException) {
        // expected
    }

    Queue::assertPushed(WriteIntegrationLog::class, fn (WriteIntegrationLog $job): bool => $job->afterCommit !== true
        && $job->payload[IntegrationLog::COLUMN_OPERATION] === 'payment.verify');
});

it('stores the request encrypted', function (): void {
    Http::fake(['*' => Http::response('ok')]);

    IntegrationHttp::for('tipax', 'ping')->post('https://omtestapi.tipax.ir/ping', ['username' => 'pharmacy']);

    $raw = DB::table('integration_logs')->value('request');
    expect($raw)->not->toContain('pharmacy')
        ->and(decrypt($raw, unserialize: false))->toContain('pharmacy');
});

it('offers the same client as Http::integration()', function (): void {
    Http::fake(['*' => Http::response('ok')]);

    Http::integration('tipax', 'ping')->get('https://omtestapi.tipax.ir/ping'); // @phpstan-ignore staticMethod.notFound

    expect(IntegrationLog::query()->sole()->getService())->toBe('tipax');
});

it('prunes logs older than the retention period', function (): void {
    Http::fake(['*' => Http::response('ok')]);
    $this->travelTo(now()->subDays(91));
    IntegrationHttp::for('tipax', 'old')->get('https://omtestapi.tipax.ir/ping');
    $this->travelBack();
    IntegrationHttp::for('tipax', 'recent')->get('https://omtestapi.tipax.ir/ping');

    $this->artisan('model:prune', ['--model' => [IntegrationLog::class]])->assertSuccessful();

    expect(IntegrationLog::query()->pluck(IntegrationLog::COLUMN_OPERATION)->all())->toBe(['recent']);
});

it('shows the integration log to super admins only', function (): void {
    Http::fake(['*' => Http::response(['ok' => true])]);
    IntegrationHttp::for('tipax', 'ping')->get('https://omtestapi.tipax.ir/ping');
    $log = IntegrationLog::query()->sole();
    $superAdmin = AdminUser::factory()->withTwoFactorAuthentication()->superAdmin()->create();
    $operator = AdminUser::factory()->withTwoFactorAuthentication()->withRole(AdminRole::Operator)->create();

    $this->actingAs($superAdmin, 'admin')->get('/admin/integration-logs')->assertOk()->assertSee('tipax');
    $this->actingAs($superAdmin, 'admin')->get("/admin/integration-logs/{$log->getId()}")->assertOk()->assertSee('prettyjson', false);
    $this->actingAs($operator, 'admin')->get('/admin/integration-logs')->assertForbidden();
});
