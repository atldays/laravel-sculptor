<?php

declare(strict_types=1);

namespace Atldays\Sculptor\Contracts;

/**
 * Bypass cache reads and writes while the query's connection is in a transaction.
 */
interface WithoutCacheInTransactions extends WithCache {}
