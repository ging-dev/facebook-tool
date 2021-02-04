<?php

declare(strict_types=1);

namespace Gingdev;

class FacebookRequest extends \Facebook\FacebookRequest
{
    public function getParams()
    {
        $params = $this->params;

        $accessToken = $this->getAccessToken();
        if ($accessToken) {
            $params['access_token'] = $accessToken;
        }

        return $params;
    }
}
