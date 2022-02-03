<?php

declare(strict_types=1);

namespace Gingdev\Facebook;

use Goutte\Client;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\Response;

class Tool
{
    public const TOKEN_URL = 'https://mbasic.facebook.com/dialog/oauth';

    /** @var string[] */
    protected $queryData = [
        'client_id' => '124024574287414',
        'redirect_uri' => 'fbconnect://success',
        'scope' => 'email,read_insights,read_audience_network_insights,rsvp_event,offline_access,publish_video,openid,catalog_management,user_managed_groups,groups_show_list,pages_manage_cta,pages_manage_instant_articles,pages_show_list,pages_messaging,pages_messaging_phone_number,pages_messaging_subscriptions,read_page_mailboxes,ads_management,ads_read,business_management,instagram_basic,instagram_manage_comments,instagram_manage_insights,instagram_content_publish,publish_to_groups,groups_access_member_info,leads_retrieval,whatsapp_business_management,attribution_read,pages_read_engagement,pages_manage_metadata,pages_read_user_content,pages_manage_ads,pages_manage_posts,pages_manage_engagement,audience_network_placement_management,public_profile',
        'response_type' => 'token',
    ];

    protected string $accessToken = '';

    protected Client $browser;

    public function __construct(string $filename)
    {
        $json = \file_get_contents($filename);
        if (false === $json) {
            throw new \RuntimeException("Unable to load file {$filename}");
        }

        $cookies = \json_decode($json, true);
        if (\JSON_ERROR_NONE !== \json_last_error()) {
            throw new \InvalidArgumentException('json_decode error: '.\json_last_error_msg());
        }

        if (!\is_array($cookies)) {
            throw new \RuntimeException("Invalid cookie file: {$filename}");
        }

        /** @var string[] $cookies */
        $cookieJar = new CookieJar();
        $cookieJar->updateFromSetCookie($cookies);

        $this->browser = new Client(cookieJar: $cookieJar);
    }

    public function requestAccessToken(): void
    {
        $this->browser->request('GET', self::TOKEN_URL.'?'.http_build_query($this->queryData));
        $this->browser->followRedirects(false);

        try {
            $form = $this->browser
                ->getCrawler()
                ->filter('form')
                ->form();
        } catch (\InvalidArgumentException) {
            throw new \LogicException('Cookies have expired or are not valid.');
        }

        $this->browser->submit($form);

        /** @var Response */
        $resp = $this->browser->getResponse();

        /** @var string */
        $location = $resp->getHeader('location');

        parse_str(
            parse_url($location, PHP_URL_FRAGMENT),
            $data
        );

        $this->accessToken = (string) $data['access_token'];
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getBrowser(): Client
    {
        return $this->browser;
    }

    public function likePost(string $id, int $mode = 0): bool
    {
        $crawler = $this->browser->request('GET', 'https://mbasic.facebook.com/reactions/picker/?ft_id='.$id);

        try {
            $link = $crawler->filter('a[style="display:block"]')->eq($mode)->link();
            $this->browser->click($link);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    public function likePage(string $id): bool
    {
        $crawler = $this->browser->request('GET', 'https://mbasic.facebook.com/'.$id.'/about');

        try {
            $link = $crawler->filterXPath('//a[contains(@href, "/a/profile.php")]')->link();
            $this->browser->click($link);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    public function followPage(string $id): bool
    {
        $crawler = $this->browser->request('GET', 'https://mbasic.facebook.com/'.$id.'/about');

        try {
            $link = $crawler->filter('a[id="pages_follow_action_id"]')->link();
            $this->browser->click($link);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    public function followUser(string $id): bool
    {
        $crawler = $this->browser->request('GET', 'https://mbasic.facebook.com/'.$id.'/about');

        try {
            $link = $crawler->filterXPath('//a[contains(@href, "/a/subscribe.php")]')->link();
            $this->browser->click($link);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    public function commentPost(string $id, string $message): bool
    {
        $crawler = $this->browser->request('GET', 'https://mbasic.facebook.com/mbasic/comment/advanced/?target_id='.$id.'&at=compose');

        try {
            $form = $crawler->filter('form')->form();
            $form->remove('photo'); // watch out for this damn thing
            $this->browser->submit($form, ['comment_text' => $message]);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    public function sharePost(string $id): bool
    {
        // Nah, I hope there will be a quicker solution
        $crawler = $this->browser->request('GET', 'https://mbasic.facebook.com/'.$id);

        try {
            $link = $crawler->filterXPath('//a[contains(@href, "/composer/mbasic")]')->link();
            $this->browser->click($link);
            $this->browser->submitForm('view_post');
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }
}
