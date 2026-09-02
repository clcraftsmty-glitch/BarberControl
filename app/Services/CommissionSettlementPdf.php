<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\CommissionSettlement;

class CommissionSettlementPdf
{
    private const PAGE_WIDTH = 612.0;

    private const PAGE_HEIGHT = 792.0;

    public function __construct(private PdfLogo $logo) {}

    public function render(CommissionSettlement $settlement): string
    {
        $settlement->loadMissing(['barber', 'creator', 'commissions.sale', 'adjustments.authorizer']);
        $lines = $this->lines($settlement);
        $logo = $this->logo->image();
        $logoDimensions = $logo ? $this->logo->dimensions($logo, 145, 90) : null;
        $logoSpace = $logoDimensions ? $logoDimensions['height'] + 12 : 0;
        $height = max(self::PAGE_HEIGHT, 80 + $logoSpace + count($lines) * 15);
        $content = '';
        $y = $height - 45;

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

        foreach ($lines as [$text, $bold, $size]) {
            $encoded = $this->encode($text);
            $content .= sprintf(
                "BT /%s %d Tf 1 0 0 1 42 %.2F Tm (%s) Tj ET\n",
                $bold ? 'F2' : 'F1',
                $size,
                $y,
                $this->escape($encoded),
            );
            $y -= $size + 5;
        }

        return $this->document($content, $height, $logo);
    }

    /** @return list<array{string, bool, int}> */
    private function lines(CommissionSettlement $settlement): array
    {
        $settings = BusinessSetting::current();
        $lines = [
            [mb_strtoupper($settings->business_name).' - COMPROBANTE DE LIQUIDACION', true, 16],
            ['Folio: '.$settlement->folio.'    Fecha: '.$settlement->paid_at->format('d/m/Y H:i'), true, 11],
            ['Barbero: '.$settlement->barber->display_name, false, 11],
            ['Periodo: '.$settlement->period_type->label().' del '.$settlement->period_start->format('d/m/Y').' al '.$settlement->period_end->format('d/m/Y'), false, 11],
            ['Forma de pago: '.$settlement->payment_method->label().($settlement->payment_reference ? ' - Ref. '.$settlement->payment_reference : ''), false, 10],
            [str_repeat('-', 88), false, 9],
            ['SERVICIOS LIQUIDADOS', true, 12],
        ];

        foreach ($settlement->commissions as $commission) {
            $lines[] = [sprintf(
                '%s | %s | Base %s | %s%% | Comision %s',
                $commission->sale->folio,
                $commission->sale->service_name_snapshot,
                $settings->formatMoney($commission->base_amount),
                number_format((float) $commission->percentage, 2),
                $settings->formatMoney($commission->amount),
            ), false, 9];
        }

        if ($settlement->adjustments->isNotEmpty()) {
            $lines[] = [str_repeat('-', 88), false, 9];
            $lines[] = ['AJUSTES AUTORIZADOS', true, 12];
            foreach ($settlement->adjustments as $adjustment) {
                $lines[] = [sprintf(
                    '%s %s%s | %s | Autorizo: %s',
                    $adjustment->type->label(),
                    $adjustment->signedAmount() >= 0 ? '+' : '-',
                    $settings->formatMoney(abs($adjustment->signedAmount())),
                    $adjustment->reason,
                    $adjustment->authorizer?->name ?? 'Usuario eliminado',
                ), false, 9];
            }
        }

        $lines[] = [str_repeat('-', 88), false, 9];
        $lines[] = ['Comisiones: '.$settings->formatMoney($settlement->commissions_total), false, 11];
        $lines[] = ['Ajustes: '.$settings->formatMoney($settlement->adjustments_total), false, 11];
        $lines[] = ['TOTAL PAGADO: '.$settings->formatMoney($settlement->total_paid), true, 15];
        $lines[] = ['Registró: '.($settlement->creator?->name ?? 'Usuario eliminado'), false, 10];
        if ($settlement->notes) {
            $lines[] = ['Notas: '.$settlement->notes, false, 10];
        }

        return $this->wrap($lines);
    }

    /** @param list<array{string, bool, int}> $lines @return list<array{string, bool, int}> */
    private function wrap(array $lines): array
    {
        $wrapped = [];
        foreach ($lines as [$text, $bold, $size]) {
            foreach (explode("\n", wordwrap($this->transliterate($text), 88, "\n", true)) as $part) {
                $wrapped[] = [$part, $bold, $size];
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
            4 => '<< /Length '.strlen($content).">>\nstream\n{$content}endstream",
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

        return $pdf.'trailer'."\n<< /Size ".($objectCount + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
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
