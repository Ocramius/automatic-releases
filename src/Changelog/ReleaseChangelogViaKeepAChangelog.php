<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Changelog;

use Laminas\AutomaticReleases\Git\CommitFile;
use Laminas\AutomaticReleases\Git\Push;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Git\Value\SemVerVersion;
use Phly\KeepAChangelog\Common\DiscoverChangelogEntryListener;
use Phly\KeepAChangelog\Config;
use Phly\KeepAChangelog\Version\ReadyLatestChangelogEvent;
use Phly\KeepAChangelog\Version\SetDateForChangelogReleaseListener;
use Symfony\Component\Console\Output\NullOutput;

use function date;
use function file_exists;
use function sprintf;

final class ReleaseChangelogViaKeepAChangelog implements ReleaseChangelog
{
    private CommitFile $commitFile;
    private Push $push;

    public function __construct(
        CommitFile $commitFile,
        Push $push
    ) {
        $this->commitFile = $commitFile;
        $this->push       = $push;
    }

    /**
     * @psalm-param non-empty-string $repositoryDirectory
     */
    public function __invoke(
        string $repositoryDirectory,
        SemVerVersion $version,
        BranchName $sourceBranch
    ): void {
        $changelogFile = $repositoryDirectory . '/CHANGELOG.md';
        if (! file_exists($changelogFile)) {
            // No changelog
            return;
        }

        $versionString = $version->fullReleaseName();

        if (! $this->updateChangelog($changelogFile, $versionString)) {
            // Failure to update; nothing to commit
            return;
        }

        ($this->commitFile)(
            $repositoryDirectory,
            $sourceBranch,
            'CHANGELOG.md',
            sprintf('%s readiness', $versionString)
        );

        ($this->push)($repositoryDirectory, $sourceBranch->name());
    }

    private function updateChangelog(string $changelogFile, string $versionString): bool
    {
        $event = $this->createReadyLatestChangelogEvent($changelogFile, $versionString);

        (new DiscoverChangelogEntryListener())($event);

        if ($event->failed()) {
            return false;
        }

        (new SetDateForChangelogReleaseListener())($event);

        return ! $event->failed();
    }

    private function createReadyLatestChangelogEvent(
        string $changelogFile,
        string $versionString
    ): ReadyLatestChangelogEvent {
        /**
         * Hard-coded extension to allow setting version to known value
         *
         * @psalm-suppress PropertyNotSetInConstructor
         */
        $event = new class ($versionString) extends ReadyLatestChangelogEvent {
            private string $releaseDate;
            private string $version;

            public function __construct(string $versionString)
            {
                $this->releaseDate = date('Y-m-d');
                $this->version     = $versionString;
                // Required as failure methods write to output
                $this->output = new NullOutput();
            }

            /**
             * Overridden as parent uses private property access.
             */
            public function version(): string
            {
                return $this->version;
            }

            /**
             * Overridden as parent uses private property access.
             */
            public function releaseDate(): string
            {
                return $this->releaseDate;
            }
        };

        $event->discoveredConfiguration($this->createKeepAChangelogConfig($changelogFile));

        return $event;
    }

    private function createKeepAChangelogConfig(string $changelogFile): Config
    {
        // Inline extension to allow hard-coding changelog file to what is known.
        return new class ($changelogFile) extends Config {
            private string $changelogFile;

            public function __construct(string $changelogFile)
            {
                parent::__construct();
                $this->changelogFile = $changelogFile;
            }

            public function changelogFile(): string
            {
                return $this->changelogFile;
            }
        };
    }
}
