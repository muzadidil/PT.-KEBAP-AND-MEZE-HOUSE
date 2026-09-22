<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel satu menu: sheet data dengan judul kolom, format, dan
 * aturan isian per kolom; sheet petunjuk dengan aturan dan contoh; dan
 * sheet daftar pilihan yang disembunyikan.
 *
 * Sheet data dibuka pertama: yang mengunduh template satu menu biasanya
 * langsung ingin mengisi. Petunjuk tiap kolom muncul sendiri saat selnya
 * dipilih, jadi sheet petunjuk tidak wajib dibaca dulu.
 */
class SheetTemplate
{
    use StylesWorkbook;

    /** Baris yang diberi aturan isian. Aturan tidak membuat sel, jadi murah. */
    public const ROWS = 1000;

    /** @var array<string, string> nama kolom => rentang daftarnya */
    protected array $lists = [];

    public function __construct(protected ExcelSheet $sheet) {}

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        $values = [];
        $labels = [];

        foreach ($this->sheet->getColumns() as $column) {
            if (in_array($column->type, [Column::CHOICE, Column::BOOLEAN], true)) {
                $values[$column->name] = array_values(array_map('strval', $column->options()));
                $labels[$column->name] = __($column->label);
            }
        }

        $this->lists = $this->listRanges(__('excel.lists_tab'), $values);

        $this->addDataSheet($book);
        $this->addGuide($book);
        $this->addListSheet($book, __('excel.lists_tab'), $values, $labels);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    protected function addDataSheet(Spreadsheet $book): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle($this->sheet->sheetName());

        $columns = $this->sheet->getColumns();
        $sheet->fromArray(array_map(fn (Column $c) => $c->title(), $columns), null, 'A1');
        $this->styleHeader($sheet, 'A1:'.Coordinate::stringFromColumnIndex(count($columns)).'1');

        foreach ($columns as $i => $column) {
            $this->prepareColumn($sheet, $column, Coordinate::stringFromColumnIndex($i + 1));
        }

