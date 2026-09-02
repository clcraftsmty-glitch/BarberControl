<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Sale;

class ThermalTicketPdf
{
    private const PAGE_WIDTH = 226.77;

    public function __construct(private PdfLogo $logo) {}

    /** @return array<int, array{text: string, font: string, size: int, align: string, gap?: int}> */
    public function lines(Sale $sale): array
    {
        $sale->loadMissing(['creator']);
        $settings = BusinessSetting::current();
        $tax = $settings->includedTaxBreakdown($sale->total);
        $reference = $sale->payment_reference ? 'Ref: '.$sale->payment_reference : null;

        $lines = [
            ['text' => mb_strtoupper($settings->business_name), 'font' => 'bold', 'size' => 13, 'align' => 'center', 'gap' => 16],
            ['text' => mb_strtoupper($settings->ticket_header ?: 'Gestión profesional'), 'font' => 'regular', 'size' => 8, 'align' => 'center', 'gap' => 13],
            ...($settings->legal_name ? [['text' => $settings->legal_name, 'font' => 'regular', 'size' => 8, 'align' => 'center']] : []),
            ...($settings->tax_id ? [['text' => 'RFC: '.$settings->tax_id, 'font' => 'regular', 'size' => 8, 'align' => 'center']] : []),
            ...($settings->address ? [['text' => $settings->address, 'font' => 'regular', 'size' => 8, 'align' => 'center']] : []),
            ...($settings->phones ? [['text' => 'Tel: '.implode(' / ', $settings->phones), 'font' => 'regular', 'size' => 8, 'align' => 'center']] : []),
            ['text' => str_repeat('-', 34), 'font' => 'regular', 'size' => 9, 'align' => 'left', 'gap' => 13],
            ['text' => 'TICKET '.$sale->folio, 'font' => 'bold', 'size' => 10, 'align' => 'center', 'gap' => 15],
            ['text' => 'Fecha: '.$sale->paid_at->format('d/m/Y H:i'), 'font' => 'regular', 'size' => 9, 'align' => 'left'],
            ['text' => 'Cliente: '.$sale->client_name_snapshot, 'font' => 'regular', 'size' => 9, 'align' => 'left'],
            ['text' => 'Telefono: '.$sale->client_phone_snapshot, 'font' => 'regular', 'size' => 9, 'align' => 'left'],
            ['text' => 'Barbero: '.$sale->barber_name_snapshot, 'font' => 'regular', 'size' => 9, 'align' => 'left'],
            ['text' => str_repeat('-', 34), 'font' => 'regular', 'size' => 9, 'align' => 'left', 'gap' => 13],
            ['text' => $sale->service_name_snapshot, 'font' => 'bold', 'size' => 10, 'align' => 'left'],
            ['text' => $sale->service_duration_minutes_snapshot.' minutos', 'font' => 'regular', 'size' => 9, 'align' => 'left'],
            ['text' => 'Precio: '.$settings->formatMoney($sale->unit_price_snapshot), 'font' => 'regular', 'size' => 9, 'align' => 'right'],
            ['text' => str_repeat('-', 34), 'font' => 'regular', 'size' => 9, 'align' => 'left', 'gap' => 13],
            ...($tax['tax'] > 0 ? [
                ['text' => 'Subtotal: '.$settings->formatMoney($tax['subtotal']), 'font' => 'regular', 'size' => 9, 'align' => 'right'],
                ['text' => $settings->tax_name.' '.$settings->tax_rate.'% incluido: '.$settings->formatMoney($tax['tax']), 'font' => 'regular', 'size' => 9, 'align' => 'right'],
            ] : []),
            ['text' => 'TOTAL: '.$settings->formatMoney($sale->total), 'font' => 'bold', 'size' => 13, 'align' => 'right', 'gap' => 17],
            ['text' => 'Pago: '.$sale->payment_method->label(), 'font' => 'regular', 'size' => 9, 'align' => 'left'],
        ];

        if ($reference) {
            $lines[] = ['text' => $reference, 'font' => 'regular', 'size' => 9, 'align' => 'left'];
        }

        $lines[] = ['text' => 'Atendio: '.($sale->creator?->name ?? 'Sistema'), 'font' => 'regular', 'size' => 9, 'align' => 'left'];
        $lines[] = ['text' => 'Estado: '.$sale->status->label(), 'font' => 'bold', 'size' => 9, 'align' => 'left'];
        $lines[] = ['text' => str_repeat('-', 34), 'font' => 'regular', 'size' => 9, 'align' => 'left', 'gap' => 14];
        $lines[] = ['text' => $settings->ticket_footer ?: 'Gracias por tu visita', 'font' => 'bold', 'size' => 10, 'align' => 'center'];
        $lines[] = ['text' => 'Conserva este comprobante', 'font' => 'regular', 'size' => 8, 'align' => 'center'];

        return $this->wrapLines($lines);
    }

