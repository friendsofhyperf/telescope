<?php

declare(strict_types=1);
/**
 * This file is part of friendsofhyperf/components.
 *
 * @link     https://github.com/friendsofhyperf/components
 * @document https://github.com/friendsofhyperf/components/blob/main/README.md
 * @contact  huangdijia@gmail.com
 */

namespace FriendsOfHyperf\Telescope\Controller;

use FriendsOfHyperf\Telescope\EntryType;
use FriendsOfHyperf\Telescope\Model\TelescopeEntryModel;
use FriendsOfHyperf\Telescope\Model\TelescopeEntryTagModel;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Stringable\Str;
use Psr\Container\ContainerInterface;

abstract class EntryController
{
    #[Inject]
    protected ContainerInterface $container;

    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected ResponseInterface $response;

    public function index()
    {
        $before = $this->request->input('before');
        $limit = $this->request->input('take', 50);
        $tag = $this->request->input('tag');
        $query = TelescopeEntryModel::query()
            ->with('tags')
            ->where('type', $this->entryType())
            ->orderByDesc('sequence');

        if ($before) {
            $query->where('sequence', '<', $before);
        }

        if ($tag) {
            $query->join('telescope_entries_tags', 'telescope_entries_tags.entry_uuid', '=', 'telescope_entries.uuid')->where('tag', $tag);
        }

        $entries = $query->limit($limit)->get()->toArray();

        foreach ($entries as &$item) {
            if (isset($item['content']['response'])) {
                $item['content']['response'] = '';
            }
            $item['tag'] = $item['tag_value'] = '';
            foreach ($item['tags'] as $key => $val) {
                if (Str::startsWith($val['tag'], 'app_name:')) {
                    $item['tag_value'] = $val['tag'];
                    $item['tag'] = Str::substr($val['tag'], strlen('app_name:'));
                }
            }
        }

        return $this->response->json([
            'entries' => $entries,
            'status' => $this->status(),
        ]);
    }

    public function show($id)
    {
        $entry = TelescopeEntryModel::find($id);
        $entry->tags = TelescopeEntryTagModel::where('entry_uuid', $id)->pluck('tag')->toArray();

        $query = TelescopeEntryModel::where('batch_id', $entry->batch_id);
        if ($this->entryType() == EntryType::SERVICE) {
            $query->where('sub_batch_id', $entry->sub_batch_id);
        }

        $batch = $query->with('tags')->orderByDesc('sequence')->get();
        foreach ($batch as &$item) {
            $item['tag'] = $item['tag_value'] = '';
            foreach ($item['tags'] as $key => $val) {
                if (Str::startsWith($val['tag'], 'app_name:')) {
                    $item['tag_value'] = $val['tag'];
                    $item['tag'] = Str::substr($val['tag'], strlen('app_name:'));
                }
            }
        }

        return $this->response->json([
            'entry' => $entry,
            'batch' => $batch,
        ]);
    }

    /**
     * The entry type for the controller.
     *
     * @return string
     */
    abstract protected function entryType();

    /**
     * The watcher class for the controller.
     *
     * @return string
     */
    abstract protected function watcher();

    /**
     * Determine the watcher recording status.
     *
     * @return string
     */
    protected function status()
    {
        // if (! config('telescope.enabled', false)) {
        //     return 'disabled';
        // }

        // if (cache('telescope:pause-recording', false)) {
        //     return 'paused';
        // }

        // $watcher = config('telescope.watchers.'.$this->watcher());

        // if (! $watcher || (isset($watcher['enabled']) && ! $watcher['enabled'])) {
        //     return 'off';
        // }

        return 'enabled';
    }
}
