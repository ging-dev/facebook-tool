<?php

declare(strict_types=1);

namespace Gingdev\Facebook;

use Goutte\Client;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\Yaml\Yaml;

class Tool
{
    const TOKEN_URL = 'https://mbasic.facebook.com/dialog/oauth';

    protected $queryData = [
        'client_id' => '124024574287414',
        'redirect_uri' => 'fbconnect://success',
        'scope' => 'email,read_insights,read_audience_network_insights,rsvp_event,offline_access,publish_video,openid,catalog_management,user_managed_groups,groups_show_list,pages_manage_cta,pages_manage_instant_articles,pages_show_list,pages_messaging,pages_messaging_phone_number,pages_messaging_subscriptions,read_page_mailboxes,ads_management,ads_read,business_management,instagram_basic,instagram_manage_comments,instagram_manage_insights,instagram_content_publish,publish_to_groups,groups_access_member_info,leads_retrieval,whatsapp_business_management,attribution_read,pages_read_engagement,pages_manage_metadata,pages_read_user_content,pages_manage_ads,pages_manage_posts,pages_manage_engagement,audience_network_placement_management,public_profile',
        'response_type' => 'token',
    ];

    /**
     * @var string
     */
    protected $accessToken;

    /**
     * @var Client
     */
    protected $browser;

    public function __construct(string $filename)
    {
        $cookies = Yaml::parseFile($filename);
        $cookieJar = new CookieJar();

        foreach ($cookies as $name => $value) {
            $cookieJar->set(new Cookie($name, $value));
        }

        $this->browser = new Client(null, null, $cookieJar);

        $this->requestAccessToken();
    }

    public function requestAccessToken()
    {
        $this->browser->request('GET', self::TOKEN_URL.'?'.http_build_query($this->queryData));
        $this->browser->followRedirects(false);

        $form = $this->browser
            ->getCrawler()
            ->filter('form')
            ->form();

        $this->browser->submit($form);

        $location = $this->browser->getResponse()
            ->getHeader('location');

        parse_str(
            parse_url($location, PHP_URL_FRAGMENT),
            $data
        );

        $this->accessToken = $data['access_token'];
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function getBrowser()
    {
        return $this->browser;
    }
}