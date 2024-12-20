<?php

declare(strict_types=1);
/**
 * This file is part of friendsofhyperf/components.
 *
 * @link     https://github.com/friendsofhyperf/components
 * @document https://github.com/friendsofhyperf/components/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */

namespace FriendsOfHyperf\Telescope;

use Closure;
use FriendsOfHyperf\Telescope\Contract\EntriesRepository;
use Hyperf\Collection\Arr;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\Context;

use function Hyperf\Collection\collect;
use function Hyperf\Coroutine\defer;

class Telescope
{
    /**
     * @deprecated since v3.1, use `\FriendsOfHyperf\Telescope\SaveMode::SYNC` instead, will be removed in v3.2
     */
    public const SYNC = 0;

    /**
     * @deprecated since v3.1, use `\FriendsOfHyperf\Telescope\SaveMode::ASYNC` instead, will be removed in v3.2
     */
    public const ASYNC = 1;

    public const ENTRIES = 'telescope.context.entries';

    /**
     * The list of hidden request headers.
     */
    public static array $hiddenRequestHeaders = [
        'authorization',
        'php-auth-pw',
    ];

    /**
     * The list of hidden request parameters.
     */
    public static array $hiddenRequestParameters = [
        'password',
        'password_confirmation',
    ];

    /**
     * The list of hidden response parameters.
     */
    public static array $hiddenResponseParameters = [];

    /**
     * Indicates if Telescope should ignore events fired by Laravel.
     */
    public static bool $ignoreFrameworkEvents = true;

    /**
     * Indicates if Telescope should use the dark theme.
     */
    public static bool $useDarkTheme = false;

    /**
     * Indicates if Telescope migrations will be run.
     */
    public static bool $runsMigrations = true;

    /**
     * The callbacks that add tags to the record.
     */
    public static array $tagUsing = [];

    /**
     * The callbacks that filter the entries that should be recorded.
     */
    public static array $filterUsing = [];

    public static function recordCache(IncomingEntry $entry): void
    {
        static::record(EntryType::CACHE, $entry);
    }

    public static function recordCommand(IncomingEntry $entry): void
    {
        static::record(EntryType::COMMAND, $entry);
    }

    public static function recordEvent(IncomingEntry $entry): void
    {
        static::record(EntryType::EVENT, $entry);
    }

    public static function recordSchedule(IncomingEntry $entry): void
    {
        static::record(EntryType::SCHEDULE, $entry);
    }

    public static function recordException(IncomingEntry $entry): void
    {
        static::record(EntryType::EXCEPTION, $entry);
    }

    public static function recordLog(IncomingEntry $entry): void
    {
        static::record(EntryType::LOG, $entry);
    }

    public static function recordQuery(IncomingEntry $entry): void
    {
        static::record(EntryType::QUERY, $entry);
    }

    public static function recordRedis(IncomingEntry $entry): void
    {
        static::record(EntryType::REDIS, $entry);
    }

    public static function recordRequest(IncomingEntry $entry): void
    {
        static::record(EntryType::REQUEST, $entry);
    }

    public static function recordService(IncomingEntry $entry): void
    {
        static::record(EntryType::SERVICE, $entry);
    }

    public static function recordClientRequest(IncomingEntry $entry): void
    {
        static::record(EntryType::CLIENT_REQUEST, $entry);
    }

    /**
     * @deprecated since v3.1, the method has been deprecated and its usage is discouraged, will be removed in v3.2
     */
    public static function getAppName(): string
    {
        $appName = static::getConfig()->getAppName();
        return $appName ? '[' . $appName . '] ' : '';
    }

    public static function getQuerySlow(): int
    {
        return static::getConfig()->getQuerySlow();
    }

    public static function getPath(): string
    {
        return static::getConfig()->getPath();
    }

    /**
     * Add a callback that adds tags to the record.
     */
    public static function tag(Closure $callback): static
    {
        static::$tagUsing[] = $callback;

        return new static();
    }

    /**
     * Set the callback that filters the entries that should be recorded.
     */
    public static function filter(Closure $callback): static
    {
        static::$filterUsing[] = $callback;

        return new static();
    }

    public static function getConfig(): TelescopeConfig
    {
        return ApplicationContext::getContainer()->get(TelescopeConfig::class);
    }

    public static function getStorage(): EntriesRepository
    {
        return ApplicationContext::getContainer()->get(EntriesRepository::class);
    }

    /**
     * Get the default JavaScript variables for Telescope.
     */
    public static function scriptVariables(): array
    {
        return [
            'path' => trim(static::getConfig()->getPath(), '/'),
            'timezone' => static::getConfig()->getTimezone(),
            'recording' => static::getConfig()->isRecording(),
        ];
    }

    public static function store(IncomingEntry $entry): void
    {
        if (static::getConfig()->getRecordMode() === RecordMode::SYNC) {
            self::getStorage()->store(collect([$entry]));

            return;
        }

        if (! Context::has(self::ENTRIES)) {
            Context::set(self::ENTRIES, []);
            defer(function () {
                self::getStorage()->store(
                    collect(Context::get(self::ENTRIES))
                );
                Context::destroy(self::ENTRIES);
            });
        }

        Context::override(self::ENTRIES, function ($entries) use ($entry) {
            $entries = (array) $entries;
            $entries[] = $entry;

            return $entries;
        });
    }

    /**
     * Determine if the given entry should be recorded.
     */
    protected static function shouldRecord(IncomingEntry $entry): bool
    {
        foreach (static::$filterUsing as $callback) {
            if (! $callback($entry)) {
                return false;
            }
        }
        return true;
    }

    protected static function record(string $type, IncomingEntry $entry): void
    {
        if (! $batchId = TelescopeContext::getBatchId()) {
            return;
        }

        $subBatchId = (string) TelescopeContext::getSubBatchId();
        $entry->batchId($batchId)->subBatchId($subBatchId)->type($type)->user();

        if (! static::shouldRecord($entry)) {
            return;
        }

        $entry->tags(Arr::collapse(array_map(fn ($tagCallback) => $tagCallback($entry), static::$tagUsing)));

        static::store($entry);
    }
}
