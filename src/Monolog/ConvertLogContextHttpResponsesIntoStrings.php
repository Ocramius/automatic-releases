<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Psr\Http\Message\ResponseInterface;

/** @internal */
final class ConvertLogContextHttpResponsesIntoStrings implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return new LogRecord(
            $record->datetime,
            $record->channel,
            $record->level,
            $record->message,
            array_map(static function ($item): mixed {
                if (! $item instanceof ResponseInterface) {
                    return $item;
                }

                return $item->getStatusCode()
                    . ' "'
                    . $item
                        ->getBody()
                        ->__toString()
                    . '"';
            }, $record->context),
            $record->extra,
            $record->formatted
        );
    }
}