<?php

declare(strict_types=1);

namespace Gingdev\Facebook;

use Facebook\FacebookRequest;
use Facebook\FacebookSession;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

class Facebook
{
    /**
     * @var FacebookSession|null
     */
    protected $session;

    /**
     * @throws \LogicException
     */
    public function __construct(string $name = 'default')
    {
        $cache = self::getCache();

        $session = $cache->getItem($name);

        if (!$session->isHit()) {
            throw new \LogicException(sprintf('Session "%s" does not exist.', $name));
        }

        $this->session = new FacebookSession($session->get());
    }

    public function request(
        string $method,
        string $path,
        array $parameters = []
    ): FacebookRequest {
        return new FacebookRequest(
            $this->session,
            $method,
            $path,
            $parameters,
            'v1.0'
        );
    }

    public static function getCache(): PhpFilesAdapter
    {
        return new PhpFilesAdapter('cache', 0, __DIR__);
    }
}
