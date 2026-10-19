<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RegistrationVerificationService;
use App\Services\ResendTransportFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResendTransportTest extends TestCase
{
    public function test_resend_transport_renders_confirmation_and_calls_expected_endpoint(): void
    {
        $history = [];
        $handler = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{"id":"fictional-message-id"}')]));
        $handler->push(Middleware::history($history));
        $client = new Client(['handler' => $handler]);
        config()->set('mail.default', 'resend');
        config()->set('mail.from.address', 'no-reply@example.test');
        Mail::extend('resend', fn () => app(ResendTransportFactory::class)->make(['key' => 'fictional-api-key'], $client));
        $user = new User(['name' => 'Resend QA', 'email' => 'resend@example.test']);
        $user->forceFill(['id' => 123]);
        $this->assertSame('sent', app(RegistrationVerificationService::class)->send($user));
        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('https://api.resend.com/emails', (string) $request->getUri());
        $this->assertSame('Bearer fictional-api-key', $request->getHeaderLine('Authorization'));
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('Confirme seu e-mail no FlipControl', $body['subject']);
        $this->assertStringContainsString('confirmar-email?verification=', $body['html']);
        $this->assertContains('resend@example.test', $body['to']);
    }

    public function test_resend_rejection_is_reported_as_failed_delivery(): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(403, ['Content-Type' => 'application/json'], '{"name":"validation_error","message":"PRIVATE_PROVIDER_MARKER"}')]))]);
        config()->set('mail.default', 'resend');
        Mail::extend('resend', fn () => app(ResendTransportFactory::class)->make(['key' => 'fictional-api-key'], $client));
        $user = new User(['name' => 'Resend QA', 'email' => 'resend@example.test']);
        $user->forceFill(['id' => 123]);
        $this->assertSame('failed', app(RegistrationVerificationService::class)->send($user));
    }
}
