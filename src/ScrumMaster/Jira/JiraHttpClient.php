<?php

declare(strict_types=1);

namespace App\ScrumMaster\Jira;

use App\ScrumMaster\Jira\ReadModel\CompanyProject;
use App\ScrumMaster\Jira\ReadModel\JiraTicket;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JiraHttpClient
{
    /** @var HttpClientInterface */
    private $jiraClient;

    /** @var UrlFactoryInterface */
    private $urlFactory;

    public function __construct(HttpClientInterface $jiraClient, UrlFactoryInterface $urlFactory)
    {
        $this->jiraClient = $jiraClient;
        $this->urlFactory = $urlFactory;
    }

    /** @return JiraTicket[] */
    public function getTickets(CompanyProject $companyProject, string $status): array
    {
        $url = $this->urlFactory->buildUrl($companyProject, $status);
        $response = $this->jiraClient->request('GET', $url);

        return JiraTickets::fromJira($response->toArray());
    }
}
