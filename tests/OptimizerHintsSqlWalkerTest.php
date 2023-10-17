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
     * @param mixed $hint
     * @dataProvider walksProvider
     */
    public function testWalker(
        string $dql,
        $hint,
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
        $query->setHint(OptimizerHintsSqlWalker::class, $hint);
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
            [OptimizerHint::maxExecutionTime(1000)],
            'SELECT /*+ MAX_EXECUTION_TIME(1000) */ d0_.id AS id_0 FROM dummy_entity d0_',
        ];
        yield 'Distinct' => [
            $selectDistinctDql,
            [''],
            'SELECT /*+  */ DISTINCT d0_.id AS id_0 FROM dummy_entity d0_',
        ];
        yield 'Escaping $' => [
            $selectDql,
            ['$0'],
            'SELECT /*+ $0 */ d0_.id AS id_0 FROM dummy_entity d0_',
        ];
        yield 'Escaping \\' => [
            $selectDql,
            ['\0'],
            'SELECT /*+ \0 */ d0_.id AS id_0 FROM dummy_entity d0_',
        ];
        yield 'Multiple hints' => [
            $selectDql,
            ['MAX_EXECUTION_TIME(1000)', 'NO_RANGE_OPTIMIZATION(my_table PRIMARY)'],
            'SELECT /*+ MAX_EXECUTION_TIME(1000) NO_RANGE_OPTIMIZATION(my_table PRIMARY) */ d0_.id AS id_0 FROM dummy_entity d0_',
        ];
        yield 'Invalid value' => [
            $selectDql,
            'BNL(t1)',
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
