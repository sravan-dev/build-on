<?php
/**
 * Makes /health reachable without mod_rewrite.
 *
 * Apache serves this directory's index.php for /health (it redirects /health to
 * /health/ itself), so the short URL works even where RewriteRule does not.
 *
 * __DIR__ inside health.php still resolves to the project root, so its token
 * file, page probes and .env lookups are unaffected by being included here.
 */

require __DIR__ . '/../health.php';
