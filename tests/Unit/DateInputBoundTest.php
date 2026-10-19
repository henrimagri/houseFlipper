<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DateInputBoundTest extends TestCase
{
    #[Test]
    public function every_http_date_validation_rule_has_an_input_length_bound(): void
    {
        $files = [];
        $requestDirectory = dirname(__DIR__, 2).'/app/Http/Requests';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($requestDirectory)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        $files[] = dirname(__DIR__, 2).'/app/Http/Middleware/ValidatePagination.php';
        $files[] = dirname(__DIR__, 2).'/app/Http/Controllers/Api/EventoCalendarioController.php';

        $unbounded = [];
        foreach ($files as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
                if (preg_match("/['\"]date['\"]/", $line) && ! str_contains($line, 'max:40')) {
                    $unbounded[] = basename($file).':'.($number + 1);
                }
            }
        }

        self::assertSame([], $unbounded, 'Unbounded HTTP date rules: '.implode(', ', $unbounded));
    }
}