        $sheet->freezePane('A2');
    }

    protected function prepareColumn(Worksheet $sheet, Column $column, string $col): void
    {
        $sheet->getColumnDimension($col)->setWidth($column->width ?? match ($column->type) {
            Column::DATE => 13,
            Column::MONEY => 15,
            Column::NUMBER, Column::BOOLEAN => 11,
            default => 22,
        });

        // Format seluruh kolom, jadi baris yang ditambah sendiri ikut berformat.
        $format = match (true) {
            $column->type === Column::DATE => 'dd/mm/yyyy',
            $column->type === Column::MONEY => '#,##0',
            $column->type === Column::NUMBER => '0',
            $column->asText => NumberFormat::FORMAT_TEXT,
            default => null,
        };

        if ($format) {
            $sheet->getStyle("{$col}:{$col}")->getNumberFormat()->setFormatCode($format);
        }

        $rule = $this->validation($column);
        $rule->setPromptTitle(mb_substr(__($column->label), 0, 32));
        $rule->setErrorTitle(mb_substr(__($column->label), 0, 32));

        $sheet->setDataValidation($col.'2:'.$col.(static::ROWS + 1), $rule);
    }

    /** Aturan isian satu kolom, beserta pesan yang muncul saat selnya dipilih. */
    protected function validation(Column $column): DataValidation
    {
        $rule = (new DataValidation)
            ->setAllowBlank(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setErrorStyle(DataValidation::STYLE_STOP);

        $prompt = $column->required ? __('excel.prompt.required').' ' : '';

        switch ($column->type) {
            case Column::DATE:
                return $rule->setType(DataValidation::TYPE_DATE)
                    ->setOperator(DataValidation::OPERATOR_BETWEEN)
                    ->setFormula1('DATE(2000,1,1)')
                    ->setFormula2('DATE(2099,12,31)')
                    ->setError(__('excel.error.date_rule'))
                    ->setPrompt($prompt.__('excel.prompt.date'));

            case Column::MONEY:
            case Column::NUMBER:
                $rule->setType(DataValidation::TYPE_WHOLE)
                    ->setError($column->max !== null
                        ? __('excel.error.between', ['min' => $column->min, 'max' => $column->max])
                        : __('excel.error.min', ['min' => $column->min]))
                    ->setPrompt($prompt.__($column->type === Column::MONEY ? 'excel.prompt.money' : 'excel.prompt.number'));

                return $column->max !== null
                    ? $rule->setOperator(DataValidation::OPERATOR_BETWEEN)->setFormula1((string) $column->min)->setFormula2((string) $column->max)
                    : $rule->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)->setFormula1((string) $column->min);

            case Column::BOOLEAN:
            case Column::CHOICE:
                $open = ! $column->closed;
                $message = match (true) {
                    $column->creator !== null => 'excel.prompt.creates',
                    $open => 'excel.prompt.open_list',
                    default => 'excel.prompt.closed_list',
                };

                $rule->setPrompt($prompt.__($message));

                if (! isset($this->lists[$column->name])) {
                    return $rule->setType(DataValidation::TYPE_NONE);
                }

                return $rule->setType(DataValidation::TYPE_LIST)
                    ->setShowDropDown(true)
                    ->setFormula1($this->lists[$column->name])
                    ->setErrorStyle($open ? DataValidation::STYLE_WARNING : DataValidation::STYLE_STOP)
                    ->setError(__($open ? 'excel.error.open_list' : 'excel.error.closed_list'));

            default:
                if ($column->maxLength) {
                    return $rule->setType(DataValidation::TYPE_TEXTLENGTH)
                        ->setOperator(DataValidation::OPERATOR_LESSTHANOREQUAL)
                        ->setFormula1((string) $column->maxLength)
                        ->setError(__('excel.error.too_long', ['max' => $column->maxLength]))
                        ->setPrompt($prompt.__('excel.prompt.text', ['max' => $column->maxLength]));
                }

                return $rule->setType(DataValidation::TYPE_NONE)
                    ->setPrompt(trim($prompt) ?: __('excel.prompt.free'));
        }
    }

    protected function addGuide(Spreadsheet $book): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle(__('excel.guide_tab'));

        $columns = $this->sheet->getColumns();
        $title = ['title' => $this->sheet->title(), 'sheet' => $this->sheet->sheetName()];

        for ($i = 1; $i <= max(8, count($columns)); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(18);
        }

        $sheet->setCellValue('A1', __('excel.guide.title', $title));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB(self::COLOR_HEADER);
        $sheet->setCellValue('A2', __('excel.guide.subtitle', $title));

        $row = $this->guideHeading($sheet, 4, __('excel.guide.rules'));

        foreach ($this->rules() as $n => $text) {
            $sheet->setCellValue('A'.$row++, ($n + 1).'. '.$text);
        }

        if ($examples = $this->sheet->getExamples()) {
            $row = $this->guideHeading($sheet, $row + 1, __('excel.guide.examples'));

            $sheet->fromArray(array_map(fn (Column $c) => $c->title(), $columns), null, 'A'.$row);
            $this->styleHeader($sheet, "A{$row}:".Coordinate::stringFromColumnIndex(count($columns)).$row);
            $row++;

            foreach ($examples as $example) {
                $this->writeExampleRow($sheet, $row++, array_map(
                    fn (Column $c) => $c->display($example[$c->name] ?? null),
                    $columns,
                ));
            }
        }
    }

    /** @return array<int, string> */
    protected function rules(): array
    {
        $types = array_map(fn (Column $c) => $c->type, $this->sheet->getColumns());
        $keys = $this->sheet->getMatchBy()[0] ?? [];

        return array_values(array_filter([
            __('excel.rule.header'),
            __('excel.rule.required'),
            in_array(Column::DATE, $types, true) ? __('excel.rule.date') : null,
            array_intersect([Column::MONEY, Column::NUMBER], $types) ? __('excel.rule.money') : null,
            array_intersect([Column::CHOICE, Column::BOOLEAN], $types) ? __('excel.rule.list') : null,
            $keys
                ? __('excel.rule.update', ['keys' => implode(' + ', array_map(fn (string $k) => __($this->sheet->column($k)?->label ?? $k), $keys))])
                : __('excel.rule.dedupe'),
            __('excel.rule.all_or_nothing'),
            __('excel.rule.rows', ['rows' => static::ROWS]),
        ]));
    }
}
