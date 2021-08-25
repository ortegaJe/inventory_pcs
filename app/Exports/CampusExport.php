<?php

namespace App\Exports;

use App\Models\Campu;
use App\Models\CampuUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class CampusExport implements
    FromCollection,
    ShouldAutoSize,
    //ShouldAutoSize,
    WithColumnWidths,
    WithHeadings,
    WithEvents,
    WithCustomStartCell

{
    use Exportable;

    private $fileName = "export_inventory_computers.xlsx";

    public function __construct(int $campuId)
    {
        $this->campuId = $campuId;
    }

    public function collection()
    {
        //DB::statement(DB::raw('set @rownum=0'));
        $pcs = DB::table('view_exports_all_pcs')
            //->select(DB::raw('@rownum  := @rownum  + 1 AS rownum'))
            ->where('CampuID', $this->campuId)
            ->orderByDesc('FechaCreacion')
            ->get();

        return $pcs;
    }

    public function headings(): array
    {
        return [
            '#', //28
            'CODIGO INVENTARIO',
            'ACTIVO FIJO',
            'MARCA',
            'MODELO',
            'SERIAL',
            'SERIAL MONITOR',
            'TIPO',
            'MEMORIA RAM SLOT 01',
            'MEMORIA RAM SLOT 02',
            'DISCO DURO 01',
            'DISCO DURO 02',
            'PROCESADOR',
            'SEDE',
            'IP',
            'MAC',
            'DOMINIO',
            'SISTEMA OPERATIVO',
            'ANYDESK',
            'NOMBRE EQUIPO',
            'UBICACIÓN',
            'OBSERVACIÓN',
            'FECHA DE CREACIÓN',
            'FECHA DE ACTUALIZACIÓN',
            'ESTADO',
            //'CAMPUID',
            //'TECNICOID',
            'REGISTRADO POR',
        ];
    }

    public function startCell(): string
    {
        return 'A3';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            //'B' => 45,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getStyle('A1:AA500')->getFont()->setName('Nunito');
                $event->sheet->insertNewColumnBefore('A', 1);
                $event->sheet->getRowDimension('2')->setRowHeight(30);
                $event->sheet->getRowDimension('3')->setRowHeight(25);
                $event->sheet->setAutoFilter('B3:AA3');
                $event->sheet->mergeCells('B1:C1');
                $event->sheet->getCell('B1')->setValue("Generado: ");
                $event->sheet->mergeCells('D1:AA1');
                $time = Carbon::now('America/Bogota')->format('d/m/Y h:i A');
                $event->sheet->setCellValue('D1', ($time));
                $event->sheet->getStyle('D1')->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_YYYYMMDDSLASH);
                $event->sheet->mergeCells('B2:AA2');
                $event->sheet->getStyle('B2:AA2')
                    ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
                $event->sheet->getCell('B2')->setValue("INVENTARIO DE EQUIPOS REGISTRADOS VIVA 1A IPS");
                /*for ($cells = 4; $cells <= 500; $cells++) {
                    $hashColumn = Hash::make('');

                    $event->sheet->getCell('AB' . $cells . '')->setValue($hashColumn);
                }*/
                $event->sheet->getColumnDimension('AB')->setVisible(false);
                $event->sheet->getStyle('B2:AA2')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 18,
                    ],
                ]);
                $event->sheet->getStyle('B3:AA3')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        "color" => ["rgb" => "FFFFFF"]
                    ],
                    "fill" => [
                        "fillType" => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        "startColor" => ["rgb" => "636E72"]
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => '#7f8c8d'],
                        ],
                    ]
                ]);
                $event->sheet->getStyle('B4:AA500')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['argb' => '#7f8c8d'],
                        ],
                    ],
                    'font' => [
                        'size' => 9,
                    ],
                ]);
            }
        ];
    }
}
