<?php

namespace Atldays\Sculptor;

abstract class BaseQuery implements Contracts\WithLimit, Contracts\WithModel, Contracts\WithQuery
{
    use Concerns\HasQuery;
}
