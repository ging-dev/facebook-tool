<?php

namespace Gingdev\Facebook\Command;

use Goutte\Client;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class LoginCommand extends Command
{
    const BASE_URL = 'https://mbasic.facebook.com/login';

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
        $this->setDescription('Login to facebook')
            ->setHelp('This command allows you to login to a facebook account...')
            ->addArgument('name', InputArgument::OPTIONAL, 'File name');
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

        if (strlen($code) >= 32) {
            $code = (new GoogleAuthenticator())->getCode($code);
        }

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
        try {
            $this->login($input, $output);
        } catch (\RuntimeException $e) {
            $output->writeln('<fg=red>'.$e->getMessage().'</>');

            return Command::FAILURE;
        }

        $cookies = $this->client
            ->getCookieJar()
            ->allValues('https://facebook.com');

        $yaml = Yaml::dump($cookies);

        $name = $input->getArgument('name') ?? 'default';
        file_put_contents(getcwd().DIRECTORY_SEPARATOR.$name.'.yaml', $yaml);
        $output->writeln('<fg=green>Logged in successfully</>');

        return Command::SUCCESS;
    }
}