    public function render(Sale $sale): string
    {
        $lines = $this->lines($sale);
        $logo = $this->logo->image();
        $logoDimensions = $logo ? $this->logo->dimensions($logo, 140, 72) : null;
        $logoSpace = $logoDimensions ? $logoDimensions['height'] + 12 : 0;
        $height = max(340, 48 + $logoSpace + array_sum(array_map(fn (array $line): int => $line['gap'] ?? 12, $lines)));
        $content = '';
        $y = $height - 25;

        if ($logo && $logoDimensions) {
            $x = (self::PAGE_WIDTH - $logoDimensions['width']) / 2;
            $logoY = $y - $logoDimensions['height'];
            $content .= sprintf(
                "q %.2F 0 0 %.2F %.2F %.2F cm /Logo Do Q\n",
                $logoDimensions['width'],
                $logoDimensions['height'],
                $x,
                $logoY,
            );
            $y = $logoY - 12;
        }

        foreach ($lines as $line) {
            $text = $this->encode($line['text']);
            $fontKey = $line['font'] === 'bold' ? 'F2' : 'F1';
            $size = $line['size'];
            $textWidth = strlen($text) * $size * 0.6;
            $x = match ($line['align']) {
                'center' => max(12, (self::PAGE_WIDTH - $textWidth) / 2),
                'right' => max(12, self::PAGE_WIDTH - 12 - $textWidth),
                default => 12,
            };
            $content .= sprintf(
                "BT /%s %d Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
                $fontKey,
                $size,
                $x,
                $y,
                $this->escape($text),
            );
            $y -= $line['gap'] ?? 12;
        }

        return $this->document($content, $height, $logo);
    }

    /**
     * @param  array<int, array{text: string, font: string, size: int, align: string, gap?: int}>  $lines
     * @return array<int, array{text: string, font: string, size: int, align: string, gap?: int}>
     */
    private function wrapLines(array $lines): array
    {
        $wrapped = [];

        foreach ($lines as $line) {
            $parts = explode("\n", wordwrap($this->transliterate($line['text']), 34, "\n", true));

            foreach ($parts as $part) {
                $copy = $line;
                $copy['text'] = $part;
                $wrapped[] = $copy;
            }
        }

        return $wrapped;
    }

    /** @param array{width:int,height:int,color_space:string,bits:int,filter:string,data:string,alpha?:string}|null $logo */
    private function document(string $content, float $height, ?array $logo): string
    {
        $xObject = $logo ? ' /XObject << /Logo 7 0 R >>' : '';
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 5 0 R /F2 6 0 R >>%s >> /Contents 4 0 R >>', self::PAGE_WIDTH, $height, $xObject),
            4 => '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
            6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>',
        ];

        if ($logo) {
            $objects += $this->logo->objects($logo, 7, 8);
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $objectCount = max(array_keys($objects));
        $pdf .= 'xref'."\n0 ".($objectCount + 1)."\n0000000000 65535 f \n";

        for ($number = 1; $number <= $objectCount; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= 'trailer'."\n<< /Size ".($objectCount + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function transliterate(string $text): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    }

    private function encode(string $text): string
    {
        return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: $text;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
