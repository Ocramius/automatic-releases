<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Application\Command;

use Laminas\AutomaticReleases\Environment\Variables;
use Laminas\AutomaticReleases\Git\Fetch;
use Laminas\AutomaticReleases\Git\GetMergeTargetCandidateBranches;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Github\Api\V3\SetDefaultBranch;
use Laminas\AutomaticReleases\Github\Event\Factory\LoadCurrentGithubEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

final class SwitchDefaultBranchToNextMinor extends Command
{
    private Variables $variables;
    private LoadCurrentGithubEvent $loadGithubEvent;
    private Fetch $fetch;
    private GetMergeTargetCandidateBranches $getMergeCandidates;
    private Push $push;
    private SetDefaultBranch $switchDefaultBranch;

    public function __construct(
        Variables $variables,
        LoadCurrentGithubEvent $loadGithubEvent,
        Fetch $fetch,
        GetMergeTargetCandidateBranches $getMergeCandidates,
        Push $push,
        SetDefaultBranch $switchDefaultBranch
    ) {
        parent::__construct('laminas:automatic-releases:switch-default-branch-to-next-minor');

        $this->variables           = $variables;
        $this->loadGithubEvent     = $loadGithubEvent;
        $this->fetch               = $fetch;
        $this->getMergeCandidates  = $getMergeCandidates;
        $this->push                = $push;
        $this->switchDefaultBranch = $switchDefaultBranch;
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
        $releaseVersion  = $event->version();
        $newestBranch    = $mergeCandidates->newestReleaseBranch();

        if ($newestBranch === null) {
            $output->writeln('No stable branches found: cannot switch default branch');

            return 0;
        }

        $nextDefaultBranch = $mergeCandidates->newestFutureReleaseBranchAfter($releaseVersion);

        if (! $mergeCandidates->contains($nextDefaultBranch)) {
            $this->push->__invoke(
                $repositoryPath,
                $newestBranch->name(),
                $nextDefaultBranch->name()
            );
        }

        $this->switchDefaultBranch->__invoke($event->repository(), $nextDefaultBranch);

        return 0;
    }
}
