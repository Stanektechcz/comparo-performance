<?php

namespace App\Domain\Feeds\Fetching;

enum FeedAuthType: string
{
    case Basic = 'basic';
    case Bearer = 'bearer';
    case Header = 'header';
}
