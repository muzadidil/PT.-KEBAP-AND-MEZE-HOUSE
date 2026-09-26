<?php

namespace App\Support\Payroll;

use App\Models\Payslip;
use App\Support\Terbilang;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Slip gaji sebagai PDF, dan data "kertas" slip yang sama untuk pratinjau.
 *
 * Pratinjau di formulir dan PDF merender satu templat yang sama
 * (resources/views/payslips/paper.blade.php), jadi yang dilihat saat mengisi
 * persis yang tercetak. Tata letaknya meniru aplikasi Slip Gaji aslinya.
 */
class PayslipPdf
{
    public function __construct(protected Payslip $slip) {}

    /**
     * Isi kertas slip.
     *
     * @param  bool  $forPdf  logo sebagai berkas di disk (dompdf), bukan URL
     * @return array<string, mixed>
     */
    public static function paper(Payslip $slip, bool $forPdf = false): array
    {
        $period = $slip->period->copy()->locale('id');

        return [
            'letterhead' => PayslipLetterhead::get(),
            'logo' => $forPdf ? PayslipLetterhead::logoPath() : PayslipLetterhead::logoUrl(),
            'number' => $slip->number ?: Payslip::nextNumber($slip->period),
            'period' => $period->translatedFormat('F Y'),
            'issued' => $slip->issued_on->copy()->locale('id')->translatedFormat('j F Y'),
            'name' => $slip->employee_name,
            'nik' => $slip->employee_nik,
            'position' => $slip->employee_position,
            'earnings' => [
                ['label' => __('payroll.slip.basic_salary', [], 'id'), 'amount' => (int) $slip->basic_salary],
                ...$slip->earnings,
            ],
            'deductions' => $slip->deductions,
            'note' => $slip->note,
            'total_earnings' => $slip->total_earnings,
            'total_deductions' => $slip->total_deductions,
            'net_pay' => $slip->net_pay,
            'spelled' => Terbilang::rupiah($slip->net_pay),
        ];
    }

    public function render(): string
    {
        return Pdf::loadView('pdf.payslip', ['paper' => static::paper($this->slip, forPdf: true)])
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /** SlipGaji_Nama_Karyawan_September_2026.pdf, seperti aplikasi aslinya. */
    public function filename(): string
    {
        $name = Str::of($this->slip->employee_name ?: 'karyawan')->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_');

        return 'SlipGaji_'.$name.'_'.$this->slip->period->copy()->locale('id')->translatedFormat('F_Y').'.pdf';
    }
}
