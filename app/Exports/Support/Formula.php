<?php

namespace App\Exports\Support;

/**
 * Marks a cell that should be written as an Excel formula. User-controlled
 * strings never use this class and are sanitised before writing.
 */
final class Formula
{
    public function __construct(public readonly string $value) {}
}
