<?php

namespace Edyan\Neuralyzer\Service;

use Edyan\Neuralyzer\Exception\NeuralizerException;
use Edyan\Neuralyzer\Utils\DBUtils;

/**
 * Class Database to inject in expression language
 */
class Database implements ServiceInterface
{
    /**
     * @var DBUtils
     */
    private $dbUtils;

    /**
     * Used for auto wiring
     *
     * @param DBUtils $dbUtils
     */
    public function __construct(DBUtils $dbUtils)
    {
        $this->dbUtils = $dbUtils;
    }

    /**
     * Returns service's name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'db';
    }

    /**
     * @param string $sql
     *
     * @throws NeuralizerException
     */
    public function query(string $sql)
    {
        $conn = $this->dbUtils->getConn();
        try {
            return $conn->query($sql)->fetchAll();
        } catch (\Exception $e) {
            throw new NeuralizerException($e->getMessage());
        }
    }
}
