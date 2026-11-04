<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Support\Soap;

/**
 * Label or waybill document returned by CreateShipment, GetLabel, GetStrictLabel or GetWaybill.
 *
 * content holds the decoded binary document (PDF); raw keeps the other
 * fields of the response node without the base64 payload.
 */
final readonly class Label
{
    /**
     * @param array<array-key, mixed> $raw
     */
    public function __construct(
        public ?string $parcelId,
        public string $format,
        public string $content,
        public array $raw = [],
    ) {}

    /**
     * @param array<array-key, mixed> $node Label or Waybill element.
     */
    public static function fromSoap(array $node): self
    {
        $data = Soap::string($node, 'MimeData');
        $decoded = $data !== null ? base64_decode($data, true) : false;
        $raw = $node;
        unset($raw['MimeData']);

        return new self(
            parcelId: Soap::string($node, 'ParcelID'),
            format: Soap::string($node, 'MimeFormat') ?? 'PDF',
            content: $decoded !== false ? $decoded : '',
            raw: $raw,
        );
    }

    public function base64(): string
    {
        return base64_encode($this->content);
    }

    public function mimeType(): string
    {
        return 'application/pdf';
    }

    /**
     * Suggested file name, for example "label-AMB123456.pdf".
     */
    public function filename(string $prefix = 'label'): string
    {
        return sprintf('%s-%s.pdf', $prefix, preg_replace('/[^A-Za-z0-9_-]/', '', $this->parcelId ?? 'document'));
    }
}
