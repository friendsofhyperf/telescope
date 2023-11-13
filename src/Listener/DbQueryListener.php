<?php

declare(strict_types=1);
/**
 * This file is part of friendsofhyperf/components.
 *
 * @link     https://github.com/friendsofhyperf/components
 * @document https://github.com/friendsofhyperf/components/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */

namespace FriendsOfHyperf\Telescope\Listener;

use FriendsOfHyperf\Telescope\IncomingEntry;
use FriendsOfHyperf\Telescope\SwitchManager;
use FriendsOfHyperf\Telescope\Telescope;
use Hyperf\Collection\Arr;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Stringable\Str;

class DbQueryListener implements ListenerInterface
{
    public function __construct(private SwitchManager $switchManager)
    {
    }

    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    /**
     * @param QueryExecuted $event
     */
    public function process(object $event): void
    {
        if ($this->switchManager->isEnable('db') === false) {
            return;
        }
        if ($event instanceof QueryExecuted) {
            $sql = $event->sql;
            if (! Arr::isAssoc($event->bindings)) {
                foreach ($event->bindings as $key => $value) {
                    $sql = Str::replaceFirst('?', "'{$value}'", $sql);
                }
            }
            if (Str::contains($sql, 'telescope')) {
                return;
            }
            $optionSlow = Telescope::getQuerySlow();
            Telescope::recordQuery(IncomingEntry::make([
                'connection' => $event->connectionName,
                'bindings' => [],
                'sql' => Telescope::getAppName() . $sql,
                'time' => number_format($event->time, 2, '.', ''),
                'slow' => $event->time >= $optionSlow,
                'hash' => md5($sql),
            ]));
        }
    }
}
