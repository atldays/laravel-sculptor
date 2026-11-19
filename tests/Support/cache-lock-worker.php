<?php

declare(strict_types=1);

use Atldays\QueryCache\Traits\QueryCacheable;
use Atldays\Sculptor\Attributes\{CacheFor, CacheLock, CacheScope};
use Atldays\Sculptor\{BuilderCachedQuery, CachedQuery};
use Atldays\Sculptor\Concerns\QueryResultAll;
use Atldays\Sculptor\Tests\Support\RedisCacheFixture;
use Illuminate\Cache\{CacheServiceProvider, Repository};
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\{Cache, DB, Facade};

require dirname(__DIR__, 2) . '/vendor/autoload.php';

[$script, $namespace, $role, $path, $strategy, $id] = $argv;
$id = (int)$id;
$fixture = new RedisCacheFixture($namespace);
$contended = false;
$store = $fixture->store(function (string $name, bool $acquired) use ($fixture, $role, &$contended): void {
    if (!$acquired && !$contended) {
        $contended = true;
        $fixture->client->rPush($fixture->namespace . ':waiting:' . $role, $name);
    }
});

$app = new Application(dirname(__DIR__, 2));
$app->instance('config', new Config([
    'database' => ['default' => 'testing', 'connections' => ['testing' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '']]],
    'cache' => ['default' => 'redis-test', 'stores' => ['redis-test' => ['driver' => 'observed-redis']]],
    'sculptor' => ['cache_store' => 'redis-test'],
]));
Facade::setFacadeApplication($app);
$app->register(EventServiceProvider::class);
$app->register(DatabaseServiceProvider::class);
$app->register(CacheServiceProvider::class);
$app->boot();
Cache::extend('observed-redis', fn () => new Repository($store));
DB::listen(function (QueryExecuted $event) use ($fixture, $role, $id): void {
    $table = str_contains($event->sql, 'from "posts"') ? 'posts' : 'authors';
    $fixture->client->incr($fixture->namespace . ':sql:' . $table . ':' . $id);

    if ($role === 'producer' && $table === 'posts') {
        $fixture->client->rPush($fixture->namespace . ':producer-in-sql', 'ready');

        if (!$fixture->client->blPop([$fixture->namespace . ':finish-production'], 12)) {
            throw new RuntimeException('Producer did not receive the test barrier signal.');
        }
    }
});

class ResultLockAuthor extends Model
{
    protected $table = 'authors';
}

class ResultLockPost extends Model
{
    protected $table = 'posts';

    public function author(): BelongsTo
    {
        return $this->belongsTo(ResultLockAuthor::class, 'author_id');
    }
}

class BuilderLockAuthor extends ResultLockAuthor
{
    use QueryCacheable;
}

class BuilderLockPost extends ResultLockPost
{
    use QueryCacheable;

    public function author(): BelongsTo
    {
        return $this->belongsTo(BuilderLockAuthor::class, 'author_id');
    }
}

#[CacheFor(600)]
#[CacheLock(seconds: 30, waitSeconds: 10)]
class ConcurrentResultQuery extends CachedQuery
{
    use QueryResultAll;

    protected string $model = ResultLockPost::class;

    public function __construct(#[CacheScope] private readonly int $postId)
    {
        $this->withRelations('author');
    }

    public function query(): Builder
    {
        return parent::query()->where('posts.id', $this->postId);
    }
}

#[CacheFor(600)]
#[CacheLock(seconds: 30, waitSeconds: 10)]
class ConcurrentBuilderQuery extends BuilderCachedQuery
{
    use QueryResultAll;

    protected string $model = BuilderLockPost::class;

    public function __construct(#[CacheScope] private readonly int $postId)
    {
        $this->withRelations('author');
    }

    public function query(): Builder
    {
        return parent::query()->where('posts.id', $this->postId);
    }
}

$query = $strategy === 'result' ? new ConcurrentResultQuery($id) : new ConcurrentBuilderQuery($id);
$post = $query->effect()->sole();
echo json_encode(['pid' => getmypid(), 'title' => $post->title, 'author' => $post->author->name], JSON_THROW_ON_ERROR);
