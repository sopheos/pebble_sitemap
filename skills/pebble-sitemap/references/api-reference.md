# pebble-sitemap — API cheat sheet

Quick lookup by intent. This is not exhaustive. Read the source in `vendor/sopheos/pebble_sitemap/src/` for exact signatures and for edge cases not covered here.

## Creator (`Pebble\Sitemap\Creator`)

`new Creator(string $basePath, string $baseUrl, string $indexName = 'idx', string $mapName = 'map', int $limit = 50000)`

| Argument | Meaning |
| -------- | ------- |
| `$basePath` | Output directory (trailing `/` normalized). Must exist. Temporary `.tmp` files are written here |
| `$baseUrl` | Public URL of `$basePath` (trailing `/` normalized). Prefixes page URLs **and** map URLs in the index |
| `$indexName` | Index file name without extension; written as `$basePath . $indexName . '.xml'`. May contain a subdirectory |
| `$mapName` | Map file prefix; maps are `$basePath . $mapName . $i . '.xml.gz'`, `$i` from 0. May contain a subdirectory |
| `$limit` | Maximum number of URLs per map file |

| Intent | Method |
| ------ | ------ |
| Add one URL to the current map (opens a new map when full) | `add(string $url, float $priority = 0.5, string $frequency = self::MONTHLY, ?int $lastmod = null, ?string $deepLinking = null)` |
| Close the current map, delete other `$mapName*` files, write the index | `generate()` |

Neither method returns anything.

- `$url`: path relative to `$baseUrl`; `ltrim($url, '/')` then `$baseUrl . $url`. Not escaped.
- `$priority`: written with PHP's float-to-string conversion (`1.0` -> `1`, `0.5` -> `0.5`). Not validated.
- `$frequency`: one of the constants below. Not validated.
- `$lastmod`: Unix timestamp, `null` -> `time()`. Written as `date('Y-m-d\TH:i:sP', $lastmod)` in the default timezone.
- `$deepLinking`: Android app URI without the scheme; adds `<xhtml:link rel="alternate" href="android-app://{$deepLinking}" />`. Not escaped.

Frequency constants: `ALWAYS`, `HOURLY`, `DAILY`, `WEEKLY`, `MONTHLY`, `YEARLY`, `NEVER` (lower-case strings). Other constants: `EOL = "\n"`, `COMPRESS_EXT = '.gz'`, `XML_EXT = '.xml'`.

### Output

Map file (gzipped), one `<url>` per line:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
<url><loc>https://example.com/page</loc><changefreq>monthly</changefreq><priority>0.5</priority><lastmod>2026-10-06T14:00:00+02:00</lastmod></url>
</urlset>
```

Index file (plain XML), `lastmod` = generation time for every map:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<sitemap><loc>https://example.com/map0.xml.gz</loc><lastmod>2026-10-06T14:00:00+02:00</lastmod></sitemap>
</sitemapindex>
```

## GzWriter (`Pebble\Sitemap\GzWriter`)

| Intent | Method |
| ------ | ------ |
| Build (does not open) | `new GzWriter(string $filename)` |
| Path given to the constructor | `filename(): string` |
| Open with `gzopen($filename, 'w')`; no-op if already open; failure leaves it closed with a warning | `open()` |
| Whether a stream is held | `isOpen(): bool` |
| `gzwrite()` to the stream; `TypeError` if not open | `write(string $content)` |
| `gzclose()`; no-op if already closed | `close()` |
