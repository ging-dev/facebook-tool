<?php

declare(strict_types=1);

namespace Gingdev;

use Facebook\Authentication\AccessToken;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Facebook extends \Facebook\Facebook
{
    public function __construct(array $config = [])
    {
        $config = array_merge([
            'app_id' => 'app_id',
            'app_secret' => 'app_secret',
        ], $config);

        parent::__construct($config);
    }

    public function request($method, $endpoint, array $params = [], $accessToken = null, $eTag = null, $graphVersion = null)
    {
        $accessToken = $accessToken ?: $this->defaultAccessToken;
        $graphVersion = $graphVersion ?: $this->defaultGraphVersion;

        return new FacebookRequest(
            $this->app,
            $accessToken,
            $method,
            $endpoint,
            $params,
            $eTag,
            $graphVersion
        );
    }

    public function setSession(string $name)
    {
        $cache = new FilesystemAdapter();

        $session = $cache->getItem('facebook.'.$name);

        if (!$session->isHit()) {
            throw new \LogicException(sprintf('Session "facebook.%s" does not exist.', $name));
        }

        $this->defaultAccessToken = new AccessToken($session->get());
    }
}
