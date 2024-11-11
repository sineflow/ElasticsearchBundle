<?php

namespace Sineflow\ElasticsearchBundle\Tests\App\Fixture\Acme\FooBundle\Enum;

enum CustomerTypeEnum: int
{
    case INDIVIDUAL = 1;
    case COMPANY = 2;
}
