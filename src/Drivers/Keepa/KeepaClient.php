<?php

namespace Arnavision\PaymentGateway\Drivers\Keepa;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeepaClient
{
    protected ?string $accessToken = null;

    public function __construct(
        protected string $baseUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected int|string $terminalId,
        protected int $timeout = 30,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function authorize(): array
    {
        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->post($this->url('/creditcore/thirdpartygateway/clients/authorize'), [
                'clientId' => $this->clientId,
                'clientSecret' => $this->clientSecret,
            ]);

        $data = $this->handleResponse($response);

        $this->accessToken = $data['accessToken'] ?? null;

        if (!$this->accessToken) {
            throw new RuntimeException('Keepa accessToken not received.');
        }

        return $data;
    }

    public function getPaymentToken(
        string $invoiceNumber,
        int $amount,
        string $callbackUrl,
        ?string $payload = null,
        array $items = []
    ): array {
        $data = $this->authorizedRequest()
            ->post($this->url('/creditcore/thirdpartygateway/cpg-payments/v2/get-token'), [
                'terminalId' => (int) $this->terminalId,
                'invoiceNumber' => $invoiceNumber,
                'amount' => $amount,
                'callbackUrl' => $callbackUrl,
                'payload' => $payload,
                'items' => $items,
            ]);

        return $this->handleResponse($data);
    }

    public function inquiry(string $token): array
    {
        $response = $this->authorizedRequest()
            ->get($this->url('/creditcore/thirdpartygateway/cpg-payments/v2/inquiry/' . urlencode($token)));

        return $this->handleResponse($response);
    }

    public function verify(string $token, int $amount): array
    {
        $response = $this->authorizedRequest()
            ->post($this->url('/creditcore/thirdpartygateway/cpg-payments/v2/verify'), [
                'token' => $token,
                'amount' => $amount,
            ]);

        return $this->handleResponse($response);
    }

    protected function authorizedRequest()
    {
        if (!$this->accessToken) {
            $this->authorize();
        }

        return Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($this->accessToken);
    }

    protected function handleResponse(Response $response): array
    {
        $json = $response->json();

        if ($response->successful()) {
            return is_array($json) ? $json : [];
        }

        $message = $this->extractErrorMessage($json);

        throw new RuntimeException($message, $response->status());
    }

    protected function extractErrorMessage(mixed $json): string
    {
        if (is_array($json) && isset($json['Details']) && is_array($json['Details'])) {
            $messages = [];

            foreach ($json['Details'] as $detail) {
                $key = $detail['Key'] ?? null;
                $description = $detail['Description'] ?? null;

                $messages[] = KeepaStatus::errorMessage($key, $description);
            }

            return implode(' | ', array_filter($messages));
        }

        if (is_array($json) && isset($json['Description'])) {
            return (string) $json['Description'];
        }

        if (is_array($json) && isset($json['message'])) {
            return (string) $json['message'];
        }

        return 'خطای نامشخص در ارتباط با سرویس کیپا.';
    }

    protected function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }
}
