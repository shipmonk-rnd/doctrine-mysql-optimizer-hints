<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

class OptimizerHint
{

    public static function maxExecutionTime(int $milliseconds): string
    {
        return "MAX_EXECUTION_TIME($milliseconds)";
    }

    public static function setVar(string $name, string $value): string
    {
        return "SET_VAR($name = $value)";
    }

}
