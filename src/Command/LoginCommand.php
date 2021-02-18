<?php

namespace Gingdev\Facebook\Command;

use Gingdev\Facebook\Facebook;
use Goutte\Client;
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
        'scope' => 'user_about_me,user_actions.books,user_actions.fitness,user_actions.music,user_actions.news,user_actions.video,user_activities,user_birthday,user_education_history,user_events,user_friends,user_games_activity,user_groups,user_hometown,user_interests,user_likes,user_location,user_managed_groups,user_photos,user_posts,user_relationship_details,user_relationships,user_religion_politics,user_status,user_tagged_places,user_videos,user_website,user_work_history,email,manage_notifications,manage_pages,publish_actions,publish_pages,read_insights,read_page_mailboxes,read_stream,rsvp_event,read_mailbox',
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

        if ($this->filter('#checkpoint_title')) {
            if ($this->filter('#approvals_code')) {
                return $this->nextStep($input, $output);
            }
            throw new \RuntimeException('You must enable 2-factor authentication.');
        }

        $output->writeln('<fg=red>Login failed, please re-enter</>');
        // Re-login
        return $this->login($input, $output);
    }

    /**
     * Two-step verification.
     *
     * @return void
     */
    protected function nextStep(InputInterface $input, OutputInterface $output, int $failed = 0)
    {
        $helper = $this->getHelper('question');
        $question = new Question('Enter 2-FA code: ');
        $code = $helper->ask($input, $output, $question);

        $this->client->submitForm('submit[Submit Code]', [
            'approvals_code' => $code,
        ]);

        if ($this->filter('#approvals_code')) {
            if (++$failed > 1) {
                throw new \RuntimeException('Login failed, you entered incorrectly too many times.');
            }

            $output->writeln('<fg=red>The recovery code is not correct, please re-enter it</>');
            // Re-enter the recovery code if it is not correct
            return $this->nextStep($input, $output, $failed);
        }

        return $this->endStep();
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
            $this->continue();
            $this->dontSave();
        } catch (\InvalidArgumentException $e) {
            // Finish without checking the browser
        }
    }

    protected function continue()
    {
        $this->client->submitForm('submit[Continue]');
        $this->client->submitForm('submit[This was me]');
    }

    protected function dontSave()
    {
        $this->client->submitForm('submit[Continue]', [
            'name_action_selected' => 'dont_save',
        ]);
    }

    protected function filter($input)
    {
        $crawler = $this->client->getCrawler()->filter($input);

        return $crawler->count() > 0;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cache = Facebook::getCache();

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
        $output->writeln('Access token: '.$data['access_token']);

        return Command::SUCCESS;
    }
}
