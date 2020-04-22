<?php

declare(strict_types=1);

namespace Chemaclass\JiraStatusNotifier;

use Chemaclass\JiraStatusNotifier\Channel\ChannelInterface;
use Chemaclass\JiraStatusNotifier\Channel\ChannelResult;
use Chemaclass\JiraStatusNotifier\Channel\TicketsByAssignee;
use Chemaclass\JiraStatusNotifier\IO\JiraConnectorInput;
use Chemaclass\JiraStatusNotifier\Jira\Board;
use Chemaclass\JiraStatusNotifier\Jira\JiraHttpClient;
use Chemaclass\JiraStatusNotifier\Jira\JqlUrlBuilder;
use Chemaclass\JiraStatusNotifier\Jira\JqlUrlFactory;
use Chemaclass\JiraStatusNotifier\Jira\ReadModel\Company;
use Webmozart\Assert\Assert;

final class JiraConnector
{
    private JiraHttpClient $jiraHttpClient;

    /** @var ChannelInterface[] */
    private $channels;

    public function __construct(JiraHttpClient $jiraHttpClient, array $channels)
    {
        Assert::allIsInstanceOf($channels, ChannelInterface::class);
        $this->jiraHttpClient = $jiraHttpClient;
        $this->channels = $channels;
    }

    /**
     * It passes the tickets by assignee (from Jira) to all its channels.
     *
     * @return array<string,ChannelResult>
     */
    public function handle(JiraConnectorInput $input): array
    {
        $jiraBoard = new Board($input->daysForStatus());
        $company = Company::withNameAndProject($input->companyName(), $input->jiraProjectName());
        $result = [];

        $ticketsByAssignee = (new TicketsByAssignee(
            $this->jiraHttpClient,
            new JqlUrlFactory($jiraBoard, JqlUrlBuilder::inOpenSprints($company)),
            $input->jiraUsersToIgnore()
        ))->fetchFromBoard($jiraBoard);

        foreach ($this->channels as $channel) {
            $result[get_class($channel)] = $channel->send($ticketsByAssignee, $company);
        }

        return $result;
    }
}
