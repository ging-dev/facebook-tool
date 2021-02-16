<?php

declare(strict_types=1);

namespace Gingdev\Facebook;

use Facebook\FacebookRequest;
use Facebook\FacebookSession;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

class Facebook
{
    protected $session;

    public function __construct(string $name = 'default')
    {
        $cache = self::getCache();

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

    public static function getCache()
    {
        return new PhpFilesAdapter('cache', 0, __DIR__);
    }
}
