<?php

namespace app\common\library;

/**
 * 雪花算法 (Snowflake)
 */
class Snowflake
{
    // 起始时间戳 (2024-01-01)
    const EPOCH = 1704067200000;

    // 各部分占用的位数
    const SEQUENCE_BITS = 12; // 序列号占用的位数
    const MACHINE_BITS  = 5;  // 机器标识占用的位数
    const DATA_BITS     = 5;  // 数据中心占用的位数

    // 各部分的最大值
    const MAX_MACHINE_ID = -1 ^ (-1 << self::MACHINE_BITS);
    const MAX_DATA_ID    = -1 ^ (-1 << self::DATA_BITS);
    const MAX_SEQUENCE   = -1 ^ (-1 << self::SEQUENCE_BITS);

    // 各部分偏移量
    const MACHINE_ID_SHIFT = self::SEQUENCE_BITS;
    const DATA_ID_SHIFT    = self::SEQUENCE_BITS + self::MACHINE_BITS;
    const TIMESTAMP_SHIFT  = self::SEQUENCE_BITS + self::MACHINE_BITS + self::DATA_BITS;

    protected $dataCenterId;
    protected $machineId;
    protected $sequence = 0;
    protected $lastTimestamp = -1;

    public function __construct($dataCenterId = 1, $machineId = 1)
    {
        if ($dataCenterId > self::MAX_DATA_ID || $dataCenterId < 0) {
            throw new \Exception("Data center ID can't be greater than " . self::MAX_DATA_ID . " or less than 0");
        }
        if ($machineId > self::MAX_MACHINE_ID || $machineId < 0) {
            throw new \Exception("Machine ID can't be greater than " . self::MAX_MACHINE_ID . " or less than 0");
        }
        $this->dataCenterId = $dataCenterId;
        $this->machineId = $machineId;
    }

    public function nextId()
    {
        $timestamp = $this->timeGen();

        if ($timestamp < $this->lastTimestamp) {
            throw new \Exception("Clock moved backwards. Refusing to generate id for " . ($this->lastTimestamp - $timestamp) . " milliseconds");
        }

        if ($this->lastTimestamp == $timestamp) {
            $this->sequence = ($this->sequence + 1) & self::MAX_SEQUENCE;
            if ($this->sequence == 0) {
                $timestamp = $this->tilNextMillis($this->lastTimestamp);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastTimestamp = $timestamp;

        $nextId = (($timestamp - self::EPOCH) << self::TIMESTAMP_SHIFT)
            | ($this->dataCenterId << self::DATA_ID_SHIFT)
            | ($this->machineId << self::MACHINE_ID_SHIFT)
            | $this->sequence;

        return (string)$nextId;
    }

    protected function tilNextMillis($lastTimestamp)
    {
        $timestamp = $this->timeGen();
        while ($timestamp <= $lastTimestamp) {
            $timestamp = $this->timeGen();
        }
        return $timestamp;
    }

    protected function timeGen()
    {
        return (int)(microtime(true) * 1000);
    }
}
