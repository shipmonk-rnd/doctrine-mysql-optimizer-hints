<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Query;
use LogicException;
use PHPUnit\Framework\TestCase;
use function sprintf;

class OptimizerHintsSqlWalkerTest extends TestCase
{

    /**
     * @param callable(Query): void $configureQueryCallback
     * @dataProvider walksProvider
     */
    public function testWalker(
        string $dql,
        callable $configureQueryCallback,
        ?string $expectedSql,
        ?string $expectedError = null
    ): void
    {
        if ($expectedError !== null) {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessageMatches($expectedError);
        }

        $entityManagerMock = $this->createEntityManagerMock();

        $query = new Query($entityManagerMock);
        $query->setDQL($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, OptimizerHintsSqlWalker::class);
        $configureQueryCallback($query);
        $producedSql = $query->getSQL();

        self::assertSame($expectedSql, $producedSql);
    }

    /**
     * @return iterable|mixed[][]
     */
    public static function walksProvider(): iterable
    {
        $selectDql = sprintf('SELECT w FROM %s w', DummyEntity::class);
        $selectDistinctDql = sprintf('SELECT DISTINCT w FROM %s w', DummyEntity::class);

        yield 'Max exec time' => [
            $selectDql,
            static function (Query $query): void {
                $query->setHint(OptimizerHintsSqlWalker::class, [OptimizerHint::maxExecutionTime(1000)]);
            },
            'SELECT /*+ MAX_EXECUTION_TIME(1000) */ d0_.id AS id_0 FROM dummy_entity d0_',
        ];
        yield 'Distinct' => [
            $selectDistinctDql,
            static function (Query $query): void {
                $query->setHint(OptimizerHintsSqlWalker::class, ['']);
            },
            'SELECT /*+  */ DISTINCT d0_.id AS id_0 FROM dummy_entity d0_',
        ];
        yield 'Escaping $' => [
            $selectDql,
            static function (Query $query): void {
                $query->setHint(OptimizerHintsSqlWalker::class, ['$0']);
            },
            'SELECT /*+ $0 */ d0_.id AS id_0 FROM dummy_entity d0_',
        ];
        yield 'Escaping \\' => [
            $selectDql,
            static function (Query $query): void {
                $query->setHint(OptimizerHintsSqlWalker::class, ['\0']);
            },
            'SELECT /*+ \0 */ d0_.id AS id_0 FROM dummy_entity d0_',
        ];
        yield 'No range optimization' => [
            $selectDql,
            static function (Query $query): void {
                $query->setHint(OptimizerHintsSqlWalker::class, ['NO_RANGE_OPTIMIZATION(my_table PRIMARY)']);
            },
            'SELECT /*+ NO_RANGE_OPTIMIZATION(my_table PRIMARY) */ d0_.id AS id_0 FROM dummy_entity d0_',
        ];
        yield 'Invalid value' => [
            $selectDql,
            static function (Query $query): void {
                $query->setHint(OptimizerHintsSqlWalker::class, 'BNL(t1)');
            },
            null,
            '~expecting array of strings, string given$~',
        ];
    }

    private function createEntityManagerMock(): EntityManager
    {
        $config = new Configuration();
        $config->setProxyNamespace('Tmp\Doctrine\Tests\Proxies');
        $config->setProxyDir('/tmp/doctrine');
        $config->setAutoGenerateProxyClasses(false);
        $config->setSecondLevelCacheEnabled(false);
        $config->setMetadataDriverImpl($config->newDefaultAnnotationDriver([], false));
        $config->setNamingStrategy(new UnderscoreNamingStrategy());

        $eventManager = $this->createMock(EventManager::class);
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->method('getEventManager')
            ->willReturn($eventManager);

        $connectionMock->method('getDatabasePlatform')
            ->willReturn(new MySQL80Platform());

        return EntityManager::create(
            $connectionMock,
            $config,
            $eventManager
        );
    }

}
