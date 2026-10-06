# TODO — pebble_sitemap

Problèmes restant à traiter, détectés lors de l'audit du 2026-10-06. Le code `src/` n'a **pas** été modifié. Chaque bug est figé par un test qui vérifie le comportement actuel : il faut l'adapter au moment de la correction.

## Bugs

- [ ] **Les URLs ne sont pas échappées en XML.** `src/Creator.php:110, 114`.
  - `<loc>` et le `href` du `xhtml:link` sont concaténés tels quels. Une URL avec query string (`?a=1&b=2`) produit une map qui n'est pas du XML valide, et les moteurs de recherche la rejettent en entier.
  - Correctif : `htmlspecialchars($item['url'], ENT_XML1 | ENT_QUOTES, 'UTF-8')`, idem pour `deepLinking` et pour `<loc>` dans `tplIndex()`.
  - Test : `tests/CreatorTest.php::testUrlsAreNotXmlEscaped`.
- [ ] **`generate()` supprime tous les fichiers qui commencent par `mapName`.** `src/Creator.php:93-96`.
  - Le nettoyage fait `glob($basePath . $mapName . '*')` et supprime tout ce qui n'est pas une map de l'exécution courante. Avec le `mapName` par défaut `map`, un `mapping.json` ou un `map.txt` posé dans le même répertoire disparaît. Avec `mapName = ''`, tout le répertoire est vidé.
  - Correctif : restreindre le motif aux maps générées, par exemple `glob($basePath . $mapName . '*' . self::XML_EXT . self::COMPRESS_EXT)` suivi d'un `preg_match('/^' . preg_quote($mapName, '/') . '\d+\.xml\.gz$/', basename($filename))`.
  - Test : `tests/CreatorTest.php::testGenerateDeletesEveryFileStartingWithMapName`.
- [ ] **Un échec de `gzopen()` est silencieux, puis `write()` lève une `TypeError`.** `src/GzWriter.php:36, 50`.
  - `open()` transforme l'échec en flux `null` (un simple warning). `write()` appelle ensuite `gzwrite(null)`. Côté `Creator`, un répertoire de sortie inexistant ou non accessible en écriture donne une `TypeError` peu explicite au premier `add()`.
  - Correctif : lever une exception dans `open()` quand `gzopen()` renvoie `false`, et dans `write()` quand le flux n'est pas ouvert.
  - Tests : `tests/GzWriterTest.php::testFailedOpenIsSilentAndWriteThrowsATypeError`, `tests/CreatorTest.php::testAddInAMissingDirectoryThrowsATypeError`.

## Dette / qualité

- [ ] `src/Creator.php:101` : le retour de `file_put_contents()` n'est pas vérifié. Si l'index ne peut pas être écrit, `generate()` se termine normalement (warning seulement). Les retours de `rename()` et `unlink()` ne sont pas vérifiés non plus.
- [ ] `src/Creator.php:60` : ni `$priority` (0.0 à 1.0) ni `$frequency` (constantes de la classe) ne sont validés.
- [ ] `src/Creator.php:71` : une URL absolue est quand même préfixée par `$baseUrl` (`https://example.com/https://other.com/x`).
- [ ] `src/Creator.php` : un `Creator` abandonné sans `generate()` laisse un fichier `*.tmp` dans `$basePath`.
- [ ] `src/Creator.php:120-122` : `if ($item['lastmod'])` est toujours vrai, puisque `add()` met `time()` par défaut. Un `lastmod` facultatif n'est pas possible.
- [ ] Pas de types de retour sur les méthodes publiques (`add()`, `generate()`, `open()`, `close()`, `write()`).
