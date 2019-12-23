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
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailSmtpTransport;
use Symfony\Component\Mailer\Mailer;

$dotEnv = Dotenv\Dotenv::create(__DIR__);
$dotEnv->load();

$mandatoryKeys = EnvKeys::fromFile(file_get_contents(__DIR__ . '/.env.dist'));
$mandatoryKeys->validate();

$jiraHttpClient = new JiraHttpClient(HttpClient::create([
    'auth_basic' => [getenv('JIRA_API_LABEL'), getenv('JIRA_API_PASSWORD')],
]));

$channels = [
    new Email\Channel(
        new Mailer(new GmailSmtpTransport(getenv('MAILER_USERNAME'), getenv('MAILER_PASSWORD'))),
        Email\MessageGenerator::withTimeToDiff(new DateTimeImmutable()),
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
        Slack\MessageGenerator::withTimeToDiff(new DateTimeImmutable())
    ),
];

$notifier = new Notifier($jiraHttpClient, $channels);

$result = $notifier->notify(NotifierInput::new(
    getenv(NotifierInput::COMPANY_NAME),
    getenv(NotifierInput::JIRA_PROJECT_NAME),
    json_decode(getenv(NotifierInput::DAYS_FOR_STATUS), true),
    json_decode(getenv(NotifierInput::JIRA_USERS_TO_IGNORE), true)
));

$output = new NotifierOutput(new EchoOutput());
$output->write($result);
