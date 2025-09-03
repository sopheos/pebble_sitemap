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
    private array $mapPaths = [];

    private ?GzWriter $writer = null;
    private int $nbUrls = 0;

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
        $url = ltrim($url, '/');

        if (isset($this->urls[$url])) {
            return;
        }

        if ($this->nbUrls === 0 || $this->nbUrls >= $this->limit) {
            $this->open();
        }

        $this->urls[$url] = true;
        $this->nbUrls++;

        $this->writer->write(self::tplUrl([
            'url'         => $this->baseUrl . $url,
            'priority'    => $priority,
            'frequency'   => $frequency,
            'lastmod'     => date('Y-m-d\TH:i:sP', $lastmod ?? time()),
            'deepLinking' => $deepLinking,
        ]));
    }

    /**
     * Generate sitemap index and clean
     */
    public function generate()
    {
        $this->close();

        // Map urls list
        $mapUrls = [];
        foreach (array_keys($this->mapPaths) as $i) {
            $mapUrls[] = $this->baseUrl . $this->mapName . $i . self::XML_EXT . self::COMPRESS_EXT;
        }

        // Clean previous map files
        foreach (glob($this->basePath . $this->mapName . '*') as $filename) {
            if (!in_array($filename, $this->mapPaths)) {
                unlink($filename);
            }
        }

        // Index
        $indexPath =  $this->basePath . $this->indexName . self::XML_EXT;
        file_put_contents($indexPath, self::tplIndex($mapUrls));
    }

    // -------------------------------------------------------------------------

    private static function tplUrl(array $item): string
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

        return $out . self::EOL;
    }

    private static function tplIndex(array $mapUrls)
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

    private function open()
    {
        if ($this->writer) {
            $this->close();
        }

        $this->writer = new GzWriter($this->basePath . uniqid() . '.tmp');
        $this->writer->open();

        $this->writer->write(
            '<?xml version="1.0" encoding="UTF-8"?>'
                . self::EOL
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'
                . self::EOL
        );
    }

    private function close()
    {
        if ($this->writer) {
            $this->writer->write('</urlset>');
            $this->writer->close();

            $mapPath = $this->basePath . $this->mapName . count($this->mapPaths) . self::XML_EXT . self::COMPRESS_EXT;
            rename($this->writer->filename(), $mapPath);

            $this->mapPaths[] = $mapPath;
            $this->writer = null;
            $this->nbUrls = 0;
        }
    }

    // -------------------------------------------------------------------------
}
