<?php

declare(strict_types=1);

namespace Gingdev\Facebook;

use Facebook\FacebookRequest;
use Facebook\FacebookSession;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Facebook
{
    protected $session;

    public function __construct(string $name)
    {
        $cache = new FilesystemAdapter();

        $session = $cache->getItem('facebook.'.$name);

        if (!$session->isHit()) {
            throw new \LogicException(sprintf('Session "facebook.%s" does not exist.', $name));
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
