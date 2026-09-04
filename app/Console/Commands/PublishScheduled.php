<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Models\JobRun;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PublishScheduled extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publish posts whose schedule time has passed';

    public function handle(): int
    {
        JobRun::track('posts:publish-scheduled', function () {
            $due = Post::where('status', 'scheduled')->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())->get();
            foreach ($due as $post) {
                $post->update(['status' => 'published', 'published_at' => $post->scheduled_at]);
                AdminNotification::announce('scheduled_published', 'Scheduled content published: '.$post->title, null, 'success', $post->url);
                $this->info('Published: '.$post->title);
            }
            Cache::forget('sitemap.xml');
            return 'Published '.$due->count().' post(s)';
        });
        return self::SUCCESS;
    }
}
