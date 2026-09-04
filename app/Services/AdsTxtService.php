<?php

namespace App\Services;

/**
 * ads.txt management (IAB spec). Served dynamically at /ads.txt from settings.
 */
class AdsTxtService
{
    public function content(): string
    {
        $c = (string) setting('adsense.ads_txt', '');
        if (trim($c) === '' && ($pub = setting('adsense.publisher_id'))) {
            $c = $this->googleLine($pub);
        }
        if (trim($c) === '') {
            // Valid, empty ads.txt (comments only) until a publisher id is configured in the admin panel.
            $c = '# ads.txt — no authorised sellers configured yet.';
        }
        return rtrim($c)."\n";
    }

    public function googleLine(string $publisherId): string
    {
        $pub = $this->normalizePublisherId($publisherId);
        return "google.com, {$pub}, DIRECT, f08c47fec0942fa0";
    }

    public function normalizePublisherId(string $id): string
    {
        $id = trim($id);
        if (preg_match('/^ca-(pub-\d+)$/i', $id, $m)) {
            return strtolower($m[1]);
        }
        if (preg_match('/^\d{10,}$/', $id)) {
            return 'pub-'.$id;
        }
        return strtolower($id);
    }

    /**
     * Validate ads.txt content.
     *
     * @return array{errors: string[], warnings: string[], lines: int, records: int}
     */
    public function validate(string $content, ?string $publisherId = null): array
    {
        $errors = [];
        $warnings = [];
        $records = 0;
        $hasGoogle = false;
        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach ($lines as $i => $raw) {
            $n = $i + 1;
            $line = trim(preg_replace('/#.*$/', '', $raw));
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(contact|subdomain|inventorypartnerdomain|ownerdomain|managerdomain)\s*=/i', $line)) {
                continue; // variable line
            }
            $parts = array_map('trim', explode(',', $line));
            if (count($parts) < 3) {
                $errors[] = "Line {$n}: expected at least 3 comma-separated fields (domain, publisher id, relationship).";
                continue;
            }
            [$domain, $pub, $rel] = $parts;
            if (! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
                $errors[] = "Line {$n}: '{$domain}' is not a valid domain.";
            }
            if ($pub === '') {
                $errors[] = "Line {$n}: publisher account id is empty.";
            }
            if (! in_array(strtoupper($rel), ['DIRECT', 'RESELLER'], true)) {
                $errors[] = "Line {$n}: relationship must be DIRECT or RESELLER (found '{$rel}').";
            }
            if (isset($parts[3]) && $parts[3] !== '' && ! preg_match('/^[a-f0-9]{8,32}$/i', $parts[3])) {
                $warnings[] = "Line {$n}: certification authority id '{$parts[3]}' looks unusual.";
            }
            if (strtolower($domain) === 'google.com') {
                $hasGoogle = true;
                if ($publisherId && strtolower($pub) !== $this->normalizePublisherId($publisherId)) {
                    $warnings[] = "Line {$n}: google.com publisher id '{$pub}' does not match the configured AdSense publisher id.";
                }
                if (! isset($parts[3]) || strtolower($parts[3]) !== 'f08c47fec0942fa0') {
                    $warnings[] = "Line {$n}: Google lines normally end with the certification id f08c47fec0942fa0.";
                }
            }
            $records++;
        }
        if ($publisherId && ! $hasGoogle) {
            $warnings[] = 'No google.com entry found. AdSense expects: '.$this->googleLine($publisherId);
        }
        return ['errors' => $errors, 'warnings' => $warnings, 'lines' => count($lines), 'records' => $records];
    }
}
