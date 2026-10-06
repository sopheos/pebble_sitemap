# CLAUDE.md — pebble_sitemap

Ce fichier guide Claude Code quand il **maintient** cette librairie. Pour l'**utiliser** depuis un projet, voir le skill [`skills/pebble-sitemap/`](skills/pebble-sitemap/SKILL.md).

## Rôle

`sopheos/pebble_sitemap`, namespace `Pebble\Sitemap\`, PHP >= 8.1, extension `zlib`, aucune dépendance runtime. La lib écrit :
- des fichiers sitemap `<urlset>` gzippés (`map0.xml.gz`, `map1.xml.gz`…), découpés tous les `$limit` URLs ;
- un index `<sitemapindex>` non compressé (`idx.xml`) qui les référence.

Les URLs sont écrites en flux, rien n'est gardé en mémoire. Pas de validation, pas d'échappement XML, pas de ping des moteurs de recherche.

## Commandes

```bash
composer install
vendor/bin/phpunit            # toute la suite
vendor/bin/phpunit --filter CreatorTest
```

## Carte de `src/`

| Fichier | Rôle |
|---|---|
| `Creator.php` | `add()` écrit une entrée `<url>` dans la map courante (ouverte sous un nom `uniqid().tmp`, renommée en `{mapName}{i}.xml.gz` à la fermeture). `generate()` ferme, supprime les anciennes maps par `glob()` et écrit l'index |
| `GzWriter.php` | Enveloppe de `gzopen()`/`gzwrite()`/`gzclose()` |

## Tests

- PHPUnit 9.5. `tests/bootstrap.php` fixe le fuseau `Europe/Paris` (les dates `lastmod` attendues en dépendent).
- Chaque test travaille dans un répertoire `sys_get_temp_dir()/pebble_sitemap_*` créé en `setUp()` et vidé en `tearDown()`. Les fichiers sont relus avec `gzdecode()` et SimpleXML.
- Les classes de test n'ont pas de namespace. Les méthodes s'appellent `testPhraseEnCamelCase`, les assertions passent par `self::assertSame`, et des bannières `// ----` séparent les sections.
- PHPUnit convertit les warnings en exceptions : un test qui provoque volontairement l'échec de `gzopen()` préfixe l'appel par `@`.

## Conventions du code

Respecter le style existant, sans le « moderniser » au passage :
- pas de `declare(strict_types=1)` ;
- constantes de classe sans visibilité ;
- templates XML construits par concaténation de chaînes dans des méthodes statiques privées `tpl*()` ;
- méthodes publiques sans type de retour (`add()`, `generate()`, `open()`…).

Une modification de comportement doit être répercutée dans `skills/pebble-sitemap/` (SKILL.md, `references/api-reference.md`, `references/gotchas.md`) et dans le `README.md`.

## Bugs connus

Ils sont listés dans [`TODO.md`](TODO.md). Chacun est **figé par un test** annoté `// BUG:` qui vérifie le comportement *actuel*, dans la section « Known bugs » de `tests/CreatorTest.php` ou `tests/GzWriterTest.php`.

Pour corriger un bug :
1. Corriger `src/`.
2. Réécrire le test `// BUG:` pour qu'il vérifie le comportement attendu.
3. Mettre à jour l'entrée « (bug) » de `skills/pebble-sitemap/references/gotchas.md` et le SKILL.md.
4. Retirer l'entrée de `TODO.md` (il ne liste que ce qui reste à faire).
