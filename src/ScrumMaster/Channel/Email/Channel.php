<?php

declare(strict_types=1);

namespace Chemaclass\ScrumMaster\Channel\Email;

use Chemaclass\ScrumMaster\Channel\ChannelInterface;
use Chemaclass\ScrumMaster\Channel\ChannelResult;
use Chemaclass\ScrumMaster\Channel\Email\ReadModel\EmailAddress;
use Chemaclass\ScrumMaster\Channel\Email\ReadModel\Message;
use Chemaclass\ScrumMaster\Channel\Email\ReadModel\ToAddress;
use Chemaclass\ScrumMaster\Channel\MessageGeneratorInterface;
use Chemaclass\ScrumMaster\Channel\ReadModel\ChannelIssue;
use Chemaclass\ScrumMaster\Jira\Board;
use Chemaclass\ScrumMaster\Jira\JiraHttpClient;
use Chemaclass\ScrumMaster\Jira\JqlUrlFactory;
use Chemaclass\ScrumMaster\Jira\ReadModel\Company;
use Chemaclass\ScrumMaster\Jira\ReadModel\JiraTicket;

final class Channel implements ChannelInterface
{
    /** @var MailerClient */
    private $client;

    /** @var MessageGeneratorInterface */
    private $messageGenerator;

    /** @var null|ByPassEmail */
    private $byPassEmail;

    public function __construct(
        MailerClient $client,
        MessageGeneratorInterface $messageGenerator,
        ?ByPassEmail $byPassEmail = null
    ) {
        $this->messageGenerator = $messageGenerator;
        $this->client = $client;
        $this->byPassEmail = $byPassEmail;
    }

    public function sendNotifications(
        Board $board,
        JiraHttpClient $jiraClient,
        Company $company,
        JqlUrlFactory $jqlUrlFactory,
        array $jiraUsersToIgnore = []
    ): ChannelResult {
        $result = new ChannelResult();

        foreach ($board->maxDaysInStatus() as $statusName => $maxDays) {
            $tickets = $jiraClient->getTickets($jqlUrlFactory, $statusName);

            $result->append($this->sendEmails($company, $tickets, $jiraUsersToIgnore));
        }

        return $result;
    }

    private function sendEmails(Company $company, array $tickets, array $jiraUsersToIgnore): ChannelResult
    {
        $result = new ChannelResult();

        /** @var JiraTicket $ticket */
        foreach ($tickets as $ticket) {
            $assignee = $ticket->assignee();

            if (in_array($assignee->key(), $jiraUsersToIgnore)) {
                continue;
            }

            $this->sendEmail($ticket, $company);
            $issue = ChannelIssue::withCodeAndAssignee(200, $ticket->assignee()->displayName());
            $result->addChannelIssue($ticket->key(), $issue);
        }

        return $result;
    }

    private function sendEmail(JiraTicket $ticket, Company $company): void
    {
        $this->client->sendMessage(new Message(
            new ToAddress([
                new EmailAddress(
                    $this->emailFromTicket($ticket),
                    $ticket->assignee()->displayName()
                ),
            ]),
            $this->messageGenerator->forJiraTicket($ticket, $company->companyName())
        ));
    }

    private function emailFromTicket(JiraTicket $ticket): string
    {
        if ($this->byPassEmail) {
            $assigneeKey = $ticket->assignee()->key();
            $overriddenEmail = $this->byPassEmail->byAssigneeKey($assigneeKey);

            if ($overriddenEmail) {
                return $overriddenEmail;
            }
        }

        return $ticket->assignee()->email();
    }
}
