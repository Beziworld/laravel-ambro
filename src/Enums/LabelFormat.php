<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Enums;

/**
 * Label formats (LabelImageFormat): PDF for a label printer, PDFA4 for an A4 sheet.
 */
enum LabelFormat: string
{
    case Pdf = 'PDF';
    case PdfA4 = 'PDFA4';
}
