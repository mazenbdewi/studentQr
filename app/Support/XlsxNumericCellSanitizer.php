<?php

namespace App\Support;

use DOMDocument;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class XlsxNumericCellSanitizer
{
    public function sanitizeToTemporaryFile(string $path): string
    {
        if (! is_file($path) || ! Str::endsWith(Str::lower($path), '.xlsx')) {
            return $path;
        }

        $temporaryPath = storage_path('app/import-sanitized/'.uniqid('manara-', true).'.xlsx');

        if (! is_dir(dirname($temporaryPath))) {
            mkdir(dirname($temporaryPath), 0775, true);
        }

        if (! copy($path, $temporaryPath)) {
            throw new RuntimeException('Unable to prepare uploaded Excel file for import.');
        }

        $zip = new ZipArchive();

        if ($zip->open($temporaryPath) !== true) {
            return $temporaryPath;
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);

                if (! is_string($name) || ! preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                    continue;
                }

                $contents = $zip->getFromName($name);

                if (! is_string($contents) || $contents === '') {
                    continue;
                }

                $repaired = $this->repairWorksheetXml($contents);

                if ($repaired !== $contents) {
                    $zip->addFromString($name, $repaired);
                }
            }
        } finally {
            $zip->close();
        }

        return $temporaryPath;
    }

    public function deleteTemporaryFile(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $directory = storage_path('app/import-sanitized');
        $realPath = realpath($path);
        $realDirectory = realpath($directory);

        if ($realPath && $realDirectory && str_starts_with($realPath, $realDirectory) && is_file($realPath)) {
            @unlink($realPath);
        }
    }

    public function repairWorksheetXml(string $contents): string
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument();
            $document->preserveWhiteSpace = false;
            $document->formatOutput = false;

            if (! $document->loadXML($contents)) {
                return $contents;
            }

            $changed = false;

            foreach ($document->getElementsByTagName('c') as $cell) {
                if ($cell->getAttribute('t') !== 'n') {
                    continue;
                }

                $value = $cell->getElementsByTagName('v')->item(0)?->textContent;

                if ($value === null || trim($value) === '' || is_numeric(trim($value))) {
                    continue;
                }

                $cell->setAttribute('t', 'str');
                $changed = true;
            }

            return $changed ? ($document->saveXML() ?: $contents) : $contents;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
