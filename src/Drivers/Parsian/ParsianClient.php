<?php

namespace Arnavision\PaymentGateway\Drivers\Parsian;

use RuntimeException;
use SimpleXMLElement;

class ParsianClient
{
    public function __construct(
        protected string $loginAccount,
        protected string $saleUrl,
        protected string $confirmUrl,
        protected string $reverseUrl,
        protected int $timeout = 10,
        protected bool $sslVerify = false
    ) {
    }

    public function salePaymentRequest(
        string|int $orderId,
        int $amount,
        string $callbackUrl,
        ?string $additionalData = null,
        ?string $originator = null
    ): array {
        $xml = $this->buildSalePaymentXmlString(
            orderId: $orderId,
            amount: $amount,
            callbackUrl: $callbackUrl,
            additionalData: $additionalData,
            originator: $originator
        );

        $response = $this->postSoap(
            url: $this->saleUrl,
            xml: $xml,
            soapAction: $this->getSaleSoapAction()
        );

        $parser = $this->parseSoapBody($response);

        $result = $parser->SalePaymentRequestResponse->SalePaymentRequestResult ?? null;

        if (!$result) {
            return [
                'status' => -138,
                'message' => 'ارتباط شبکه برقرار نمی باشد',
                'raw_response' => $response,
            ];
        }

        return [
            'token' => isset($result->Token) ? (string) $result->Token : null,
            'message' => isset($result->Message) ? (string) $result->Message : null,
            'status' => isset($result->Status) ? (string) $result->Status : null,
            'raw_response' => $response,
        ];
    }

    public function confirmPaymentWithAmount(
        string|int $token,
        int|string $amount,
        string|int $orderId
    ): array {
        $amount = (int) str_replace(',', '', (string) $amount);

        $xml = $this->buildConfirmPaymentXmlString(
            token: $token,
            amount: $amount,
            orderId: $orderId
        );

        $response = $this->postSoap(
            url: $this->confirmUrl,
            xml: $xml,
            soapAction: $this->getConfirmSoapAction()
        );

        $parser = $this->parseSoapBody($response);

        $result = $parser->ConfirmPaymentWithAmountResponse->ConfirmPaymentWithAmountResult ?? null;

        if (!$result) {
            return [
                'status' => -138,
                'message' => 'ارتباط شبکه برقرار نمی باشد',
                'raw_response' => $response,
            ];
        }

        return [
            'token' => isset($result->Token) ? (string) $result->Token : null,
            'card_number' => isset($result->CardNumberMasked) ? (string) $result->CardNumberMasked : null,
            'RRN' => isset($result->RRN) ? (string) $result->RRN : null,
            'status' => isset($result->Status) ? (string) $result->Status : null,
            'message' => isset($result->Message) ? (string) $result->Message : null,
            'raw_response' => $response,
        ];
    }

    public function reversalRequest(string|int $token): bool
    {
        $xml = $this->buildReversePaymentXmlString($token);

        $response = $this->postSoap(
            url: $this->reverseUrl,
            xml: $xml,
            soapAction: $this->getReverseSoapAction()
        );

        $parser = $this->parseSoapBody($response);

        $status = $parser->ReversalRequestResponse->ReversalRequestResult->Status ?? null;

        return (string) $status === '0';
    }

    protected function postSoap(string $url, string $xml, string $soapAction): string
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify ? 1 : 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-type: text/xml; charset=utf-8',
            'Accept: text/xml',
            'Host: pec.shaparak.ir',
            'SOAPAction:' . $soapAction,
            'Content-length: ' . strlen($xml),
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException($error ?: 'Parsian cURL error.');
        }

        curl_close($ch);

        return $response;
    }

    protected function parseSoapBody(string $response): SimpleXMLElement
    {
        $response = str_replace('<soap:Body>', '', $response);
        $response = str_replace('</soap:Body>', '', $response);

        $parser = simplexml_load_string($response);

        if (!$parser) {
            throw new RuntimeException('Invalid Parsian SOAP response.');
        }

        return $parser;
    }

    protected function buildSalePaymentXmlString(
        string|int $orderId,
        int $amount,
        string $callbackUrl,
        ?string $additionalData = null,
        ?string $originator = null
    ): string {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<SalePaymentRequest xmlns="' . $this->getSalePaymentRequest() . '">
<requestData>
<LoginAccount>' . htmlspecialchars($this->loginAccount, ENT_XML1, 'UTF-8') . '</LoginAccount>
<OrderId>' . htmlspecialchars((string) $orderId, ENT_XML1, 'UTF-8') . '</OrderId>
<Amount>' . $amount . '</Amount>
<CallBackUrl>' . htmlspecialchars($callbackUrl, ENT_XML1, 'UTF-8') . '</CallBackUrl>
<AdditionalData>' . htmlspecialchars((string) $additionalData, ENT_XML1, 'UTF-8') . '</AdditionalData>
<Originator>' . htmlspecialchars((string) $originator, ENT_XML1, 'UTF-8') . '</Originator>
</requestData>
</SalePaymentRequest>
</soap:Body>
</soap:Envelope>';
    }

    protected function buildConfirmPaymentXmlString(
        string|int $token,
        int $amount,
        string|int $orderId
    ): string {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<ConfirmPaymentWithAmount xmlns="' . $this->getConfirmPaymentRequest() . '">
<requestData>
<LoginAccount>' . htmlspecialchars($this->loginAccount, ENT_XML1, 'UTF-8') . '</LoginAccount>
<Token>' . htmlspecialchars((string) $token, ENT_XML1, 'UTF-8') . '</Token>
<OrderId>' . htmlspecialchars((string) $orderId, ENT_XML1, 'UTF-8') . '</OrderId>
<Amount>' . $amount . '</Amount>
</requestData>
</ConfirmPaymentWithAmount>
</soap:Body>
</soap:Envelope>';
    }

    protected function buildReversePaymentXmlString(string|int $token): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
<ReversalRequest xmlns="' . $this->getReversePaymentRequest() . '">
<requestData>
<LoginAccount>' . htmlspecialchars($this->loginAccount, ENT_XML1, 'UTF-8') . '</LoginAccount>
<Token>' . htmlspecialchars((string) $token, ENT_XML1, 'UTF-8') . '</Token>
</requestData>
</ReversalRequest>
</soap:Body>
</soap:Envelope>';
    }

    protected function getSaleSoapAction(): string
    {
        return 'https://pec.Shaparak.ir/NewIPGServices/Sale/SaleService/SalePaymentRequest';
    }

    protected function getConfirmSoapAction(): string
    {
        return 'https://pec.Shaparak.ir/NewIPGServices/Confirm/ConfirmService/ConfirmPaymentWithAmount';
    }

    protected function getReverseSoapAction(): string
    {
        return 'https://pec.Shaparak.ir/NewIPGServices/Reversal/ReversalService/ReversalRequest';
    }

    protected function getSalePaymentRequest(): string
    {
        return 'https://pec.Shaparak.ir/NewIPGServices/Sale/SaleService';
    }

    protected function getConfirmPaymentRequest(): string
    {
        return 'https://pec.Shaparak.ir/NewIPGServices/Confirm/ConfirmService';
    }

    protected function getReversePaymentRequest(): string
    {
        return 'https://pec.Shaparak.ir/NewIPGServices/Reversal/ReversalService';
    }
}
