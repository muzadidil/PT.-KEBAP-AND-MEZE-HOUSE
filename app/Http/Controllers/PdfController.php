<?php

namespace App\Http\Controllers;

use App\Filament\Admin\Pages\MeetingProgress;
use App\Filament\Admin\Pages\Reports\CashExpenses;
use App\Filament\Admin\Pages\Reports\OnlineTransfers;
use App\Filament\Admin\Pages\Reports\Tax;
use App\Filament\Admin\Pages\Zeytin\MonthlyLedger;
use App\Filament\Admin\Resources\Payslips\PayslipResource;
use App\Models\Payslip;
use App\Support\Meetings\MeetingPdf;
use App\Support\Meetings\MeetingReport;
use App\Support\Payroll\PayslipPdf;
use App\Support\Reports\ListPdf;
use App\Support\Zeytin\DailyLedger;
use App\Support\Zeytin\PeriodPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * PDF yang dibuka di tab baru, bukan diunduh diam-diam.
 *
 * Tombol lama mengunduh berkas lewat Livewire: tidak ada yang terbuka di
 * layar, dan berkasnya hanya muncul di folder Unduhan — terlihat seperti
 * tombolnya tidak bekerja. Di sini PDF-nya disajikan `inline`, jadi peramban
 * menampilkannya di penampil PDF-nya sendiri, lengkap dengan tombol unduh
 * dan cetak.
 *
 * Rutenya ada di dalam panel admin, jadi hanya bisa dibuka orang yang sudah
 * masuk; hak per halamannya diperiksa lagi di sini.
 */
class PdfController extends Controller
{
    /** Buku Besar Bulanan untuk rentang dan pengelompokan yang dipilih. */
    public function ledger(Request $request): Response
    {
        abort_unless(MonthlyLedger::canAccess(), 403);

        $from = Carbon::parse($request->query('from') ?: Carbon::today()->startOfMonth())->startOfDay();
        $to = Carbon::parse($request->query('to') ?: Carbon::today())->startOfDay();
        $to = $to->lt($from) ? $from->copy() : $to;

        $grouping = in_array($request->query('grouping'), ['daily', 'monthly', 'yearly'], true)
            ? $request->query('grouping')
            : 'daily';

        return $this->inline(new PeriodPdf(DailyLedger::periodReport($from, $to), $grouping));
    }

    /**
     * Laporan berbentuk daftar: Pengeluaran Tunai, Transfer Online, Pajak.
     *
     * Rentang, penyaring, dan pencariannya dibawa di alamat, karena PDF
     * dibuka di tab baru dan tidak bisa membaca keadaan tabel di layar.
     */
    public function expenses(Request $request, string $report): Response
    {
        $classes = [
            CashExpenses::reportKey() => CashExpenses::class,
            OnlineTransfers::reportKey() => OnlineTransfers::class,
            Tax::reportKey() => Tax::class,
        ];

        abort_unless(isset($classes[$report]), 404);

        /** @var \App\Filament\Admin\Pages\Reports\ExpenseReport $page */
        $page = app($classes[$report]);

        abort_unless($page::canAccess(), 403);

        $filters = $request->only(['from', 'to', 'search', ...$page->filterColumns()]);

        return $this->inline(new ListPdf(
            title: $page->getTitle(),
            source: $page->source(),
            rows: $page->filteredQuery($filters)->get(),
            columns: $page->exportColumns(),
            filters: $filters,
        ));
    }

    public function payslip(Payslip $payslip): Response
    {
        abort_unless(PayslipResource::canAccess(), 403);

        return $this->inline(new PayslipPdf($payslip));
    }

    /** Progres Rapat: semua proyek, tanpa proyek, atau satu proyek. */
    public function meeting(Request $request): Response
    {
        abort_unless(MeetingProgress::canAccess(), 403);

        $scope = (string) $request->query('scope', 'all');
        $scope = in_array($scope, ['all', 'none'], true) || ctype_digit($scope) ? $scope : 'all';

        return $this->inline(new MeetingPdf(MeetingReport::make(), $scope));
    }

    protected function inline(PeriodPdf|PayslipPdf|MeetingPdf|ListPdf $pdf): Response
    {
        return response($pdf->render(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename().'"',
        ]);
    }
}
