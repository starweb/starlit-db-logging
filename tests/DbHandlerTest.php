<?php declare(strict_types=1);

namespace Starlit\Db\Logging;

use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Starlit\Db\Db;

class DbHandlerTest extends TestCase
{
    /**
     * @var array
     */
    private static $testRecord = [
        'message' => 'The error message',
        'context' => [],
        'level' => Logger::ERROR,
        'level_name' => 'ERROR',
        'channel' => 'system',
        'extra' => [],
    ];

    /**
     * @var DbHandler
     */
    private $dbHandler;

    /**
     * @var MockObject
     */
    private $db;

    public function setUp()
    {
        $this->db = $this->createMock(Db::class);
        $this->dbHandler = new DbHandler($this->db, 10, ['user_id']);
    }

    public function testWrite(): void
    {
        $expectedDbData = [
            'channel' => 'system',
            'level'   => 'ERROR',
            'message' => 'The error message'
        ];

        $this->db->expects($this->once())
            ->method('insert')
            ->with('log', $expectedDbData);

        $this->dbHandler->handle(self::$testRecord);
    }

    public function testWriteWithContextAndExtra(): void
    {
        $testRecord = array_merge(self::$testRecord, [
            'context' => ['environment' => 'testing'],
            'extra' => ['user' => 'you', 'user_id' => 1],
        ]);

        $expectedDbData = [
            'channel' => 'system',
            'level'   => 'ERROR',
            'message' => 'The error message {"environment":"testing"} {"user":"you"}',
            'user_id' => 1,
        ];

        $this->db->expects($this->once())
            ->method('insert')
            ->with('log', $expectedDbData);

        $this->dbHandler->handle($testRecord);
    }

    public function testWriteWithClean(): void
    {
        $this->db
            ->method('fetchValue')
            ->willReturn(100);

        $this->db->expects($this->once())
            ->method('exec')
            ->with($this->stringStartsWith('DELETE'));


        $this->dbHandler->setCleanProbability(100);
        $this->dbHandler->handle(self::$testRecord);
    }

    public function testWriteWithNoChanceOfClean(): void
    {
        $this->db
            ->method('fetchValue')
            ->willReturn(100);

        $this->db->expects($this->never())
            ->method('exec')
            ->with($this->stringStartsWith('DELETE'));


        $this->dbHandler->setCleanDivisor(1000);
        $this->dbHandler->setCleanProbability(0);
        $this->dbHandler->handle(self::$testRecord);
    }

    public function testWriteNotTimeToCleanYet(): void
    {
        $this->db
            ->method('fetchValue')
            ->willReturn(5);

        $this->db->expects($this->never())
            ->method('exec')
            ->with($this->stringStartsWith('DELETE'));


        $this->dbHandler->setCleanProbability(100);
        $this->dbHandler->handle(self::$testRecord);
    }

    public function testClear(): void
    {
        $loggerMock = $this->createMock(Logger::class);
        $loggerMock
            ->expects($this->once())
            ->method('getName')
            ->willReturn('foo');

        $this->db->expects($this->once())
                 ->method('exec')
                 ->with('DELETE FROM `log` WHERE `channel` = ?', ['foo']);

        $this->dbHandler->clear($loggerMock);
    }
}
