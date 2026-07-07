<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    public function __construct(
        protected Parser $parser = new Parser,
    ) {}

    public function extractFromPath(string $path): string
    {
        try {
            $pdf = $this->parser->parseFile($path);
            $text = trim($pdf->getText());
        } catch (\Throwable) {
            return '';
        }

        // Normalize whitespace for predictable LIKE search behavior.
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}

