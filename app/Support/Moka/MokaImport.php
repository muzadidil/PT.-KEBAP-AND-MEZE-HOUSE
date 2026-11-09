<?php

namespace App\Support\Moka;

use App\Support\Excel\ImportReport;
use App\Support\Zeytin\PosSalesImporter;
use App\Support\Zeytin\Workbook\Cells;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Satu pintu impor untuk semua laporan Moka: berkasnya diunggah apa adanya,
 * dan jenis laporannya dikenali dari judul kolomnya.
 *
 *   Report Item Sales                         rekap per menu → Penjualan per Menu
 *   Report Transaction / Report Item Details  per struk      → Pemasukan Harian
 *
 * Dulu keduanya punya tombol sendiri dan pengguna harus tahu berkas mana
 * untuk tombol mana; salah pilih berarti galat "judul tidak ditemukan".
 * Sekarang tombolnya satu, dan yang dikenali dari isinya bukan dari tempat
 * mengunggahnya. Aturan masing-masing pengimpor tidak berubah (ItemSalesImport
 * dan PosSalesImporter): semua atau tidak sama sekali, dan penjualan yang
 * sudah ada tidak ditimpa.
 */
class MokaImport
{
    public const ITEM_SALES = 'item_sales';

    public const RECEIPTS = 'receipts';

    /** Judul kolom dicari di sekian baris pertama saja. */
    protected const HEADER_SCAN = 30;

    /**
     * @param  array{0: Carbon, 1: Carbon}|null  $period  periode laporan, dari kotak Dari/Sampai atau dari nama berkas
     */
    public function __construct(protected ?array $period = null) {}

    public function import(string $path): ImportReport
    {
        try {
            $kind = static::detect($path);
        } catch (Throwable) {
            return ImportReport::failed([__('excel.pos.unreadable')]);
        }

        return match ($kind) {
            // Rekap per menu tidak punya tanggal sama sekali; tanpa periode
            // tidak ada yang bisa dicatat.
            self::ITEM_SALES => $this->period
                ? (new ItemSalesImport($this->period[0], $this->period[1]))->import($path)
                : ImportReport::failed([__('moka.error.period_missing')]),

            // Tanpa periode, tanggal dipakai apa adanya seperti sebelum ada
            // pengenal ini; dengan periode, tanggal dicocokkan ke periode itu.
            self::RECEIPTS => (new PosSalesImporter($this->period))->import($path),

            default => ImportReport::failed([__('moka.error.unknown_report')]),
        };
    }

    /**
     * Jenis laporan dari judul kolomnya, atau null kalau tidak dikenali.
     *
     * @return self::ITEM_SALES|self::RECEIPTS|null
     */
    public static function detect(string $path): ?string
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $book = $reader->load($path);

        try {
            $grid = $book->getSheet(0)->toArray(null, true, false, false);
        } finally {
            $book->disconnectWorksheets();
        }

        foreach (array_slice($grid, 0, self::HEADER_SCAN) as $cells) {
            // Tanda BOM di sel pertama berkas CSV bukan bagian judulnya.
            $titles = array_map(fn ($cell) => str_replace("\u{FEFF}", '', Cells::norm($cell)), $cells);

            if (in_array('item name', $titles, true) && in_array('item sold', $titles, true) && in_array('net sales', $titles, true)) {
                return self::ITEM_SALES;
            }

            if (in_array('date', $titles, true) && in_array('payment method', $titles, true) && in_array('net sales', $titles, true)) {
                return self::RECEIPTS;
            }
        }

        return null;
    }
}
