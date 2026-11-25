<?php

namespace App\Services\Reports;

use ZipArchive;

/**
 * Lớp XuatBaoCao - xuất báo cáo ra tệp CSV hoặc Excel (.xlsx) và gửi về trình duyệt.
 * Nếu máy chủ không hỗ trợ ZipArchive (hoặc tạo tệp xlsx thất bại) sẽ tự chuyển sang CSV.
 */
class XuatBaoCao
{
    /** Xuất một báo cáo theo định dạng 'csv' hoặc 'xlsx' (kết thúc request). */
    public function export(BaoCaoCoSo $report, string $format): void
    {
        $filename = $report->exportFilename();
        if ($format === 'csv') {
            $this->sendCsv($filename . '.csv', $report->exportRows());
        } else {
            $this->sendExcel($filename . '.xlsx', $report->exportRows());
        }
    }

    /** Gửi tệp CSV (UTF-8 BOM, chống CSV injection) rồi kết thúc request. */
    public function sendCsv(string $filename, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'w');
        if ($output === false) {
            error_log('CSV report export could not open output stream.');
            http_response_code(500);
            exit('Không thể tạo tệp CSV. Vui lòng thử lại.');
        }
        // Ghi BOM UTF-8 để Microsoft Excel hiển thị đúng tiếng Việt
        fwrite($output, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            $sanitizedRow = array_map(static function ($cell) {
                $val = (string) $cell;
                if (preg_match('/^[\s]*[=+\-@]/u', $val)) {
                    return "'" . $val;
                }

                return $val;
            }, array_values($row));
            fputcsv($output, $sanitizedRow);
        }
        fclose($output);
        exit;
    }

    /** Gửi tệp Excel .xlsx (tối giản, một trang tính) rồi kết thúc request. */
    public function sendExcel(string $filename, array $rows): void
    {
        $csvFilename = (string) preg_replace('/\.xlsx$/i', '.csv', $filename);
        if (!class_exists(ZipArchive::class)) {
            $this->sendCsv($csvFilename, $rows);

            return;
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'report_xlsx_');
        if ($temporaryFile === false) {
            $this->sendCsv($csvFilename, $rows);

            return;
        }

        $archive = new ZipArchive();
        if ($archive->open($temporaryFile, ZipArchive::OVERWRITE) !== true) {
            unlink($temporaryFile);
            $this->sendCsv($csvFilename, $rows);

            return;
        }
        foreach ($this->workbookFiles($rows) as $path => $content) {
            if (!$archive->addFromString($path, $content)) {
                $archive->close();
                unlink($temporaryFile);
                $this->sendCsv($csvFilename, $rows);

                return;
            }
        }
        if (!$archive->close()) {
            unlink($temporaryFile);
            $this->sendCsv($csvFilename, $rows);

            return;
        }

        $fileContents = file_get_contents($temporaryFile);
        if (!unlink($temporaryFile)) {
            error_log('Excel report export could not remove temporary file: ' . $temporaryFile);
        }
        if ($fileContents === false) {
            $this->sendCsv($csvFilename, $rows);

            return;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($fileContents));
        header('X-Content-Type-Options: nosniff');
        echo $fileContents;
        exit;
    }

    /** Các tệp XML cấu thành gói .xlsx. */
    private function workbookFiles(array $rows): array
    {
        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="Bao cao" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '</Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
                . $this->worksheetRows($rows) . '</sheetData></worksheet>',
        ];
    }

    /** Nội dung <sheetData>: số giữ kiểu số, chuỗi dùng inlineStr. */
    private function worksheetRows(array $rows): string
    {
        $worksheetRows = '';
        foreach (array_values($rows) as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $worksheetRows .= '<row r="' . $rowNumber . '">';
            foreach (array_values($row) as $columnIndex => $value) {
                $cellReference = self::columnName($columnIndex + 1) . $rowNumber;
                if (is_int($value) || is_float($value)) {
                    $numericValue = is_float($value)
                        ? rtrim(rtrim(sprintf('%.10F', $value), '0'), '.')
                        : (string) $value;
                    $worksheetRows .= '<c r="' . $cellReference . '"><v>' . $numericValue . '</v></c>';
                } else {
                    $cellValue = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $worksheetRows .= '<c r="' . $cellReference . '" t="inlineStr"><is><t xml:space="preserve">'
                        . $cellValue . '</t></is></c>';
                }
            }
            $worksheetRows .= '</row>';
        }

        return $worksheetRows;
    }

    /** Tên cột Excel từ số thứ tự (1 => A, 27 => AA). */
    private static function columnName(int $columnNumber): string
    {
        $columnName = '';
        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $columnName = chr(65 + $remainder) . $columnName;
            $columnNumber = intdiv($columnNumber - 1, 26);
        }

        return $columnName;
    }
}
