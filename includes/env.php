<?php
/**
 * The optional .env file in the project root (KEY=value lines; see
 * .env.example). It holds the .com domain feature's settings and can override
 * the Maya keys in config/config.php (MAYA_ENV, MAYA_PUBLIC_KEY,
 * MAYA_SECRET_KEY). Values stay in this process (not putenv), and the web
 * server never serves the file (.htaccess denies dotfiles).
 */
declare(strict_types=1);

/** Parse a .env file: KEY=value, # comments, optional quotes around values. */
function env_parse(string $file): array
{
    $out = [];
    if (!is_file($file)) {
        return $out;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            continue;
        }
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        } else {
            $value = trim(preg_replace('/\s+#.*$/', '', $value)); // trailing comment
        }
        $out[$key] = $value;
    }
    return $out;
}

/** A setting from .env, then the process environment, then $default. Empty values count as unset. */
function env(string $key, $default = null)
{
    $value = $GLOBALS['APP_ENV'][$key] ?? null;
    if ($value === null || $value === '') {
        $value = getenv($key);
    }
    return $value === false || $value === null || $value === '' ? $default : $value;
}

/** Load .env and apply the Maya overrides to the config. */
function env_boot(): void
{
    $GLOBALS['APP_ENV'] = env_parse(APP_ROOT . '/.env');
    foreach (['MAYA_ENV' => 'environment', 'MAYA_PUBLIC_KEY' => 'public_key', 'MAYA_SECRET_KEY' => 'secret_key'] as $key => $setting) {
        $value = env($key);
        if ($value !== null) {
            $GLOBALS['APP_CONFIG']['maya'][$setting] = (string) $value;
        }
    }
}
