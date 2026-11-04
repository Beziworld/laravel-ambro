<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Tests;

use Beziworld\Ambro\AmbroServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const string SANDBOX = 'http://dev.api.ambro.opennet.pl/api.asmx';

    public const string PRODUCTION = 'http://api.ambro.opennet.pl/api.asmx';

    public const string NS = 'http://tempuri.org/';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [AmbroServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ambro.username', 'demo');
        $app['config']->set('ambro.password', 'demo');
        $app['config']->set('ambro.sandbox', true);
        $app['config']->set('ambro.read_retries', 1);
        $app['config']->set('ambro.sender', [
            'name' => 'ACME Sp. z o.o.',
            'address' => 'ul. Prosta 1',
            'city' => 'Warszawa',
            'post_code' => '00-001',
            'person' => 'Jan Nowak',
            'phone' => '500600700',
            'email' => 'magazyn@example.com',
        ]);
    }

    /**
     * Wraps the content of an {Operation}Result element in an ASMX response envelope.
     */
    public static function asmx(string $operation, string $content = '', int $code = 0, ?string $description = null): string
    {
        $status = '<responseCode>' . $code . '</responseCode>' . ($description !== null ? '<responseDescription>' . $description . '</responseDescription>' : '');

        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
            . '<soap:Body><' . $operation . 'Response xmlns="' . self::NS . '"><' . $operation . 'Result>' . $status . $content . '</' . $operation . 'Result></'
            . $operation . 'Response></soap:Body></soap:Envelope>';
    }

    public static function fault(string $message): string
    {
        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><soap:Fault><faultcode>soap:Server</faultcode><faultstring>'
            . $message . '</faultstring><detail /></soap:Fault></soap:Body></soap:Envelope>';
    }

    /**
     * Fakes responses per operation, selected by the SOAPAction header.
     *
     * @param array<string, string> $responses Operation name to response body.
     */
    public static function fakeAmbro(array $responses): void
    {
        Http::fake(static function (Request $request) use ($responses) {
            $operation = self::operation($request);

            return isset($responses[$operation])
                ? Http::response($responses[$operation], 200, ['Content-Type' => 'text/xml; charset=utf-8'])
                : Http::response(self::fault('Unexpected operation ' . $operation), 500);
        });
    }

    public static function operation(Request $request): string
    {
        return substr(trim($request->header('SOAPAction')[0] ?? '', '"'), strlen(self::NS));
    }

    public static function xpath(Request $request): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadXML($request->body());
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xpath->registerNamespace('t', self::NS);

        return $xpath;
    }

    /**
     * String value of the first node matching an XPath expression in the request body.
     */
    public static function at(Request $request, string $expression): string
    {
        return (string) self::xpath($request)->evaluate('string(' . $expression . ')');
    }

    public static function nodes(Request $request, string $expression): int
    {
        return (int) self::xpath($request)->evaluate('count(' . $expression . ')');
    }
}
