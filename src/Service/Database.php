<?php

namespace Edyan\Neuralyzer\Service;

use Doctrine\DBAL\Connection;
use Edyan\Neuralyzer\Anonymizer\DB;
use Edyan\Neuralyzer\Exception\NeuralizerException;

/**
 * Class Database to inject in expression language
 */
class Database implements ServiceInterface
{
    /**
     * @var Db
     */
    public $db;

    /**
     * Used for auto wiring
     *
     * @param DB $db
     */
    public function __construct(DB $db)
    {
        $this->db = $db;
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
        try {
            $conn = $this->db->getConn();
            $conn->query($sql);
        } catch (\Exception $e) {
            throw new NeuralizerException($e->getMessage());
        }
    }
}
