<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Tests\Feature;

use Atldays\Sculptor\Cache\TransactionCacheGuard;
use Atldays\Sculptor\Tests\Fixtures\Models\AutomaticallyCachedPost;
use Atldays\Sculptor\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class TransactionCacheGuardTest extends TestCase
{
    public function test_guard_constructor_retains_the_builder_context_and_prepares_an_independent_copy(): void
    {
        DB::table('posts')->insert(['title' => 'Cached', 'published' => true]);
        $builder = AutomaticallyCachedPost::query();
        $this->assertSame('Cached', $builder->get()->sole()->title);
        $guard = new TransactionCacheGuard($builder);
        DB::beginTransaction();

        try {
            DB::table('posts')->update(['title' => 'Uncommitted']);
            $prepared = $guard->prepare();
            $this->assertNotSame($builder, $prepared);
            $this->assertFalse($builder->getQuery()->shouldAvoidCache());
            $this->assertSame('Uncommitted', $prepared->get()->sole()->title);
        } finally {
            DB::rollBack();
        }

        DB::table('posts')->update(['title' => 'Committed']);
        $this->assertSame('Cached', $guard->prepare()->get()->sole()->title);
        $this->assertSame('Cached', TransactionCacheGuard::make($builder)->prepare()->get()->sole()->title);
    }
}
