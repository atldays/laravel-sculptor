<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Data\CursorPaginationData;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Pagination\Cursor;
use PHPUnit\Framework\Attributes\DataProvider;
use Webmozart\Assert\InvalidArgumentException;

class CursorPaginationDataTest extends TestCase
{
    public function test_it_preserves_configuration_and_encodes_cursor_context(): void
    {
        $cursor = new Cursor(['id' => 3], false);
        $data = new CursorPaginationData(2, $cursor, 'posts_cursor', [4 => 'id']);
        $this->assertSame($cursor, $data->cursor);
        $this->assertSame([
            'per_page' => 2,
            'cursor' => $cursor->encode(),
            'cursor_name' => 'posts_cursor',
            'columns' => ['id'],
        ], $data->toArray());
        $this->assertSame($data->toArray(), (new CursorPaginationData(2, $cursor->encode(), 'posts_cursor', ['id']))->toArray());
    }

    public static function invalidSettings(): iterable
    {
        yield 'zero size' => [0, 'cursor', ['*']];
        yield 'negative size' => [-1, 'cursor', ['*']];
        yield 'empty name' => [2, '', ['*']];
        yield 'empty columns' => [2, 'cursor', []];
    }

    #[DataProvider('invalidSettings')]
    public function test_it_rejects_invalid_settings(int $perPage, string $name, array $columns): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CursorPaginationData($perPage, null, $name, $columns);
    }
}
