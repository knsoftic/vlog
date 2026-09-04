<?php

namespace App\Services;

/**
 * Lightweight, dependency-free user-agent parser (browser / OS / device type).
 */
class UserAgentParser
{
    protected string $ua;

    public function __construct(string $ua)
    {
        $this->ua = $ua;
    }

    public function deviceType(): string
    {
        $ua = $this->ua;
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle|Nexus (7|9|10)|SM-T|Tab/i', $ua) && ! preg_match('/Mobile Safari.*Android.*Mobile/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/Android(?!.*Mobile)/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone|BlackBerry|Opera Mini|IEMobile/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }

    public function browser(): string
    {
        return $this->browserInfo()[0];
    }

    public function browserVersion(): ?string
    {
        return $this->browserInfo()[1];
    }

    public function browserInfo(): array
    {
        $ua = $this->ua;
        $patterns = [
            ['Edge', '/Edg(?:e|A|iOS)?\/([\d.]+)/'],
            ['Opera', '/(?:OPR|Opera)\/([\d.]+)/'],
            ['Samsung Internet', '/SamsungBrowser\/([\d.]+)/'],
            ['UC Browser', '/UCBrowser\/([\d.]+)/'],
            ['Brave', '/Brave\/([\d.]+)/'],
            ['Vivaldi', '/Vivaldi\/([\d.]+)/'],
            ['Firefox', '/(?:Firefox|FxiOS)\/([\d.]+)/'],
            ['Chrome', '/(?:Chrome|CriOS)\/([\d.]+)/'],
            ['Safari', '/Version\/([\d.]+).*Safari/'],
            ['Internet Explorer', '/(?:MSIE |rv:)([\d.]+)/'],
        ];
        foreach ($patterns as [$name, $re]) {
            if (preg_match($re, $ua, $m)) {
                return [$name, mb_substr($m[1], 0, 30)];
            }
        }
        if (stripos($ua, 'Safari') !== false) {
            return ['Safari', null];
        }
        return ['Other', null];
    }

    public function os(): string
    {
        return $this->osInfo()[0];
    }

    public function osVersion(): ?string
    {
        return $this->osInfo()[1];
    }

    public function osInfo(): array
    {
        $ua = $this->ua;
        if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
            $map = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
            return ['Windows', $map[$m[1]] ?? $m[1]];
        }
        if (preg_match('/Windows Phone ([\d.]+)/', $ua, $m)) {
            return ['Windows Phone', $m[1]];
        }
        if (preg_match('/Android ([\d.]+)/', $ua, $m)) {
            return ['Android', $m[1]];
        }
        if (preg_match('/(?:iPhone|iPad|iPod).*OS ([\d_]+)/', $ua, $m)) {
            return ['iOS', str_replace('_', '.', $m[1])];
        }
        if (preg_match('/Mac OS X ([\d_.]+)/', $ua, $m)) {
            return ['macOS', str_replace('_', '.', $m[1])];
        }
        if (stripos($ua, 'CrOS') !== false) {
            return ['Chrome OS', null];
        }
        if (stripos($ua, 'Linux') !== false) {
            return ['Linux', null];
        }
        return ['Other', null];
    }
}
