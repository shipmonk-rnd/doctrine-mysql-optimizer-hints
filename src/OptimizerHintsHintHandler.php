<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use LogicException;
use ShipMonk\Doctrine\Walker\HintHandler;
use ShipMonk\Doctrine\Walker\SqlNode;
use function addcslashes;
use function gettype;
use function implode;
use function is_array;
use function is_object;
use function is_string;
use function preg_last_error_msg;
use function preg_replace;

class OptimizerHintsHintHandler extends HintHandler
{

    /**
     * @return list<SqlNode::*>
     */
    public function getNodes(): array
    {
        return [SqlNode::SelectClause];
    }

    public function processNode(
        SqlNode $sqlNode,
        string $sql,
    ): string
    {
        $selfClass = static::class;

        $optimizerHints = $this->getHintValue();

        if (!is_array($optimizerHints)) {
            $type = is_object($optimizerHints) ? $optimizerHints::class : gettype($optimizerHints);
            throw new LogicException("Unexpected value in ->setHint({$selfClass}::class, ...), expecting array of strings, {$type} given");
        }

        foreach ($optimizerHints as $index => $optimizerHint) {
            if (!is_string($optimizerHint)) {
                $type = is_object($optimizerHint) ? $optimizerHint::class : gettype($optimizerHint);
                throw new LogicException("Unexpected value in ->setHint({$selfClass}::class, ...), expecting array of strings, {$type} given at index {$index}");
            }
        }

        $optimizerHintsString = implode(' ', $optimizerHints);
        $optimizerHintsSql = addcslashes($optimizerHintsString, '$\\');
        $sqlWithOptimizerHints = preg_replace('~^SELECT ~', "SELECT /*+ $optimizerHintsSql */ ", $sql);

        if ($sqlWithOptimizerHints === null) {
            throw new LogicException('Regex replace failure: ' . preg_last_error_msg());
        }

        return $sqlWithOptimizerHints;
    }

}
