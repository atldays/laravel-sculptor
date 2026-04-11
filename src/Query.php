<?php

namespace Atldays\Sculptor;

abstract class Query implements
    Contracts\WithQuery,
    Contracts\WithModel,
    Contracts\WithLimit
{
    use Concerns\HasQuery;
}
