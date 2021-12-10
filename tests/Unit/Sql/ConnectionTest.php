<?php

namespace Ekok\Cosiler\Test\Unit\Sql;

use Ekok\Cosiler\Sql\Builder;
use Ekok\Cosiler\Sql\Connection;
use PHPUnit\Framework\TestCase;

class ConnectionTest extends TestCase
{
    /** @var Connection */
    private $db;

    protected function setUp(): void
    {
        $this->db = new Connection('sqlite::memory:', null, null, array(
            'scripts' => array(
                <<<'SQL'
CREATE TABLE "demo" (
    "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "name" VARCHAR(64) NOT NULL,
    "hint" VARCHAR(255) NULL
)
SQL
            ),
        ), new Builder('sqlite'));
    }

    public function testDb()
    {
        $this->assertFalse($this->db->exists('unknown'));
        $this->assertTrue($this->db->exists('demo'));
        $this->assertNull($this->db->getName());
        $this->assertNotNull($this->db->getVersion());
        $this->assertEquals('sqlite', $this->db->getDriver());
        $this->assertInstanceOf(Builder::class, $this->db->getBuilder());
    }

    public function testDbManipulation()
    {
        $this->assertCount(0, $this->db->select('demo'));
        $this->assertEquals(0, $this->db->count('demo'));
        $this->assertNull($this->db->selectOne('demo'));

        $this->assertEquals(1, $this->db->insert('demo', array('name' => 'foo', 'hint' => 'bar')));
        $this->assertEquals(2, $this->db->insertBatch('demo', array(
            array('name' => 'qux'),
            array('name' => 'quux'),
        )));
        $this->assertEquals(array('id' => 4, 'name' => 'load1', 'hint' => null), $this->db->insert('demo', array('name' => 'load1'), 'id'));
        $this->assertEquals(array('id' => 5, 'name' => 'load2', 'hint' => null), $this->db->insert('demo', array('name' => 'load2'), array('load' => 'id')));
        $this->assertEquals(array(
            array('id' => 6, 'name' => 'batch1', 'hint' => null),
            array('id' => 7, 'name' => 'batch2', 'hint' => null),
        ), $this->db->insertBatch('demo', array(
            array('name' => 'batch1'),
            array('name' => 'batch2'),
        ), 'id > 5'));

        $this->assertCount(7, $this->db->select('demo'));
        $this->assertEquals(7, $this->db->count('demo'));
        $this->assertEquals(array('id' => 1, 'name' => 'foo', 'hint' => 'bar'), $this->db->selectOne('demo'));

        $this->assertEquals(1, $this->db->update('demo', array('name' => 'qux update'), array('name = ?', 'qux')));
        $this->assertEquals(array('id' => 2, 'name' => 'qux update2', 'hint' => null), $this->db->update('demo', array('name' => 'qux update2'), 'id = 2', true));

        $this->assertEquals(4, $this->db->delete('demo', 'id > 3'));
        $this->assertEquals(3, $this->db->count('demo'));
    }

    public function testClone()
    {
        $this->expectExceptionMessage('Cloning Connection is prohibited');

        clone $this->db;
    }

    public function testUnknownProperty()
    {
        $this->expectExceptionMessage('Undefined property: foo');

        $this->db->foo;
    }

    public function testErrorPdo()
    {
        $this->expectExceptionMessage('Unable to connect database');

        $db = new Connection('sqlite::memory:', null, null, array(
            'scripts' => array(
                <<<'SQL'
CREATE TABLE "demo" (
    "id" INTEGER NOT NULL AUTOINCREMENT,
    "name" VARCHAR(64) NOT NULL,
    "hint" VARCHAR(255) NULL
)
SQL
            ),
        ));
        $db->getPdo();
    }

    public function testInvalidQuery()
    {
        $this->expectExceptionMessage('Unable to prepare query');

        // failure query
        $this->db->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
        $this->assertFalse($this->db->insert('demo', array('foo' => 'bar')));
    }

