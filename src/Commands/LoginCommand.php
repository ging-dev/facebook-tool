<?php

namespace Gingdev\Facebook\Commands;

use Gingdev\Facebook\Facebook;
use Goutte\Client;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class LoginCommand extends Command
{
    const BASE_URL = 'https://mbasic.facebook.com/login';

    const TOKEN_URL = 'https://mbasic.facebook.com/dialog/oauth';

    protected $queryData = [
        'client_id' => '124024574287414',
        'redirect_uri' => 'fbconnect://success',
        'scope' => 'email,publish_actions,publish_pages,user_about_me,user_actions.books,user_actions.music,user_actions.news,user_actions.video,user_activities,user_birthday,user_education_history,user_events,user_games_activity,user_groups,user_hometown,user_interests,user_likes,user_location,user_notes,user_photos,user_questions,user_relationship_details,user_relationships,user_religion_politics,user_status,user_subscriptions,user_videos,user_website,user_work_history,friends_about_me,friends_actions.books,friends_actions.music,friends_actions.news,friends_actions.video,friends_activities,friends_birthday,friends_education_history,friends_events,friends_games_activity,friends_groups,friends_hometown,friends_interests,friends_likes,friends_location,friends_notes,friends_photos,friends_questions,friends_relationship_details,friends_relationships,friends_religion_politics,friends_status,friends_subscriptions,friends_videos,friends_website,friends_work_history,ads_management,create_event,create_note,export_stream,friends_online_presence,manage_friendlists,manage_notifications,manage_pages,photo_upload,publish_stream,read_friendlists,read_insights,read_mailbox,read_page_mailboxes,read_requests,read_stream,rsvp_event,share_item,sms,status_update,user_online_presence,video_upload,xmpp_login',
        'response_type' => 'token',
    ];

    /**
     * Browser Kit.
     *
     * @var Client
     */
    protected $client;

    protected static $defaultName = 'facebook:login';

    public function __construct()
    {
        $this->client = new Client();

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Login facebook.')
            ->setHelp('This command allows you to login to a facebook account...')
            ->addArgument('name', InputArgument::OPTIONAL, 'Cookie name?');
    }

    /**
     * Sign in to your account.
     *
     * @return void
     */
    protected function login(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        $question = new Question('Enter email: ');
        $email = $helper->ask($input, $output, $question);

        $question = new Question('Enter password: ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $password = $helper->ask($input, $output, $question);

        $this->client->request('GET', self::BASE_URL);

        $this->client->submitForm('login', [
            'email' => $email,
            'pass' => $password,
        ]);

        try {
            $this->nextStep($input, $output);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<fg=red>Login failed, please re-enter</>');
            // Re-login
            $this->login($input, $output);
        }
    }

    /**
     * Two-step verification.
     *
     * @return void
     */
    protected function nextStep(InputInterface $input, OutputInterface $output, int $failed = 0)
    {
        $helper = $this->getHelper('question');

        $form = $this->client->getCrawler()
            ->selectButton('submit[Submit Code]')
            ->form();

        $question = new Question('Enter 2-FA code: ');
        $code = $helper->ask($input, $output, $question);

        $form['approvals_code'] = $code;
        $this->client->submit($form);

        try {
            $this->client->getCrawler()
                ->selectButton('submit[Submit Code]')
                ->form();
            if (++$failed > 1) {
                throw new \RuntimeException('Login failed, you entered incorrectly too many times.');
            }
            $output->writeln('<fg=red>The recovery code is not correct, please re-enter it</>');
            // Re-enter the recovery code if it is not correct
            $this->nextStep($input, $output, $failed);
        } catch (\InvalidArgumentException $e) {
            $this->endStep();
        }
    }

    /**
     * Complete login.
     *
     * @return void
     */
    protected function endStep()
    {
        try {
            $this->dontSave();
            $this->client->submitForm('submit[Continue]');
            $this->client->submitForm('submit[This was me]');
            $this->dontSave();
        } catch (\InvalidArgumentException $e) {
            // Finish without checking the browser
        }
    }

    protected function dontSave()
    {
        $this->client->submitForm('submit[Continue]', [
            'name_action_selected' => 'dont_save',
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cache = new PhpFilesAdapter('', 0, Facebook::CACHE_DIR);

        try {
            $this->login($input, $output);
        } catch (\RuntimeException $e) {
            $output->writeln('<fg=red>'.$e->getMessage().'</>');

            return Command::FAILURE;
        }

        $this->client->request('GET', self::TOKEN_URL.'?'.http_build_query($this->queryData));
        $this->client->followRedirects(false);

        $form = $this->client
            ->getCrawler()
            ->filter('form')
            ->form();

        $this->client->submit($form);

        $location = $this->client->getResponse()
            ->getHeader('location');

        parse_str(
            parse_url($location, PHP_URL_FRAGMENT),
            $data
        );

        $name = $input->getArgument('name') ?? 'default';
        $account = $cache->getItem($name);
        $account->set($data['access_token']);

        $cache->save($account);

        $output->writeln('<fg=green>Logged in successfully</>');

        return Command::SUCCESS;
    }
}
