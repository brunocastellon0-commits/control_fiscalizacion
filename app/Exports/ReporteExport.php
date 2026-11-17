<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array{columnas: array<int, array{clave: string, titulo: string}>, data: array<int, array<string, mixed>>, titulo?: string, codigo?: string}  $datos
     */
    public function __construct(private readonly array $datos) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $claves = array_column($this->datos['columnas'], 'clave');

        return array_map(
            fn (array $fila): array => array_map(fn (string $clave): mixed => $fila[$clave] ?? '', $claves),
            $this->datos['data'],
        );
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_column($this->datos['columnas'], 'titulo');
    }

    public function title(): string
    {
        $codigo = $this->datos['codigo'] ?? 'REPORTE';

        return mb_substr("{$codigo} - ".($this->datos['titulo'] ?? 'Reporte'), 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $ultimaColumna = $sheet->getHighestColumn();
        $ultimaFila = max($sheet->getHighestRow(), 1);

        $sheet->getStyle("A1:{$ultimaColumna}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1B5E20'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle("A1:{$ultimaColumna}{$ultimaFila}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
            ],
        ]);

        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$ultimaColumna}1");

        return [];
    }
}
