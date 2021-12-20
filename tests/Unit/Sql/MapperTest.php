<?php

namespace Ekok\Cosiler\Test\Unit\Sql;

use Ekok\Cosiler\Sql\Mapper;
use PHPUnit\Framework\TestCase;
use Ekok\Cosiler\Sql\Connection;

class MapperTest extends TestCase
{
    /** @var Connection */
    private $db;

    /** @var Mapper */
    private $mapper;

    public function setUp(): void
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
        ));
        $this->mapper = new Mapper($this->db, 'demo', 'id', array(
            'id' => 'int',
        ));
    }

    public function testUsage()
    {
        $foo = array('id' => 1, 'name' => 'foo', 'hint' => null);

        $this->assertSame('demo', $this->mapper->table());
        $this->assertSame(0, $this->mapper->countRow());
        $this->assertFalse($this->mapper->findOne()->valid());
        $this->assertTrue($this->mapper->fromArray(array('name' => 'foo'))->save());
        $this->assertTrue($this->mapper->findOne()->valid());
        $this->assertFalse($this->mapper->invalid());
        $this->assertCount(1, $this->mapper);
        $this->assertTrue(isset($this->mapper['name']));
        $this->assertSame('foo', $this->mapper['name']);
        $this->assertSame($foo, $this->mapper->toArray());
        $this->assertTrue($this->mapper->dry());
        $this->assertFalse($this->mapper->dirty());
        $this->assertSame(json_encode(array($foo)), json_encode($this->mapper));

        // updating
        $this->mapper['name'] = 'update';

        $this->assertFalse($this->mapper->dry());
        $this->assertTrue($this->mapper->dirty());
        $this->assertTrue($this->mapper->save());

        // insert new item
        $this->mapper->reset();
        $this->mapper['name'] = 'bar';
        $this->mapper['hint'] = 'to be removed';
        unset($this->mapper['hint']);

        $bar = array('id' => 2, 'name' => 'bar', 'hint' => null);

        $this->assertTrue($this->mapper->save());
        $this->assertSame(array($bar), $this->mapper->all());

        // confirmation
        $this->assertCount(2, $this->mapper->findAll());
        $this->assertSame(2, $this->mapper->countRow());
        $this->assertSame($bar, $this->mapper->find(2)->toArray());

        // manual queries
        $this->assertSame(1, $this->mapper->insert(array('name' => 'baz')));
        $this->assertSame(1, $this->mapper->update(array('name' => 'update'), 'id = 3'));
        $this->assertSame(1, $this->mapper->delete('id = 3'));
        $this->assertSame(0, $this->mapper->delete('id = 3'));
        $this->assertSame(2, $this->mapper->insertBatch(array(
            array('name' => 'four'),
            array('name' => 'five'),
        )));

        $page1 = $this->mapper->paginate();
        $page2 = $this->mapper->simplePaginate();
        $rows = $this->mapper->select();
        $row = $this->mapper->selectOne();

        $this->assertCount(4, $page1['subset']);
        $this->assertCount(4, $page2['subset']);
        $this->assertCount(4, $rows);
        $this->assertSame(1, $row['id']);
    }
}
