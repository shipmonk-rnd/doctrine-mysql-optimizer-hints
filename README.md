## MySQL optimizer hints for Doctrine

This library provides a simple way to incorporate [MySQL's optimizer hints](https://dev.mysql.com/doc/refman/8.0/en/optimizer-hints.html)
into SELECT queries written in [Doctrine Query Language](https://www.doctrine-project.org/projects/doctrine-orm/en/2.9/reference/dql-doctrine-query-language.html)
via [custom SqlWalker](https://www.doctrine-project.org/projects/doctrine-orm/en/2.9/cookbook/dql-custom-walkers.html#modify-the-output-walker-to-generate-vendor-specific-sql).
No need for native queries anymore.

### Installation:

```sh
composer require shipmonk/doctrine-mysql-optimizer-hints
```

### Example usage:

```php
$result = $em->createQueryBuilder()
    ->select('u.id')
    ->from(User::class, 'u')
    ->andWhere('u.id = 1')
    ->getQuery()
    ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class)
    ->setHint(OptimizerHintsHintHandler::class, ['SET_VAR(sql_mode=ONLY_FULL_GROUP_BY)'])
    ->getResult();
```

Which produces following SQL:

```mysql
SELECT /*+ SET_VAR(sql_mode=ONLY_FULL_GROUP_BY) */ u0_.id AS id_0
FROM user u0_
WHERE u0_.id = 1
```

Be careful what you place as optimizer hint, you are basically writing SQL there, but MySQL produces only warnings when a typo is made there.

### Use cases:

#### Limiting / extending max execution time for a single query:

Any reasonable application uses some global [max_execution_time](https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_max_execution_time) to avoid queries running for hours.
But you may want to break this limitation for a single long-running query.
Doing so by `SET max_execution_time = 10000;` is tricky as you should revert that to previous value just after the query ends.
This results in complex code around it, optimizer hint does that for you for free:

```php
->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class)
->setHint(OptimizerHintsHintHandler::class, ['MAX_EXECUTION_TIME(1000)'])
```

#### Query optimization:

Sometimes, [forcing some index usage](https://github.com/shipmonk-rnd/doctrine-mysql-index-hints) is not enough and you need to help MySQL optimizer to adjust the order of tables in execution plan.
[Join-order optimizer hints](https://dev.mysql.com/doc/refman/8.0/en/optimizer-hints.html#optimizer-hints-join-order) are the way to go.
Simpliest usage is to force the table order to be exactly as you wrote it is using `JOIN_FIXED_ORDER()`:

```php
->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class)
->setHint(OptimizerHintsHintHandler::class, ['JOIN_FIXED_ORDER()'])
```

#### Testing invisible index:

When dealing with complex query optimization on production, you can only guess if the new index you thought up will help or not.
Since MySQL 8.0, you can create [invisible index](https://dev.mysql.com/doc/refman/8.0/en/invisible-indexes.html) (those are maintained by the engine, but not used).
But you can enable invisible indexes for the query you want to test:

```php
->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class)
->setHint(OptimizerHintsHintHandler::class, ["SET_VAR(optimizer_switch = 'use_invisible_indexes=on')"])
```


### Combining with index hints:

Since 2.0.0, you can combine this library with [shipmonk/doctrine-mysql-index-hint](https://github.com/shipmonk-rnd/doctrine-mysql-index-hints):

```php
$result = $em->createQueryBuilder()
    ->select('u.id')
    ->from(User::class, 'u')
    ->andWhere('u.id = 1')
    ->getQuery()
    ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class)
    ->setHint(OptimizerHintsHintHandler::class, ['MAX_EXECUTION_TIME(1000)'])
    ->setHint(UseIndexHintHandler::class, [IndexHint::force(User::IDX_FOO, User::TABLE_NAME)])
    ->getResult();
```

### Supported PHP versions
- PHP 7.2 - 8.3
