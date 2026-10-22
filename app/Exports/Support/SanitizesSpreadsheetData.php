<?php

namespace App\Exports\Support;

trait SanitizesSpreadsheetData
{
    /**
     * Prevent spreadsheet formula injection for user-controlled text. Values
     * beginning with =, +, -, @, tab or carriage return are prefixed with an
     * apostrophe so Excel treats them as literal text.
     */
    protected function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