    public function testTransaction()
    {
        $actual = $this->db->transact(static function (Connection $db) {
            return $db->exec("insert into demo (name) values ('foo')");
        });

        $this->assertEquals(1, $actual);
    }

    /** @dataProvider simplePaginateProvider */
    public function testSimplePaginate($expected, ...$arguments)
    {
        $rowCount = $this->db->insertBatch('demo', array(
            array('name' => 'row1'),
            array('name' => 'row2'),
            array('name' => 'row3'),
            array('name' => 'row4'),
            array('name' => 'row5'),
        ));

        $this->assertEquals(5, $rowCount);

        $actual = $this->db->simplePaginate('demo', ...$arguments);
        $subset = $actual['subset'];
        unset($actual['subset']);

        $this->assertEquals($expected, $actual);
        $this->assertCount($expected['count'], $subset);
    }

    public function simplePaginateProvider()
    {
        return array(
            'default' => array(
                array('count' => 5, 'current_page' => 1, 'next_page' => 2, 'prev_page' => 0, 'per_page' => 20),
            ),
            'filtering' => array(
                array('count' => 1, 'current_page' => 1, 'next_page' => 2, 'prev_page' => 0, 'per_page' => 20),
                1,
                "name = 'row1'",
            ),
            'page 2' => array(
                array('count' => 0, 'current_page' => 2, 'next_page' => 3, 'prev_page' => 1, 'per_page' => 20),
                2,
            ),
            'perpage = 2' => array(
                array('count' => 2, 'current_page' => 1, 'next_page' => 2, 'prev_page' => 0, 'per_page' => 2),
                1,
                null,
                array('limit' => 2),
            ),
            'perpage = 2, page 3' => array(
                array('count' => 1, 'current_page' => 3, 'next_page' => 4, 'prev_page' => 2, 'per_page' => 2),
                3,
                null,
                array('limit' => 2),
            ),
        );
    }

    /** @dataProvider paginateProvider */
    public function testPaginate($expected, ...$arguments)
    {
        $rowCount = $this->db->insertBatch('demo', array(
            array('name' => 'row1'),
            array('name' => 'row2'),
            array('name' => 'row3'),
            array('name' => 'row4'),
            array('name' => 'row5'),
        ));

        $this->assertEquals(5, $rowCount);

        $actual = $this->db->paginate('demo', ...$arguments);
        $subset = $actual['subset'];
        unset($actual['subset']);

        $this->assertEquals($expected, $actual);
        $this->assertCount($expected['count'], $subset);
    }

    public function paginateProvider()
    {
        return array(
            'default' => array(
                array('count' => 5, 'current_page' => 1, 'next_page' => 1, 'prev_page' => 0, 'last_page' => 1, 'total' => 5, 'first' => 1, 'last' => 5, 'per_page' => 20),
            ),
            'filtering' => array(
                array('count' => 1, 'current_page' => 1, 'next_page' => 1, 'prev_page' => 0, 'last_page' => 1, 'total' => 1, 'first' => 1, 'last' => 1, 'per_page' => 20),
                1,
                "name = 'row1'",
            ),
            'page 2' => array(
                array('count' => 0, 'current_page' => 2, 'next_page' => 1, 'prev_page' => 1, 'last_page' => 1, 'total' => 5, 'first' => 21, 'last' => 21, 'per_page' => 20),
                2,
            ),
            'perpage = 2' => array(
                array('count' => 2, 'current_page' => 1, 'next_page' => 2, 'prev_page' => 0, 'last_page' => 3, 'total' => 5, 'first' => 1, 'last' => 2, 'per_page' => 2),
                1,
                null,
                array('limit' => 2),
            ),
            'perpage = 2, page 3' => array(
                array('count' => 1, 'current_page' => 3, 'next_page' => 3, 'prev_page' => 2, 'last_page' => 3, 'total' => 5, 'first' => 5, 'last' => 5, 'per_page' => 2),
                3,
                null,
                array('limit' => 2),
            ),
        );
    }
}
