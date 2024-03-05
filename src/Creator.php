<?php

namespace Pebble\Sitemap;

/**
 * Create sitemap files
 */
class Creator
{
    const EOL          = "\n";
    const COMPRESS_EXT = '.gz';
    const XML_EXT      = '.xml';

    const ALWAYS  = 'always';
    const HOURLY  = 'hourly';
    const DAILY   = 'daily';
    const WEEKLY  = 'weekly';
    const MONTHLY = 'monthly';
    const YEARLY  = 'yearly';
    const NEVER   = 'never';

    private string $basePath;
    private string $baseUrl;
    private string $indexName;
    private string $mapName;
    private int $limit;
    private array $urls = [];

    // -------------------------------------------------------------------------

    public function __construct(
        string $basePath,
        string $baseUrl,
        string $indexName = 'idx',
        string $mapName = 'map',
        int $limit = 50000
    ) {
        $this->basePath   = rtrim($basePath, '/') . '/';
        $this->baseUrl    = rtrim($baseUrl, '/') . '/';
        $this->indexName  = $indexName;
        $this->mapName    = $mapName;
        $this->limit      = $limit;
    }

    // -------------------------------------------------------------------------

    /**
     * Add an url to the sitemap
     *
     * @param string $url
     * @param float $priority
     * @param string $frequency always, hourly, daily, weekly, monthly, yearly, never
     * @param int|null $lastmod
     * @param string $deepLinking android deep linking url
     */
    public function add(string $url, float $priority = 0.5, string $frequency = self::MONTHLY, ?int $lastmod = null, ?string $deepLinking = null)
    {
        $url      = ltrim($url, '/');
        $prevprio = $this->urls[$url]['priority'] ?? null;
        $prevmod  = $this->urls[$url]['lastmod'] ?? null;
        $lastmod  = date('Y-m-d\TH:i:sP', $lastmod ?? time());

        $this->urls[$url] = [
            'url'         => $this->baseUrl . $url,
            'priority'    => self::value($prevprio, $priority),
            'frequency'   => $frequency,
            'lastmod'     => self::value($prevmod, $lastmod),
            'deepLinking' => $deepLinking,
        ];
    }

    /**
     * Generate sitemaps, compress and sent to search engine
     */
    public function generate()
    {
        ksort($this->urls);

        // Map files
        $mapPaths = [];
        $mapUrls = [];
        foreach (array_chunk($this->urls, $this->limit) as $i => $urls) {
            $mapPath = $this->basePath . $this->mapName . $i . self::XML_EXT . self::COMPRESS_EXT;
            $mapUrl = $this->baseUrl . $this->mapName . $i . self::XML_EXT . self::COMPRESS_EXT;
            $mapPaths[] = $mapPath;
            $mapUrls[] = $mapUrl;

            $this->writegz($mapPath, $this->tplMap($urls));
        }

        // Clean previous map files
        foreach (glob($this->basePath . $this->mapName . '*') as $filename) {
            if (!in_array($filename, $mapPaths)) {
                unlink($filename);
            }
        }

        // Index
        $indexPath =  $this->basePath . $this->indexName . self::XML_EXT;
        file_put_contents($indexPath, $this->tplIndex($mapUrls));
    }

    // -------------------------------------------------------------------------

    private static function value(mixed $prev, mixed $value): mixed
    {
        return $prev === null || $value > $prev ? $value : $prev;
    }

    private function tplMap(array $urls): string
    {
        $urlContent = '';
        foreach ($urls as $url) {
            $urlContent .= $this->tplUrl($url) . self::EOL;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . self::EOL
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . self::EOL
            . $urlContent
            . '</urlset>';
    }

    private function tplUrl(array $item): string
    {
        // Build a new entry
        $out = '<url>';
        $out .= '<loc>' . $item['url'] . '</loc>';

        // Android link for deep linking
        if ($item['deepLinking']) {
            $out .= '<xhtml:link rel="alternate" href="android-app://' . $item['deepLinking'] . '" />';
        }

        $out .= '<changefreq>' . $item['frequency'] . '</changefreq>';
        $out .= '<priority>' . $item['priority'] . '</priority>';

        if ($item['lastmod']) {
            $out .= '<lastmod>' . $item['lastmod'] . '</lastmod>';
        }

        $out .= "</url>";

        return $out;
    }

    private function tplIndex(array $mapUrls)
    {
        $lastMod = date('Y-m-d\TH:i:sP');

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . self::EOL;
        $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . self::EOL;

        foreach ($mapUrls as $mapUrl) {
            $out .= '<sitemap>';
            $out .= '<loc>' . $mapUrl . '</loc>';
            $out .= '<lastmod>' . $lastMod . '</lastmod>';
            $out .=  '</sitemap>' . self::EOL;
        }

        $out .= "</sitemapindex>";

        return $out;
    }

    private function writegz(string $file, string $content)
    {
        $gz = gzopen($file, 'w');
        gzwrite($gz, $content);
        gzclose($gz);
    }

    // -------------------------------------------------------------------------
}
