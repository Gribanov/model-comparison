<?php

namespace App\Tests\Unit\Application\Telegram;

use App\Application\Telegram\YouTubeVideoUrlParser;
use PHPUnit\Framework\TestCase;

final class YouTubeVideoUrlParserTest extends TestCase
{
    public function testParsesYoutubeWatchUrl(): void
    {
        $parser = new YouTubeVideoUrlParser();

        $result = $parser->parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        self::assertNotNull($result);
        self::assertSame('dQw4w9WgXcQ', $result->videoId);
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $result->canonicalUrl);
    }

    public function testParsesShortUrlAndNormalizesIt(): void
    {
        $parser = new YouTubeVideoUrlParser();

        $result = $parser->parse('https://youtu.be/dQw4w9WgXcQ?t=42');

        self::assertNotNull($result);
        self::assertSame('dQw4w9WgXcQ', $result->videoId);
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $result->canonicalUrl);
    }

    public function testParsesExtractedYoutubeWatchUrlWithoutScheme(): void
    {
        $parser = new YouTubeVideoUrlParser();

        $result = $parser->parse('youtube.com/watch?v=dQw4w9WgXcQ');

        self::assertNotNull($result);
        self::assertSame('dQw4w9WgXcQ', $result->videoId);
    }

    public function testExtractsUrlFromRawText(): void
    {
        $parser = new YouTubeVideoUrlParser();

        $result = $parser->parse('please download this https://www.youtube.com/watch?v=dQw4w9WgXcQ now');

        self::assertNotNull($result);
        self::assertSame('dQw4w9WgXcQ', $result->videoId);
    }

    public function testRejectsShortsUrl(): void
    {
        $parser = new YouTubeVideoUrlParser();

        self::assertNull($parser->parse('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
    }

    public function testRejectsPlaylistUrl(): void
    {
        $parser = new YouTubeVideoUrlParser();

        self::assertNull($parser->parse('https://www.youtube.com/playlist?list=PL1234567890'));
    }

    public function testRejectsNonYoutubeUrl(): void
    {
        $parser = new YouTubeVideoUrlParser();

        self::assertNull($parser->parse('https://example.com/watch?v=dQw4w9WgXcQ'));
    }
}
