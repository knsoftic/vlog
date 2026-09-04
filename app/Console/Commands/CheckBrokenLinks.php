<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Models\BrokenLink;
use App\Models\JobRun;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckBrokenLinks extends Command
{
    protected $signature = 'links:check {--limit=50 : Posts to check per run}';

    protected $description = 'Scan published content for broken outbound / internal links';

    public function handle(): int
    {
        JobRun::track('links:check', function () {
            $limit = (int) $this->option('limit');
            $posts = Post::published()->orderBy('updated_at')->limit($limit)->get();
            $checked = 0;
            $broken = 0;
            $cache = [];
            foreach ($posts as $post) {
                preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', (string) $post->content, $m);
                $urls = array_unique(array_filter($m[1] ?? [], fn ($u) => preg_match('~^https?://~i', $u)));
                BrokenLink::where('post_id', $post->id)->where('is_resolved', false)->delete();
                foreach (array_slice($urls, 0, 40) as $url) {
                    $checked++;
                    if (! isset($cache[$url])) {
                        $cache[$url] = $this->probe($url);
                    }
                    [$code, $err] = $cache[$url];
                    if ($code === null || $code >= 400) {
                        $broken++;
                        BrokenLink::create(['post_id' => $post->id, 'source_url' => $post->url, 'target_url' => mb_substr($url, 0, 1000), 'status_code' => $code, 'error' => $err, 'checked_at' => now()]);
                    }
                }
                $post->timestamps = false;
                $post->update(['updated_at' => $post->updated_at]); // no-op keeps order stable
            }
            if ($broken > 0) {
                AdminNotification::announce('broken_page', "{$broken} broken link(s) detected", 'Review them under SEO → Broken Links.', 'warning', route('admin.seo.broken-links'));
            }
            $msg = "Checked {$checked} links in {$posts->count()} posts, {$broken} broken";
            $this->info($msg);
            return $msg;
        });
        return self::SUCCESS;
    }

    /** @return array{0:?int,1:?string} */
    protected function probe(string $url): array
    {
        try {
            $resp = Http::timeout(8)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; '.config('app.name').' LinkChecker)'])->head($url);
            $code = $resp->status();
            if ($code === 405 || $code === 403 || $code === 404) {
                $resp = Http::timeout(8)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; '.config('app.name').' LinkChecker)'])->get($url);
                $code = $resp->status();
            }
            return [$code, null];
        } catch (\Throwable $e) {
            return [null, mb_substr($e->getMessage(), 0, 255)];
        }
    }
}
