<?php

namespace Atldays\Sculptor;

abstract class Query implements Contracts\WithLimit, Contracts\WithModel, Contracts\WithQuery
{
    use Concerns\HasQuery;
}
