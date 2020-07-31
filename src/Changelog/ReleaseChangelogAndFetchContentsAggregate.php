<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

class ReleaseChangelogAndFetchContentsAggregate implements ReleaseChangelogAndFetchContents
{
    /** @var ReleaseChangelogAndFetchContents[] */
    private array $strategies;

    /**
     * @param ReleaseChangelogAndFetchContents[] $strategies
     */
    public function __construct(array $strategies)
    {
        $this->strategies = $strategies;
    }

    public function __invoke(ReleaseChangelogEvent $releaseChangelogEvent): ?string
    {
        foreach ($this->strategies as $strategy) {
            $changelog = $strategy($releaseChangelogEvent);
            if (null !== $changelog) {
                return $changelog;
            }
        }

        return sprintf(
            '# %s/%s %s',
            $releaseChangelogEvent->repositoryName->owner(),
            $releaseChangelogEvent->repositoryName->name(),
            $releaseChangelogEvent->version->fullReleaseName()
        );
    }
}