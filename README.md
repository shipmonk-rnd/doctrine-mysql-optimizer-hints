## MySQL optimizer hints for Doctrine

This library provides a simple way to incorporate [MySQL's optimizer hints](https://dev.mysql.com/doc/refman/8.0/en/optimizer-hints.html)
into SELECT queries written in [Doctrine Query Language](https://www.doctrine-project.org/projects/doctrine-orm/en/2.9/reference/dql-doctrine-query-language.html)
via [custom SqlWalker](https://www.doctrine-project.org/projects/doctrine-orm/en/2.9/cookbook/dql-custom-walkers.html#modify-the-output-walker-to-generate-vendor-specific-sql).
No need for native queries anymore.

### Installation:

```sh
composer require shipmonk/doctrine-mysql-optimizer-hints
```

### Simple usage:

```php
$result = $em->createQueryBuilder()
    ->select('u.id')
    ->from(User::class, 'u')
    ->andWhere('u.id = 1')
    ->getQuery()
    ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class)
    ->setHint(OptimizerHintsHintHandler::class, ['MAX_EXECUTION_TIME(1000)'])
    ->getResult();
```

Which produces following SQL:

```mysql
SELECT /*+ MAX_EXECUTION_TIME(1000) */ u0_.id AS id_0
FROM user u0_
WHERE u0_.id = 1
```

This example of setting [max_execution_time](https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_max_execution_time) for a single query is super handy.
Typically, you have some default global settings you want to overwrite only temporarily.
Doing so by `SET max_execution_time = 10000;` is tricky as you should revert that to previous value just after the query ends.
This results in complex code around it, optimizer hint does that for you for free.

Be careful what you place as optimizer hint, you are basically writing SQL there, but MySQL produces only warnings when a typo is made there.

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
