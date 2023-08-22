<?php

namespace QUI\Cache\Cookie;

final class LoggedInCookie
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLifetimeInSeconds(): int
    {
        return 31536000; // One year in seconds
    }
}
