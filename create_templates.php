<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Create templates directory if it doesn't exist
if (!file_exists('templates')) {
    mkdir('templates', 0777, true);
}

// Create Products Template
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Products Template');

// Set headers
$headers = ['product_name', 'category', 'quantity', 'price'];
$sheet->fromArray($headers, NULL, 'A1');

// Add sample data
$sampleData = [
    ['Sample Product 1', 'Category 1', 100, 99.99],
    ['Sample Product 2', 'Category 2', 50, 149.99]
];
$sheet->fromArray($sampleData, NULL, 'A2');

// Style the header row
$headerStyle = [
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E2EFDA']
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
    ],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
];
$sheet->getStyle('A1:D1')->applyFromArray($headerStyle);

// Set column widths
$sheet->getColumnDimension('A')->setWidth(30);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(15);

// Save Products Template
$writer = new Xlsx($spreadsheet);
$writer->save('templates/products_template.xlsx');

// Create Movements Template
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Movements Template');

// Set headers
$headers = ['product_id', 'quantity_change', 'type', 'reference_type', 'reference_id', 'notes'];
$sheet->fromArray($headers, NULL, 'A1');

// Add sample data with more realistic examples
$sampleData = [
    [1, 50, 'in', 'purchase', 1, 'Initial stock purchase'],
    [1, -10, 'out', 'sale', 1, 'Regular customer sale'],
    [2, 25, 'in', 'purchase', 2, 'Restock order'],
    [2, -5, 'out', 'sale', 2, 'Walk-in customer'],
    [1, 15, 'adjustment', 'adjustment', 0, 'Stock count adjustment']
];
$sheet->fromArray($sampleData, NULL, 'A2');

// Style the header row
$sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

// Add data validation for type column
$typeValidation = $sheet->getCell('C2')->getDataValidation();
$typeValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
$typeValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
$typeValidation->setAllowBlank(false);
$typeValidation->setShowInputMessage(true);
$typeValidation->setShowErrorMessage(true);
$typeValidation->setShowDropDown(true);
$typeValidation->setFormula1('"in,out,adjustment"');
$sheet->setDataValidation('C2:C1000', $typeValidation);

// Add data validation for reference_type column
$refTypeValidation = $sheet->getCell('D2')->getDataValidation();
$refTypeValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
$refTypeValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
$refTypeValidation->setAllowBlank(false);
$refTypeValidation->setShowInputMessage(true);
$refTypeValidation->setShowErrorMessage(true);
$refTypeValidation->setShowDropDown(true);
$refTypeValidation->setFormula1('"sale,purchase,adjustment"');
$sheet->setDataValidation('D2:D1000', $refTypeValidation);

// Add conditional formatting for quantity_change
$sheet->getStyle('B2:B1000')->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle('B2:B1000')->getFont()->setBold(true);

// Add conditional formatting for type column
$sheet->getStyle('C2:C1000')->getFont()->setBold(true);

// Set column widths
$sheet->getColumnDimension('A')->setWidth(15);  // product_id
$sheet->getColumnDimension('B')->setWidth(15);  // quantity_change
$sheet->getColumnDimension('C')->setWidth(15);  // type
$sheet->getColumnDimension('D')->setWidth(15);  // reference_type
$sheet->getColumnDimension('E')->setWidth(15);  // reference_id
$sheet->getColumnDimension('F')->setWidth(40);  // notes

// Add comments for guidance
$sheet->getComment('A1')->getText()->createTextRun('Enter the product ID from your inventory');
$sheet->getComment('B1')->getText()->createTextRun('Use positive numbers for stock in, negative for stock out');
$sheet->getComment('C1')->getText()->createTextRun('Select from dropdown: in, out, or adjustment');
$sheet->getComment('D1')->getText()->createTextRun('Select from dropdown: sale, purchase, or adjustment');
$sheet->getComment('E1')->getText()->createTextRun('Enter reference ID (e.g., sale ID, purchase ID)');
$sheet->getComment('F1')->getText()->createTextRun('Add any relevant notes about this movement');

// Save Movements Template
$writer = new Xlsx($spreadsheet);
$writer->save('templates/movements_template.xlsx');

echo "Excel templates created successfully!\n";
?> 