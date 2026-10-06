---
name: pebble-sitemap
description: How to correctly generate gzipped XML sitemaps and their sitemap index using the sopheos/pebble_sitemap PHP library (namespace Pebble\Sitemap — classes Creator and GzWriter). Use this whenever the project's composer.json requires sopheos/pebble_sitemap, code imports from Pebble\Sitemap\*, or you're asked to add, change or debug a sitemap.xml, a sitemap index, a SEO cron/command that lists the site's URLs, or Android deep links in a sitemap in a PHP project that has this library available — even if the request is phrased generically like "generate the sitemap for Google" or "add the product pages to the sitemap" without naming the library. Also check this before hand-writing sitemap XML with XMLWriter or string concatenation in such a project, since this library replaces that and has non-obvious and in places broken behavior (URLs are not XML-escaped, generate() deletes every file starting with the map prefix, every URL is prefixed with the base URL, a missing output directory ends in a TypeError, the current map stays a .tmp file until generate()) that hand-rolled code would miss.
---

# pebble-sitemap

`sopheos/pebble_sitemap` is a tiny PHP 8.1+ sitemap writer. You construct a `Creator` with an output directory and the public URL of that directory, stream URLs into it with `add()`, then call `generate()` once. It writes gzipped `<urlset>` files (`map0.xml.gz`, `map1.xml.gz`, …, split every `$limit` URLs, 50 000 by default) and a plain `<sitemapindex>` file (`idx.xml`) pointing at them. Nothing is kept in memory. The library does **not** escape XML, validate priorities or frequencies, ping search engines, or write `robots.txt`.

Namespace: `Pebble\Sitemap\*`. Source lives in `vendor/sopheos/pebble_sitemap/src/`. Read it directly when you need an exact method signature; this skill focuses on *how the pieces fit together* and the behavior that isn't obvious from the method names.

## Orientation

- `Creator` is the only class you normally use: `new Creator($basePath, $baseUrl, $indexName = 'idx', $mapName = 'map', $limit = 50000)`, then `add()` N times, then `generate()`.
- `GzWriter` is the thin `gzopen()`/`gzwrite()`/`gzclose()` wrapper `Creator` uses for each map. You rarely touch it.
- Requires `ext-zlib`.

For a full method cheat sheet and the output format, see `references/api-reference.md`. For the complete list of easy-to-miss behaviors, see `references/gotchas.md`. Read it before debugging a sitemap that Google rejects.

## Core recipes

### Generate the sitemap from a cron or CLI command

```php
use Pebble\Sitemap\Creator;

$root = '/var/www/public';                    // document root, served at https://example.com/
if (!is_dir($root . '/sitemaps')) {
    mkdir($root . '/sitemaps', 0775, true);   // must exist before add()
}

$creator = new Creator($root, 'https://example.com', 'sitemaps/idx', 'sitemaps/map');

$creator->add('/', 1.0, Creator::DAILY);
foreach ($repository->iterateArticles() as $article) {
    $creator->add(
        '/article/' . rawurlencode($article->slug),
        0.8,
        Creator::WEEKLY,
        $article->updatedAt->getTimestamp()
    );
}

$creator->generate();
// -> public/sitemaps/map0.xml.gz, map1.xml.gz, …, public/sitemaps/idx.xml
```

Declare `https://example.com/sitemaps/idx.xml` in `robots.txt` (`Sitemap: …`) or in Search Console.

`$baseUrl` is used twice: page URLs are `$baseUrl . ltrim($url, '/')`, and map URLs in the index are `$baseUrl . $mapName . $i . '.xml.gz'`. So `$baseUrl` must be the public URL of `$basePath`, and `$basePath` is normally the document root. To keep the files in a subdirectory, put the subdirectory in `$indexName` and `$mapName` (as above), not in `$basePath`/`$baseUrl`, or every page URL gets the subdirectory too. The temporary `.tmp` file is still written in `$basePath` itself.

### Paths, not absolute URLs

`$url` is relative to `$baseUrl`: the leading `/` is trimmed and `$baseUrl` is always prepended. Passing `https://example.com/x` produces `https://example.com/https://example.com/x`. Pass the path only.

### URLs with a query string

`add()` writes the URL verbatim inside `<loc>`. Escape it yourself or the whole map becomes invalid XML:

```php
$creator->add(htmlspecialchars('search?tag=php&page=2', ENT_XML1 | ENT_QUOTES, 'UTF-8'));
```

Do the same for the `$deepLinking` argument.

### Android deep links

```php
$creator->add('/article/42', 0.5, Creator::MONTHLY, null, 'com.example.app/https/example.com/article/42');
// <xhtml:link rel="alternate" href="android-app://com.example.app/https/example.com/article/42" />
```

## Behavior to keep in mind while writing code

- **URLs are not XML-escaped** (bug). A `&` in `<loc>` or in the deep link makes the map invalid. Escape with `htmlspecialchars(…, ENT_XML1)`.
- **`generate()` deletes every file in `$basePath` whose name starts with `$mapName`** (bug), not only old maps. Keep the maps in a dedicated directory (`$mapName = 'sitemaps/map'`) and never use `$mapName = ''`.
- **Keep the files in a subdirectory via `$indexName`/`$mapName`** (`'sitemaps/idx'`, `'sitemaps/map'`), not via `$basePath`/`$baseUrl`, which also prefix the page URLs.
- **The output directory must exist.** Otherwise the first `add()` throws a `TypeError` from `gzwrite()` after a `gzopen()` warning (bug).
- **`$baseUrl` is prepended to every URL**, absolute or not, and is also the base of the map URLs in the index.
- **The current map is a `uniqid().tmp` file until it is full or `generate()` runs.** Always call `generate()`, even on error paths, or `.tmp` files pile up.
- **`$lastmod` is a Unix timestamp**, formatted with `date('Y-m-d\TH:i:sP')` in the default timezone. `null` means `time()`; `0` means 1970. There is no way to omit `<lastmod>`.
- **Priority and frequency are not validated.** Use the `Creator::ALWAYS…NEVER` constants and a priority in `[0.0, 1.0]`. Floats are written as PHP prints them (`1.0` -> `1`).
- **One `Creator` = one run.** Calling `add()` after `generate()` starts `map{n+1}` and the next `generate()` lists all maps.

Read `references/gotchas.md` for the rest before assuming the output is what you expect.
