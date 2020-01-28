<?php
/**
 * This example demonstrates how to notify via Email and Slack
 * using the ENV parameters (from the .env file)
 */
declare(strict_types=1);

require dirname(__DIR__) . '/../vendor/autoload.php';

use Chemaclass\ScrumMaster\Channel\Email;
use Chemaclass\ScrumMaster\Channel\Email\ByPassEmail;
use Chemaclass\ScrumMaster\Channel\Slack;
use Chemaclass\ScrumMaster\Common\EnvKeys;
use Chemaclass\ScrumMaster\IO\EchoOutput;
use Chemaclass\ScrumMaster\IO\NotifierInput;
use Chemaclass\ScrumMaster\IO\NotifierOutput;
use Chemaclass\ScrumMaster\Jira\JiraHttpClient;
use Chemaclass\ScrumMaster\Notifier;
use Dotenv\Dotenv;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailSmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

Dotenv::create(__DIR__)->load();
EnvKeys::create((array) getenv())->validate(file_get_contents(__DIR__ . '/.env.dist'));

$jiraHttpClient = new JiraHttpClient(HttpClient::create([
    'auth_basic' => [getenv('JIRA_API_LABEL'), getenv('JIRA_API_PASSWORD')],
]));

$channels = [
    new Email\Channel(
        new Mailer(new GmailSmtpTransport(getenv('MAILER_USERNAME'), getenv('MAILER_PASSWORD'))),
        Email\MessageGenerator::beingNow(new DateTimeImmutable()),
        new Email\AddressGenerator((new ByPassEmail())
            ->setSendEmailsToAssignee(false) // <- OverriddenEmails wont have no effect as long as this is false
            ->setOverriddenEmails(json_decode(getenv('OVERRIDDEN_EMAILS'), true))
            ->setSendCopyTo(getenv('MAILER_USERNAME')))
    ),
    new Slack\Channel(
        new Slack\HttpClient(HttpClient::create([
            'auth_bearer' => getenv('SLACK_BOT_USER_OAUTH_ACCESS_TOKEN'),
        ])),
        Slack\JiraMapping::jiraNameWithSlackId(json_decode(getenv('SLACK_MAPPING_IDS'), true)),
        Slack\MessageGenerator::beingNow(new DateTimeImmutable())
    ),
];

$notifier = new Notifier($jiraHttpClient, $channels);

$result = $notifier->notify(NotifierInput::new(
    getenv(NotifierInput::COMPANY_NAME),
    getenv(NotifierInput::JIRA_PROJECT_NAME),
    json_decode(getenv(NotifierInput::DAYS_FOR_STATUS), true),
    json_decode(getenv(NotifierInput::JIRA_USERS_TO_IGNORE), true)
));

(new NotifierOutput(
    new EchoOutput(),
    new Environment(new FilesystemLoader('../templates'))
))->write($result, 'output/channel-result.twig');