# Facebook Tool
Facebook Tool for PHP

![GitHub repo size](https://img.shields.io/github/repo-size/ging-dev/facebook-tool?color=c&label=size)
[![StyleCI](https://github.styleci.io/repos/335948618/shield?branch=main)](https://github.styleci.io/repos/335948618?branch=main)

# Note
This package is in beta, it will be dangerous to use

# Usage

```sh
composer require ging-dev/facebook-tool dev-main
```

## Login

```sh
./vendor/bin/console facebook:login default
```

```php
<?php

use Gingdev\Facebook;

require __DIR__.'/vendor/autoload.php';

$fb = new Facebook();
$fb->setSession('default');

// Get user info
try {
    $response = $fb->get('/me');
} catch(\Facebook\Exceptions\FacebookResponseException $e) {
    echo 'Graph returned an error: ' . $e->getMessage();
    exit;
}

$me = $response->getGraphUser();

echo 'Logged in as ' . $me->getName();
```
