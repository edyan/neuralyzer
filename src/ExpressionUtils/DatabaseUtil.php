<?php

namespace Edyan\Neuralyzer\ExpressionUtils;

use Doctrine\DBAL\Connection;
use Edyan\Neuralyzer\Exception\NeuralizerException;

/**
 * Class DatabaseUtil
 */
class DatabaseUtil implements UtilsInterface
{
    /**
     * @var Connection
     */
    public $conn;

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'db';
    }

    public function getExtraArguments(): array
    {
        return ['conn'];
    }

    /**
     * @param string $sql
     *
     * @throws NeuralizerException
     */
    public function query(string $sql)
    {
        try {
            $this->conn->query($sql);
        } catch (\Exception $e) {
            throw new NeuralizerException($e->getMessage());
        }
    }
}
