<?php declare(strict_types=1);

namespace Starlit\Db\Logging;

use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class DbFormatterTest extends TestCase
{
    public function testFormat(): void
    {
        $formatter = new DbFormatter();

        $record = [
            'message' => 'The error message',
            'context' => [],
            'level' => Logger::ERROR,
            'level_name' => Logger::getLevelName(Logger::ERROR),
            'channel' => 'system',
            'extra' => [],
        ];

        $this->assertEquals('The error message', $formatter->format($record));
    }

    public function testFormatExceedMaxLength(): void
    {
        $formatter = new DbFormatter(null, true, true, 5);

        $record = [
            'message' => 'The error message',
            'context' => [],
            'level' => Logger::ERROR,
            'level_name' => Logger::getLevelName(Logger::ERROR),
            'channel' => 'system',
            'extra' => [],
        ];

        $this->assertEquals('Th...', $formatter->format($record));
    }
}
