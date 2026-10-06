<?php

use Pebble\Sitemap\Creator;
use PHPUnit\Framework\TestCase;

class CreatorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pebble_sitemap_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    private function creator(string $indexName = 'idx', string $mapName = 'map', int $limit = 50000): Creator
    {
        return new Creator($this->dir, 'https://example.com', $indexName, $mapName, $limit);
    }

    private function files(): array
    {
        return array_values(array_diff(scandir($this->dir), ['.', '..']));
    }

    private function mapXml(int $i, string $mapName = 'map'): string
    {
        return gzdecode(file_get_contents($this->dir . '/' . $mapName . $i . '.xml.gz'));
    }

    private function map(int $i, string $mapName = 'map'): SimpleXMLElement
    {
        return simplexml_load_string($this->mapXml($i, $mapName));
    }

    private function index(string $indexName = 'idx'): SimpleXMLElement
    {
        return simplexml_load_file($this->dir . '/' . $indexName . '.xml');
    }

    // -------------------------------------------------------------------------
    // Files
    // -------------------------------------------------------------------------

    public function testGenerateWritesGzippedMapsAndAPlainIndex()
    {
        $creator = $this->creator();
        $creator->add('/a');
        $creator->generate();

        self::assertSame(['idx.xml', 'map0.xml.gz'], $this->files());

        $index = $this->index();
        self::assertSame('sitemapindex', $index->getName());
        self::assertSame('https://example.com/map0.xml.gz', (string) $index->sitemap[0]->loc);
    }

    public function testLimitSplitsUrlsIntoSeveralMaps()
    {
        $creator = $this->creator('idx', 'map', 2);
        foreach (['a', 'b', 'c'] as $url) {
            $creator->add($url);
        }
        $creator->generate();

        self::assertSame(['idx.xml', 'map0.xml.gz', 'map1.xml.gz'], $this->files());
        self::assertCount(2, $this->map(0)->url);
        self::assertCount(1, $this->map(1)->url);
        self::assertCount(2, $this->index()->sitemap);
    }

    public function testIndexAndMapNamesMayContainASubdirectory()
    {
        mkdir($this->dir . '/sitemaps');

        $creator = $this->creator('sitemaps/idx', 'sitemaps/map');
        $creator->add('/page');
        $creator->generate();

        self::assertSame(['idx.xml', 'map0.xml.gz'], array_map('basename', glob($this->dir . '/sitemaps/*')));
        self::assertSame('https://example.com/page', (string) $this->map(0, 'sitemaps/map')->url[0]->loc);
        self::assertSame('https://example.com/sitemaps/map0.xml.gz', (string) $this->index('sitemaps/idx')->sitemap[0]->loc);

        unlink($this->dir . '/sitemaps/idx.xml');
        unlink($this->dir . '/sitemaps/map0.xml.gz');
        rmdir($this->dir . '/sitemaps');
    }

    public function testCurrentMapStaysATmpFileUntilGenerate()
    {
        $creator = $this->creator();
        $creator->add('a');

        $files = $this->files();
        self::assertCount(1, $files);
        self::assertStringEndsWith('.tmp', $files[0]);

        $creator->generate();
        self::assertSame(['idx.xml', 'map0.xml.gz'], $this->files());
    }

    public function testGenerateWithoutUrlsWritesAnEmptyIndex()
    {
        $this->creator()->generate();

        self::assertSame(['idx.xml'], $this->files());
        self::assertCount(0, $this->index()->sitemap);
    }

    public function testGenerateDeletesStaleMapsOfAPreviousRun()
    {
        $first = $this->creator('idx', 'map', 1);
        $first->add('a');
        $first->add('b');
        $first->generate();
        self::assertSame(['idx.xml', 'map0.xml.gz', 'map1.xml.gz'], $this->files());

        $second = $this->creator('idx', 'map', 1);
        $second->add('a');
        $second->generate();
        self::assertSame(['idx.xml', 'map0.xml.gz'], $this->files());
    }

    public function testAddAfterGenerateContinuesTheNumbering()
    {
        $creator = $this->creator();
        $creator->add('a');
        $creator->generate();
        $creator->add('b');
        $creator->generate();

        self::assertSame(['idx.xml', 'map0.xml.gz', 'map1.xml.gz'], $this->files());
        self::assertCount(2, $this->index()->sitemap);
    }

    // -------------------------------------------------------------------------
    // Entries
    // -------------------------------------------------------------------------

    public function testAddWritesDefaults()
    {
        $before = time();
        $creator = $this->creator();
        $creator->add('/page');
        $creator->generate();

        $url = $this->map(0)->url[0];
        self::assertSame('https://example.com/page', (string) $url->loc);
        self::assertSame('monthly', (string) $url->changefreq);
        self::assertSame('0.5', (string) $url->priority);
        self::assertGreaterThanOrEqual($before, strtotime((string) $url->lastmod));
    }

    public function testUrlIsAlwaysPrefixedWithBaseUrl()
    {
        $creator = $this->creator();
        $creator->add('https://other.com/x');
        $creator->generate();

        self::assertSame('https://example.com/https://other.com/x', (string) $this->map(0)->url[0]->loc);
    }

    public function testLastmodIsAW3cDateInTheDefaultTimezone()
    {
        $creator = $this->creator();
        $creator->add('a', 0.5, Creator::DAILY, 1700000000);
        $creator->add('b', 0.5, Creator::DAILY, 0);
        $creator->generate();

        self::assertSame('2023-11-14T23:13:20+01:00', (string) $this->map(0)->url[0]->lastmod);
        self::assertSame('1970-01-01T01:00:00+01:00', (string) $this->map(0)->url[1]->lastmod);
    }

    public function testPriorityAndFrequencyAreNotValidated()
    {
        $creator = $this->creator();
        $creator->add('a', 1.0, 'sometimes');
        $creator->add('b', 7.25);
        $creator->generate();

        self::assertSame('1', (string) $this->map(0)->url[0]->priority);
        self::assertSame('sometimes', (string) $this->map(0)->url[0]->changefreq);
        self::assertSame('7.25', (string) $this->map(0)->url[1]->priority);
    }

    public function testDeepLinkingAddsAnAndroidAlternateLink()
    {
        $creator = $this->creator();
        $creator->add('a', 0.5, Creator::MONTHLY, null, 'com.example/http/example.com/a');
        $creator->generate();

        $link = $this->map(0)->url[0]->children('http://www.w3.org/1999/xhtml')->link;
        self::assertSame('alternate', (string) $link->attributes()->rel);
        self::assertSame('android-app://com.example/http/example.com/a', (string) $link->attributes()->href);
    }

    // -------------------------------------------------------------------------
    // Known bugs (see TODO.md)
    // -------------------------------------------------------------------------

    public function testUrlsAreNotXmlEscaped()
    {
        // BUG: <loc> and the xhtml:link href are concatenated without
        // htmlspecialchars(), so a '&' produces a map that is not valid XML.
        $creator = $this->creator();
        $creator->add('search?a=1&b=2');
        $creator->generate();

        $xml = $this->mapXml(0);
        self::assertStringContainsString('<loc>https://example.com/search?a=1&b=2</loc>', $xml);
        self::assertFalse(@simplexml_load_string($xml));
    }

    public function testGenerateDeletesEveryFileStartingWithMapName()
    {
        // BUG: the cleanup globs mapName . '*', so unrelated files sharing the
        // prefix are deleted too.
        touch($this->dir . '/mapping.json');
        touch($this->dir . '/map.txt');

        $creator = $this->creator();
        $creator->add('a');
        $creator->generate();

        self::assertSame(['idx.xml', 'map0.xml.gz'], $this->files());
    }

    public function testAddInAMissingDirectoryThrowsATypeError()
    {
        // BUG: GzWriter::open() swallows the gzopen() failure (warning only) and
        // write() then calls gzwrite(null).
        $creator = new Creator($this->dir . '/missing', 'https://example.com');

        $this->expectException(TypeError::class);
        @$creator->add('a');
    }
}
