<?php

/*
| The column titles below are used both ways: as table labels (current
| language) and to recognise the column titles of a Moka file. The English
| text must therefore match the titles in the Moka file exactly — "Item
| Name", "Gross Sales", and so on. Changing one stops the importer from
| recognising the file.
*/

return [

    'nav' => [
        'item_sales' => 'Item Sales (Moka)',
    ],

    'page' => [
        'subheading' => 'Per-item summary from the Moka Report Item Sales. Kept apart from Daily Income: these figures are not part of Total sales in any report, so nothing is counted twice.',
        'empty_heading' => 'No Moka report yet',
        'empty_description' => 'Download Report Item Sales from Moka, then upload it with the Import from Moka button.',
    ],

    'action' => [
        'import' => 'Import from Moka',
    ],

    'import' => [
        'heading' => 'Import a report from Moka',
        'description' => 'Upload the file downloaded from Moka as-is (Excel or CSV); the report type is recognised automatically. Report Item Sales (per-item summary) goes to Item Sales. Report Transaction and Report Item Details (per receipt) go to Daily Income: Net Sales is added up per date and payment method, and dates that already have a row are skipped, never overwritten. If anything is wrong, nothing is saved.',
        'file' => 'Moka report file',
        'period_hint' => 'A Moka file does not carry its period. These boxes are filled in from the file name when it can be read; fill them in yourself if the name has no dates, and check them before importing.',
    ],

    'filter' => [
        'period' => 'Period',
        'category' => 'Category',
    ],

    'col' => [
        'period' => 'Period',
        'item_name' => 'Item Name',
        'variant_name' => 'Item Variant Name',
        'category_name' => 'Category Name',
        'sku' => 'SKU',
        'qty_sold' => 'Item Sold',
        'qty_refunded' => 'Item Refunded',
        'gross_sales' => 'Gross Sales',
        'discount' => 'Discount',
        'refund' => 'Refund',
        'net_sales' => 'Net Sales',
    ],

    'error' => [
        'no_header' => 'Header row not found. Columns looked for: :columns. Use the Report Item Sales file from Moka as-is.',
        'unknown_report' => 'This file is not recognised as a Moka report. Supported: Report Item Sales (per-item summary), and Report Transaction and Report Item Details (per receipt). Use the file downloaded from Moka as-is.',
        'period_missing' => 'The report period is not known: the file name carries no dates. Fill in the From and To boxes.',
        'empty' => 'The file has no item rows.',
        'period_order' => 'The "To" date cannot be before the "From" date.',
        'period_future' => 'The period cannot extend past today.',
        'fraction' => 'not a whole number (":value")',
        'net_mismatch' => 'Row :row (:name): Net Sales :net does not equal Gross Sales minus Discount and Refund (:expected). Make sure this is an intact Report Item Sales file.',
        'total_mismatch' => 'The Total row in the file (:file) does not match the sum of the item rows (:sum). The file may be truncated.',
        'overlap' => 'Period :periods already exists and its dates overlap this one, so the same items would be counted twice. Import the exact same period to replace it, or delete the old period on this page first.',
    ],

    'note' => [
        'compare' => 'Moka net sales :moka. Daily Income for the same period :books (:days days recorded), difference :diff. They come from different sources, so they need not match exactly; a large gap is worth checking.',
        'no_books' => 'Moka net sales :moka. There is no Daily Income for this period yet, so nothing was compared.',
        'separate' => 'Moka figures are not part of Total sales in any report.',
    ],

];
