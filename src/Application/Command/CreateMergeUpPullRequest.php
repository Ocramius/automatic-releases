<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Environment\Variables;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Github\Api\GraphQL\Query\GetGithubMilestone;
use Laminas\AutomaticReleases\Github\Api\V3\CreatePullRequest;
use Laminas\AutomaticReleases\Github\CreateReleaseText;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function sprintf;
use function uniqid;

final class CreateMergeUpPullRequest extends Command
{
    private Variables $variables;
    private LoadCurrentGithubEvent $loadGithubEvent;
    private Fetch $fetch;
    private GetMergeTargetCandidateBranches $getMergeCandidates;
    private GetGithubMilestone $getMilestone;
    private CreateReleaseText $createReleaseText;
    private Push $push;
    private CreatePullRequest $createPullRequest;

    public function __construct(
        Variables $variables,
        LoadCurrentGithubEvent $loadGithubEvent,
        Fetch $fetch,
        GetMergeTargetCandidateBranches $getMergeCandidates,
        GetGithubMilestone $getMilestone,
        CreateReleaseText $createReleaseText,
        Push $push,
        CreatePullRequest $createPullRequest
    ) {
        parent::__construct('laminas:automatic-releases:create-merge-up-pull-request');

        $this->variables          = $variables;
        $this->loadGithubEvent    = $loadGithubEvent;
        $this->fetch              = $fetch;
        $this->getMergeCandidates = $getMergeCandidates;
        $this->getMilestone       = $getMilestone;
        $this->createReleaseText  = $createReleaseText;
        $this->push               = $push;
        $this->createPullRequest  = $createPullRequest;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $event          = $this->loadGithubEvent->__invoke();
        $repositoryPath = $this->variables->githubWorkspacePath();

        Assert::directory($repositoryPath . '/.git');

        $this->fetch->__invoke(
            $event->repository()
                ->uriWithTokenAuthentication($this->variables->githubToken()),
            $repositoryPath
        );

        $mergeCandidates = $this->getMergeCandidates->__invoke($repositoryPath);

        $releaseVersion = $event->version();
        $releaseBranch  = $mergeCandidates->targetBranchFor($releaseVersion);
        $mergeUpTarget  = $mergeCandidates->branchToMergeUp($releaseVersion);

        if ($mergeUpTarget === null) {
            $output->writeln(sprintf(
                'No merge-up candidate for release %s - skipping pull request creation',
                $releaseVersion->fullReleaseName()
            ));

            return 0;
        }

        Assert::notNull(
            $releaseBranch,
            sprintf('No valid release branch found for version %s', $releaseVersion->fullReleaseName())
        );

        $mergeUpBranch = BranchName::fromName(
            $releaseBranch->name()
            . '-merge-up-into-'
            . $mergeUpTarget->name()
            . uniqid('_', true) // This is to ensure that a new merge-up pull request is created even if one already exists
        );

        $this->push->__invoke($repositoryPath, $releaseBranch->name(), $mergeUpBranch->name());
        $this->createPullRequest->__invoke(
            $event->repository(),
            $mergeUpBranch,
            $mergeUpTarget,
            'Merge release ' . $releaseVersion->fullReleaseName() . ' into ' . $mergeUpTarget->name(),
            $this->createReleaseText->__invoke(
                $this->getMilestone->__invoke($event->repository(), $event->milestoneNumber()),
                $event->repository(),
                $event->version(),
                $releaseBranch,
                $repositoryPath
            )
        );

        return 0;
    }
}
