<?php

declare(strict_types=1);

namespace Gingdev\Facebook;

use Facebook\FacebookRequest;
use Facebook\FacebookSession;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

class Facebook
{
    const CACHE_DIR = __DIR__.'/../cache';

    protected $session;

    public function __construct(string $name = 'default')
    {
        $cache = new PhpFilesAdapter('', 0, self::CACHE_DIR);

        $session = $cache->getItem($name);

        if (!$session->isHit()) {
            throw new \LogicException(sprintf('Session "%s" does not exist.', $name));
        }

        $this->session = new FacebookSession($session->get());
    }

    public function request(string $method, string $path, array $parameters = [])
    {
        return new FacebookRequest(
            $this->session,
            $method,
            $path,
            $parameters
        );
    }
}
