<?php

declare(strict_types=1);

namespace Edyan\Neuralyzer\Service;

use Edyan\Neuralyzer\Exception\NeuralyzerException;
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
     */
    public function __construct(DBUtils $dbUtils)
    {
        $this->dbUtils = $dbUtils;
    }

    /**
     * Returns service's name
     */
    public function getName(): string
    {
        return 'db';
    }

    /**
     * @throws NeuralyzerException
     *
     * @return array
     */
    public function query(string $sql): ?array
    {
        $conn = $this->dbUtils->getConn();
        $sql = trim($sql);
        try {
            $res = $conn->query($sql);
            if (strpos($sql, 'SELECT') === 0) {
                return $res->fetchAll();
            }

            return null;
        } catch (\Exception $e) {
            throw new NeuralyzerException($e->getMessage());
        }
    }
}
