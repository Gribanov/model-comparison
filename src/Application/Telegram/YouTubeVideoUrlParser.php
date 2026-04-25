<?php

namespace App\Application\Telegram;

final class YouTubeVideoUrlParser
{
    private const ALLOWED_HOSTS = ['youtube.com', 'www.youtube.com', 'youtu.be'];
    private const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';
    private const TRAILING_PUNCTUATION = ".,!?)\]}'\"";

    public function parse(string $input): ?YouTubeVideoUrl
    {
        $candidateUrl = $this->extractCandidateUrl($input);

        if ($candidateUrl === null) {
            return null;
        }

        $parts = parse_url($candidateUrl);

        if (!is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($host, self::ALLOWED_HOSTS, true)) {
            return null;
        }

        if ($host === 'youtu.be') {
            $videoId = $this->extractShortUrlVideoId($parts);

            if ($videoId === null) {
                return null;
            }

            return new YouTubeVideoUrl($videoId, sprintf('https://www.youtube.com/watch?v=%s', $videoId));
        }

        return $this->parseYouTubeDomainUrl($parts);
    }

    private function extractCandidateUrl(string $input): ?string
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('~(?:https?://|www\.)[^\s<>"\']+~i', $trimmed, $matches) === 1) {
            return $this->stripTrailingPunctuation($matches[0]);
        }

        if (preg_match('~(?:youtube\.com|youtu\.be)/[^\s<>"\']+~i', $trimmed, $matches) === 1) {
            return 'https://'.$this->stripTrailingPunctuation($matches[0]);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function extractShortUrlVideoId(array $parts): ?string
    {
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($path === '' || str_contains($path, '/')) {
            return null;
        }

        if (!preg_match(self::VIDEO_ID_PATTERN, $path)) {
            return null;
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $parts
     */
    private function parseYouTubeDomainUrl(array $parts): ?YouTubeVideoUrl
    {
        $path = (string) ($parts['path'] ?? '');

        if ($path === '/shorts' || str_starts_with($path, '/shorts/')) {
            return null;
        }

        if ($path === '/playlist' || str_starts_with($path, '/playlist/')) {
            return null;
        }

        if ($path === '/channel' || str_starts_with($path, '/channel/')) {
            return null;
        }

        if ($path === '/@' || str_starts_with($path, '/@')) {
            return null;
        }

        if ($path !== '/watch') {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);

        $videoId = is_string($query['v'] ?? null) ? $query['v'] : null;

        if ($videoId === null || !preg_match(self::VIDEO_ID_PATTERN, $videoId)) {
            return null;
        }

        return new YouTubeVideoUrl($videoId, sprintf('https://www.youtube.com/watch?v=%s', $videoId));
    }

    private function stripTrailingPunctuation(string $value): string
    {
        return rtrim($value, self::TRAILING_PUNCTUATION);
    }
}
