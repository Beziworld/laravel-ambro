<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Minimal SOAP 1.1 document/literal serializer and parser.
 *
 * Requests are built from plain arrays: every element is qualified with the
 * operation namespace (the Ambro Express ASMX service uses
 * elementFormDefault="qualified"), a list under a key becomes repeated
 * elements with that name, booleans become "true"/"false" and nulls are
 * skipped. Responses are converted back into arrays keyed by local element
 * names; repeated siblings become lists. ext-soap is not required, and the
 * transport is the Laravel HTTP client, which keeps calls fakeable.
 */
final class Soap
{
    public const string ENVELOPE_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

    public const string XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

    private const string PREFIX = 'tns';

    /**
     * Builds a SOAP 1.1 envelope whose body holds one operation element.
     *
     * @param array<array-key, mixed> $parameters
     */
    public static function envelope(string $namespace, string $operation, array $parameters = []): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $envelope = $document->createElementNS(self::ENVELOPE_NS, 'soap:Envelope');
        $document->appendChild($envelope);

        $body = $document->createElementNS(self::ENVELOPE_NS, 'soap:Body');
        $envelope->appendChild($body);

        $root = $document->createElementNS($namespace, self::PREFIX . ':' . $operation);
        $body->appendChild($root);
        self::append($document, $root, $parameters, $namespace);

        return (string) $document->saveXML();
    }

    /**
     * Parses a SOAP response and returns the content of its first body element.
     *
     * @return array{fault: array{code: string, message: string}|null, body: array<array-key, mixed>}
     */
    public static function parse(string $xml): array
    {
        $document = new DOMDocument();

        if (trim($xml) === '' || ! @$document->loadXML($xml, LIBXML_NONET)) {
            return ['fault' => ['code' => 'Client', 'message' => 'The response is not valid XML.'], 'body' => []];
        }

        $body = null;

        foreach ($document->getElementsByTagNameNS(self::ENVELOPE_NS, 'Body') as $candidate) {
            $body = $candidate;

            break;
        }

        $content = $body !== null ? self::firstElement($body) : null;

        if ($content === null) {
            return ['fault' => ['code' => 'Client', 'message' => 'The response has no SOAP body.'], 'body' => []];
        }

        if ($content->localName === 'Fault') {
            $fault = self::toArray($content);
            $fault = is_array($fault) ? $fault : [];

            return [
                'fault' => [
                    'code' => is_string($fault['faultcode'] ?? null) ? $fault['faultcode'] : 'Server',
                    'message' => is_string($fault['faultstring'] ?? null) ? trim($fault['faultstring']) : 'SOAP fault',
                ],
                'body' => [],
            ];
        }

        $value = self::toArray($content);

        return ['fault' => null, 'body' => is_array($value) ? $value : ['value' => $value]];
    }

    /**
     * Normalises a node that may hold one element or a list of repeated elements into a list.
     *
     * @param string|null $child Key of the repeated element inside the node; null when the node itself is the value.
     * @return list<array<array-key, mixed>>
     */
    public static function items(mixed $node, ?string $child = null): array
    {
        if ($child !== null) {
            $node = is_array($node) ? ($node[$child] ?? null) : null;
        }

        if (! is_array($node)) {
            return [];
        }

        $list = array_is_list($node) ? $node : [$node];

        return array_values(array_filter($list, is_array(...)));
    }

    /**
     * Reads a trimmed, non-empty string from a parsed node.
     *
     * @param array<array-key, mixed> $node
     */
    public static function string(array $node, string $key): ?string
    {
        $value = $node[$key] ?? null;

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /**
     * @param array<array-key, mixed> $node
     */
    public static function int(array $node, string $key): ?int
    {
        $value = self::string($node, $key);

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    /**
     * Wraps a list of strings in the ASMX ArrayOfString shape.
     *
     * @param list<string> $values
     * @return array{string: list<string>}
     */
    public static function arrayOfString(array $values): array
    {
        return ['string' => $values];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function append(DOMDocument $document, DOMElement $parent, array $values, string $namespace): void
    {
        foreach ($values as $name => $value) {
            $name = (string) $name;

            if ($value === null) {
                continue;
            }

            if (is_array($value) && $value !== [] && array_is_list($value)) {
                foreach ($value as $item) {
                    self::appendOne($document, $parent, $name, $item, $namespace);
                }

                continue;
            }

            self::appendOne($document, $parent, $name, $value, $namespace);
        }
    }

    private static function appendOne(DOMDocument $document, DOMElement $parent, string $name, mixed $value, string $namespace): void
    {
        if ($value === null) {
            return;
        }

        $element = $document->createElementNS($namespace, self::PREFIX . ':' . $name);
        $parent->appendChild($element);

        if (is_array($value)) {
            self::append($document, $element, $value, $namespace);

            return;
        }

        $element->appendChild($document->createTextNode(self::text($value)));
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_float($value) => rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.'),
            is_scalar($value) => (string) $value,
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }

    private static function firstElement(DOMNode $node): ?DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    private static function toArray(DOMElement $element): array|string|null
    {
        $children = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        if ($children === []) {
            if ($element->getAttributeNS(self::XSI_NS, 'nil') === 'true') {
                return null;
            }

            return $element->textContent;
        }

        $grouped = [];

        foreach ($children as $child) {
            $grouped[(string) $child->localName][] = self::toArray($child);
        }

        return array_map(static fn(array $values): mixed => count($values) > 1 ? $values : $values[0], $grouped);
    }
}
