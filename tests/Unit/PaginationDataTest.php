<?php

namespace Atldays\Sculptor\Tests\Unit;

use Atldays\Sculptor\Data\PaginationData;
use Atldays\Sculptor\Tests\TestCase;
use Webmozart\Assert\InvalidArgumentException;

class PaginationDataTest extends TestCase
{
    public function test_it_keeps_valid_pagination_values(): void
    {
        $pagination = new PaginationData(
            perPage: 25,
            page: 2,
            pageName: 'page',
            columns: ['*'],
        );

        $this->assertSame(25, $pagination->perPage);
        $this->assertSame(2, $pagination->page);
        $this->assertSame('page', $pagination->pageName);
        $this->assertSame(['*'], $pagination->columns);
    }

    public function test_it_rejects_invalid_per_page(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaginationData(
            perPage: 0,
            page: null,
            pageName: 'page',
            columns: ['*'],
        );
    }

    public function test_it_rejects_invalid_page(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PaginationData(
            perPage: null,
            page: 0,
            pageName: 'page',
            columns: ['*'],
        );
    }
}
