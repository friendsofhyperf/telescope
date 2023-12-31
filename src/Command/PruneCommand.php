<?php

declare(strict_types=1);
/**
 * This file is part of friendsofhyperf/components.
 *
 * @link     https://github.com/friendsofhyperf/components
 * @document https://github.com/friendsofhyperf/components/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */

namespace FriendsOfHyperf\Telescope\Command;

use Carbon\Carbon;
use FriendsOfHyperf\Telescope\Telescope;
use Hyperf\Command\Command;
use Hyperf\DbConnection\Db;

class PruneCommand extends Command
{
    protected ?string $signature = 'telescope:prune {--hours=24 : The number of hours to retain Telescope data}';

    public function handle()
    {
        $connection = Telescope::getConfig()->getDatabaseConnection();
        $created_at = Carbon::now()->subHours($this->input->getOption('hours'));
        Db::connection($connection)->table('telescope_entries')
            ->where('created_at', '<', $created_at)
            ->delete();
        Db::connection($connection)
            ->table('telescope_monitoring')
            ->delete();
    }
}
