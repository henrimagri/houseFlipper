<?php

namespace App\Services;

use GuzzleHttp\Client as HttpClient;
use Illuminate\Mail\Transport\ResendTransport;
use Psr\Http\Client\ClientInterface;
use Resend\Client;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;

class ResendTransportFactory
{
    public function make(array $config = [], ?ClientInterface $httpClient = null): ResendTransport
    {
        $httpClient ??= new HttpClient([
            'timeout' => 10, 'connect_timeout' => 3, 'allow_redirects' => false,
            'verify' => config('services.http.ca_bundle') ?: true,
        ]);
        $client = new Client(new HttpTransporter($httpClient, BaseUri::from('api.resend.com'), Headers::withAuthorization(ApiKey::from($config['key'] ?? config('services.resend.key') ?? ''))));

        return new ResendTransport($client);
    }
}
