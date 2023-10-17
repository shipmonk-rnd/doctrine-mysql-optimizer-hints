<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\ORM\Query\AST\SelectClause;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\SqlWalker;
use LogicException;
use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_object;
use function is_string;
use function preg_last_error_msg;
use function preg_replace;
use function strpos;
use function strtolower;

class OptimizerHintsSqlWalker extends SqlWalker
{

    /**
     * @param SelectClause $selectClause
     */
    public function walkSelectClause($selectClause): string
    {
        $selfClass = static::class;
        $query = $this->getQuery();
        $platform = $query->getEntityManager()->getConnection()->getDatabasePlatform();
        $normalizedPlatformName = strtolower(get_class($platform)); // bypass renames in DBAL

        $sql = parent::walkSelectClause($selectClause);

        if (strpos($normalizedPlatformName, 'mysql') === false) {
            throw new LogicException("Only MySQL platform is supported, {$normalizedPlatformName} given");
        }

        if (!$query->hasHint(self::class)) {
            throw new LogicException("{$selfClass} was used, but no limit in milliseconds was added. Use e.g. ->setHint({$selfClass}::class, 5_000)");
        }

        if (!$query->getAST() instanceof SelectStatement) {
            throw new LogicException("Only SELECT queries are currently supported by {$selfClass}");
        }

        $optimizerHints = $query->getHint(self::class);

        if (!is_array($optimizerHints)) {
            $type = is_object($optimizerHints) ? get_class($optimizerHints) : gettype($optimizerHints);
            throw new LogicException("Unexpected value in ->setHint({$selfClass}::class, ...), expecting array of strings, {$type} given");
        }

        foreach ($optimizerHints as $index => $optimizerHint) {
            if (!is_string($optimizerHint)) {
                $type = is_object($optimizerHint) ? get_class($optimizerHint) : gettype($optimizerHint);
                throw new LogicException("Unexpected value in ->setHint({$selfClass}::class, ...), expecting array of strings, {$type} given at index {$index}");
            }
        }

        $optimizerHintsSql = implode(' ', $optimizerHints);
        $sqlWithOptimizerHints = preg_replace('~^SELECT (.*?)~', "SELECT /*+ $optimizerHintsSql */ \\1 ", $sql);

        if ($sqlWithOptimizerHints === null) {
            throw new LogicException('Regex replace failure: ' . preg_last_error_msg());
        }

        return $sqlWithOptimizerHints;
    }

}
