# pebble-sitemap — gotchas

Things the method names don't tell you, grouped by class. Every item below is pinned by a test in `tests/`. Items marked **(bug)** are listed in the package's `TODO.md` and may be fixed in a later version. Check the test of the same name in `vendor/sopheos/pebble_sitemap/tests/` to see the current behavior.

## Creator — files

- **Maps are gzipped, the index is not.** `generate()` produces `{mapName}0.xml.gz`, `{mapName}1.xml.gz`… and `{indexName}.xml`.
- **A map is split every `$limit` URLs.** With `$limit = 2`, three URLs give `map0.xml.gz` (2 URLs) and `map1.xml.gz` (1 URL).
- **`$indexName` and `$mapName` may contain a subdirectory** (`'sitemaps/idx'`, `'sitemaps/map'`). The subdirectory must exist. This is the way to keep files out of the document root without changing `$baseUrl`, which also prefixes page URLs.
- **The current map is a `uniqid().tmp` file in `$basePath` until it is full or `generate()` runs.** A script that dies before `generate()` leaves it behind.
- **`generate()` without any `add()` writes an index with no `<sitemap>` entry.**
- **`generate()` deletes the maps of a previous run.** A run producing fewer maps than the last one leaves no stale `map{n}.xml.gz`.
- **(bug) `generate()` deletes every file whose name starts with `$mapName`.** The cleanup globs `$basePath . $mapName . '*'`, so `mapping.json` or `map.txt` next to the maps disappear. Use a dedicated directory; never use an empty `$mapName`.
- **A `Creator` keeps counting after `generate()`.** Calling `add()` again opens `map{n+1}` and the next `generate()` lists every map written by this instance.
- **(bug) A missing or read-only output directory ends in a `TypeError`.** `gzopen()` only warns, then the first `add()` calls `gzwrite(null)`. Create the directory before building the `Creator`.

## Creator — entries

- **Defaults:** `priority` `0.5`, `changefreq` `monthly`, `lastmod` = `time()`. The leading `/` of `$url` is trimmed.
- **`$baseUrl` is always prepended**, even to an absolute URL: `add('https://other.com/x')` gives `https://example.com/https://other.com/x`.
- **(bug) URLs are not XML-escaped.** `add('search?a=1&b=2')` writes `<loc>https://example.com/search?a=1&b=2</loc>` and the map is no longer valid XML. Same for `$deepLinking`. Escape with `htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8')`.
- **`$lastmod` is a timestamp formatted in the default timezone** (`date('Y-m-d\TH:i:sP')`). `0` is not "now": it gives `1970-01-01T01:00:00+01:00` in Europe/Paris. There is no way to omit `<lastmod>`.
- **Priority and frequency are not validated.** `'sometimes'` and `7.25` are written as is. `1.0` is written `1`.
- **`$deepLinking` adds `<xhtml:link rel="alternate" href="android-app://…" />`.** Pass the URI without the `android-app://` scheme.

## GzWriter

- **`open()` truncates** an existing file (mode `'w'`).
- **`open()` and `close()` are idempotent.** `isOpen()` follows them.
- **`write()` before `open()` throws a `TypeError`** (`gzwrite(null)`).
- **(bug) A failed `open()` is silent.** It only emits a PHP warning, `isOpen()` stays `false`, and the error surfaces at the next `write()` as a `TypeError`.
