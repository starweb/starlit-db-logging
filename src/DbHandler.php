<?php declare(strict_types=1);
/**
 * Starlit Db Logging.
 *
 * @copyright Copyright (c) 2019 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Starlit\Db\Db;

/**
 * Monolog handler to log to a database table.
 *
 * Use a table structure like this for compatibility:
 *
 * CREATE TABLE `log` (
 *   `log_entry_id` BIGINT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
 *   `time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   `channel` VARCHAR(64) NOT NULL,
 *   `level` VARCHAR(10) NOT NULL,
 *   `message` TEXT NOT NULL,
 *   PRIMARY KEY (`log_entry_id`),
 *   KEY `time` (`time`),
 *   KEY `channel` (`channel`),
 *   KEY `level` (`level`)
 * );
 *
 * @author Andreas Nilsson <http://github.com/jandreasn>
 */
class DbHandler extends AbstractProcessingHandler
{
    /**
     * @var Db
     */
    protected $db;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var string[]
     */
    protected $additionalFields;

    /**
     * @var int
     */
    protected $maxEntries;

    /**
     * @var int
     */
    protected $cleanDivisor = 100;

    /**
     * Default probability of table being cleaned (1/100 = 1%).
     *
     * @var int
     */
    protected $cleanProbability = 1;

    /**
     * @param Db        $db
     * @param int       $maxEntries
     * @param string[]  $additionalFields
     * @param int       $level
     * @param bool      $bubble
     * @param string    $table
     */
    public function __construct(
        Db $db,
        int $maxEntries = null,
        array $additionalFields = [],
        int $level = Logger::DEBUG,
        bool $bubble = true,
        string $table = 'log'
    ) {
        parent::__construct($level, $bubble);

        $this->db = $db;
        $this->maxEntries = $maxEntries;
        $this->additionalFields = $additionalFields;
        $this->table = $table;
    }

    /**
     * {@inheritdoc}
     */
    protected function processRecord(array $record): array
    {
        $record = parent::processRecord($record);

        $record['additionalFieldsData'] = [];
        foreach ($this->additionalFields as $field) {
            foreach (['context', 'extra'] as $sourceKey) {
                if (isset($record[$sourceKey]) && array_key_exists($field, $record[$sourceKey])) {
                    $record['additionalFieldsData'][$field] = $record[$sourceKey][$field];
                    unset($record[$sourceKey][$field]);
                }
            }
        }

        return $record;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record): void
    {
        $dbData = [
            'channel' => $record['channel'],
            'level'   => $record['level_name'],
            'message' => $record['formatted'],
        ] + $record['additionalFieldsData'];

        $this->db->insert($this->table, $dbData);

        $this->clean($record['channel']);
    }

    protected function clean(string $channel): void
    {
        if ($this->maxEntries && mt_rand(1, $this->cleanDivisor) <= $this->cleanProbability) {
            $currentCount = $this->getChannelEntriesCount($channel);
            if ($currentCount > $this->maxEntries) {
                $entriesToDelete = $currentCount - $this->maxEntries;
                $this->deleteXOldestChannelEntries($channel, $entriesToDelete);
            }
        }
    }

    protected function getChannelEntriesCount(string $channel): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM `' . $this->table . '` WHERE `channel` = ?',
            [$channel]
        );
    }

    protected function deleteXOldestChannelEntries(string $channel, int $entriesToDelete): void
    {
        $this->db->exec(
            sprintf(
                'DELETE FROM `%s` WHERE `channel` = ?  ORDER BY `time` ASC LIMIT %d',
                $this->table,
                $entriesToDelete
            ),
            [$channel]
        );
    }

    public function clear(Logger $logger): void
    {
        $this->db->exec(sprintf('DELETE FROM `%s` WHERE `channel` = ?', $this->table), [$logger->getName()]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultFormatter(): DbFormatter
    {
        return new DbFormatter();
    }

    /**
     * @see setCleanProbability()
     */
    public function setCleanDivisor(int $cleanDivisor): void
    {
        $this->cleanDivisor = $cleanDivisor;
    }

    /**
     * Sets the probability that log table is cleaned on log write.
     *
     * The clean probability together with clean divisor is used to calculate the probability.
     * With a clean probability of 1 and a divisor of 100, there's a 1% chance (1/100) the table
     * will be cleaned.
     */
    public function setCleanProbability(int $cleanProbability): void
    {
        $this->cleanProbability = $cleanProbability;
    }
}
