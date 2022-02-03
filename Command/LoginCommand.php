<?php

declare(strict_types=1);

namespace Gingdev\Facebook\Command;

use Goutte\Client;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class LoginCommand extends Command
{
    public const BASE_URL = 'https://mbasic.facebook.com/login';

    protected Client $client;

    protected static $defaultName = 'facebook:login';

    public function __construct()
    {
        $this->client = new Client();

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Login to facebook')
            ->setHelp('This command allows you to login to a facebook account...');
    }

    /**
     * Sign in to your account.
     */
    protected function login(InputInterface $input, OutputInterface $output): void
    {
        /** @var QuestionHelper */
        $helper = $this->getHelper('question');

        $question = new Question('Enter email: ');

        $email = (string) $helper->ask($input, $output, $question);

        $question = new Question('Enter password: ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $password = (string) $helper->ask($input, $output, $question);

        $this->client->request('GET', self::BASE_URL);

        $this->client->submitForm('login', [
            'email' => $email,
            'pass' => $password,
        ]);

        if ($this->filter('#checkpoint_title')) {
            if ($this->filter('#approvals_code')) {
                $this->nextStep($input, $output);

                return;
            }
            throw new \RuntimeException('You must enable 2-factor authentication.');
        }

        $output->writeln('<fg=red>Login failed, please re-enter</>');
        // Re-login
        $this->login($input, $output);
    }

    /**
     * Two-step verification.
     */
    protected function nextStep(InputInterface $input, OutputInterface $output, int $failed = 0): void
    {
        /** @var QuestionHelper */
        $helper = $this->getHelper('question');
        $question = new Question('Enter 2-FA code: ');

        $code = (string) $helper->ask($input, $output, $question);

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
            $this->nextStep($input, $output, $failed);

            return;
        }

        $this->endStep();
    }

    /**
     * Complete login.
     */
    protected function endStep(): void
    {
        try {
            $this->dontSave();
            $this->continue();
            $this->dontSave();
        } catch (\InvalidArgumentException) {
            // Finish without checking the browser
        }
    }

    protected function continue(): void
    {
        $this->client->submitForm('submit[Continue]');
        $this->client->submitForm('submit[This was me]');
    }

    protected function dontSave(): void
    {
        $this->client->submitForm('submit[Continue]', [
            'name_action_selected' => 'dont_save',
        ]);
    }

    protected function filter(string $input): bool
    {
        $crawler = $this->client->getCrawler()->filter($input);

        return $crawler->count() > 0;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->login($input, $output);
        } catch (\RuntimeException $e) {
            $output->writeln('<fg=red>'.$e->getMessage().'</>');

            return Command::FAILURE;
        }

        $cookies = $this->client->getCookieJar()->all();
        $cookies = \array_map('strval', $cookies);
        $cookies = \json_encode($cookies);

        \file_put_contents(getcwd().DIRECTORY_SEPARATOR.'cookies.json', $cookies);
        $output->writeln('<fg=green>Logged in successfully</>');

        return Command::SUCCESS;
    }
}
