<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Support\Soap;

/**
 * Handover manifest for a set of shipments (GetManifest).
 */
final readonly class Manifest
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public ?string $number,
        public ?string $format,
        public string $content,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $node GetManifestResult element.
     */
    public static function fromSoap(array $node): self
    {
        $data = Soap::string($node, 'MimeData');
        $decoded = $data !== null ? base64_decode($data, true) : false;
        $raw = $node;
        unset($raw['MimeData']);

        return new self(
            number: Soap::string($node, 'ManifestNo'),
            format: Soap::string($node, 'MimeFormat'),
            content: $decoded !== false ? $decoded : '',
            raw: $raw,
        );
    }

    public function base64(): string
    {
        return base64_encode($this->content);
    }
}
