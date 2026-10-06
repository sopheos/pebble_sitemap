# Pebble/Sitemap

Génération de sitemaps XML gzippés et de leur index, pour PHP 8.1+.

Les URLs sont écrites au fil de l'eau dans des fichiers `map0.xml.gz`, `map1.xml.gz`… (50 000 URLs par fichier par défaut), sans être gardées en mémoire. `generate()` écrit ensuite l'index `idx.xml` qui les référence.

## Installation

```bash
composer require sopheos/pebble_sitemap
```

L'extension `zlib` est requise.

## Claude Code

Ce package fournit un skill Claude Code dans [`skills/pebble-sitemap/`](skills/pebble-sitemap/). Il documente les patterns d'usage et les pièges de la librairie : URLs non échappées en XML, nettoyage qui supprime tout fichier commençant par le nom des maps, URL toujours préfixée par l'URL de base, répertoire inexistant qui se termine en `TypeError`, etc.

Dans un projet qui dépend de `sopheos/pebble_sitemap`, copie-le une fois dans `.claude/skills/` après `composer install` pour que Claude Code le charge automatiquement. Le nom du dossier doit correspondre au `name` déclaré dans `SKILL.md` :

```bash
cp -r vendor/sopheos/pebble_sitemap/skills/pebble-sitemap .claude/skills/pebble-sitemap
```

Pour la maintenance de la lib elle-même, voir [`CLAUDE.md`](CLAUDE.md). Les bugs connus sont listés dans [`TODO.md`](TODO.md).

## Creator

`\Pebble\Sitemap\Creator` écrit les sitemaps dans un répertoire servi publiquement à l'URL de base.

* `__construct(string $basePath, string $baseUrl, string $indexName = 'idx', string $mapName = 'map', int $limit = 50000)` Répertoire de sortie (il doit exister), URL publique de ce répertoire, nom de l'index (sans extension, peut contenir un sous-répertoire), préfixe des fichiers de map (idem), nombre maximum d'URLs par map.
* `add(string $url, float $priority = 0.5, string $frequency = Creator::MONTHLY, ?int $lastmod = null, ?string $deepLinking = null)` Ajoute une URL. `$url` est un chemin relatif à `$baseUrl` (le `/` initial est retiré). `$lastmod` est un timestamp, `time()` par défaut. `$deepLinking` ajoute un lien `android-app://…`. Ni la priorité ni la fréquence ne sont validées.
* `generate()` Ferme la map en cours, supprime les anciennes maps et écrit l'index.

Constantes de fréquence : `ALWAYS`, `HOURLY`, `DAILY`, `WEEKLY`, `MONTHLY`, `YEARLY`, `NEVER`.

```php
use Pebble\Sitemap\Creator;

// Fichiers dans /var/www/public/sitemaps/, servi à https://example.com/sitemaps/
$creator = new Creator('/var/www/public', 'https://example.com', 'sitemaps/idx', 'sitemaps/map');

$creator->add('/', 1.0, Creator::DAILY);
foreach ($articles as $article) {
    $creator->add('/article/' . $article->slug, 0.8, Creator::WEEKLY, $article->updatedAt);
}

$creator->generate();
// /var/www/public/sitemaps/map0.xml.gz, map1.xml.gz…, /var/www/public/sitemaps/idx.xml
```

`$baseUrl` préfixe à la fois les URLs des pages et celles des maps dans l'index : il doit être l'URL publique de `$basePath`. Pour ranger les fichiers dans un sous-répertoire (qui doit exister), le mettre dans `$indexName` et `$mapName` comme ci-dessus, pas dans `$basePath`.

**Attention** :

* les URLs ne sont pas échappées : passer `htmlspecialchars($url, ENT_XML1)` si elles peuvent contenir `&`, `<` ou `>` ;
* `generate()` supprime **tous** les fichiers du répertoire dont le nom commence par `$mapName` : ranger les maps dans un sous-répertoire dédié et ne jamais utiliser un `$mapName` vide.

## GzWriter

`\Pebble\Sitemap\GzWriter` est l'enveloppe de `gzopen()` utilisée par `Creator`.

* `__construct(string $filename)`
* `filename() : string` Chemin du fichier.
* `open()` Ouvre le fichier en écriture (`'w'`, le contenu existant est écrasé). Sans effet s'il est déjà ouvert.
* `isOpen() : bool`
* `write(string $content)` Écrit dans le flux. Lève une `TypeError` si le flux n'est pas ouvert.
* `close()` Ferme le flux. Sans effet s'il est déjà fermé.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Les tests écrivent dans un répertoire temporaire et relisent les fichiers avec `gzdecode()` et SimpleXML. Les bugs connus sont figés par des tests annotés `// BUG:` qui vérifient le comportement actuel.
