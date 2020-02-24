<?php
/**
 * This example demonstrates how to notify via Email and Slack
 * using the ENV parameters (from the .env file)
 */
declare(strict_types=1);

require dirname(__DIR__) . '/../vendor/autoload.php';

use Chemaclass\ScrumMaster\Channel\Cli;
use Chemaclass\ScrumMaster\Common\EnvKeys;
use Chemaclass\ScrumMaster\IO\EchoOutput;
use Chemaclass\ScrumMaster\IO\NotifierInput;
use Chemaclass\ScrumMaster\IO\NotifierOutput;
use Chemaclass\ScrumMaster\Jira\JiraHttpClient;
use Chemaclass\ScrumMaster\Notifier;
use Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

Dotenv::create(__DIR__)->load();
EnvKeys::create((array)getenv())->validate(file_get_contents(__DIR__ . '/.env.dist'));

$jiraHttpClient = new JiraHttpClient(HttpClient::create([
    'auth_basic' => [getenv('JIRA_API_LABEL'), getenv('JIRA_API_PASSWORD')],
]));

$notifier = new Notifier($jiraHttpClient, [new Cli\Channel()]);

$result = $notifier->notify(NotifierInput::new(
    getenv(NotifierInput::COMPANY_NAME),
    getenv(NotifierInput::JIRA_PROJECT_NAME),
    json_decode(getenv(NotifierInput::DAYS_FOR_STATUS), true),
    json_decode(getenv(NotifierInput::JIRA_USERS_TO_IGNORE), true)
));

(new NotifierOutput(
    new EchoOutput(),
    new Environment(new FilesystemLoader('../templates'))
))->write($result, 'output/cli-template.twig');
