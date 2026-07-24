<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class DeploymentConnectorArchive
{
    public function create(Website $website): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZIP extension is required to create a deployment connector.');
        }

        $secret = $website->deployment_secret;

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('A deployment secret is required before creating the connector.');
        }

        $directory = storage_path('app/deployment-connectors');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('The deployment connector archive directory could not be created.');
        }

        $archivePath = $directory . DIRECTORY_SEPARATOR . 'cosmic-sync-' . $website->id . '-' . Str::random(12) . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The deployment connector archive could not be created.');
        }

        $zip->addFromString('cosmic-sync/config.php', "<?php\n\nreturn " . var_export(['sync_secret' => $secret], true) . ";\n");
        $zip->addFromString('cosmic-sync/sync.php', $this->receiverScript());
        $zip->addFromString('cosmic-sync/.htaccess', "Options -Indexes\n\n<Files \"config.php\">\n    Require all denied\n</Files>\n");
        $zip->addFromString('cosmic-sync/README.txt', $this->readme($website));
        $zip->close();

        return $archivePath;
    }

    private function readme(Website $website): string
    {
        $domain = rtrim((string) $website->domain, '/');

        return <<<TEXT
Cosmic CMS Deployment Connector

1. Extract the cosmic-sync folder into your website root.
2. Do not rename config.php or sync.php.
3. Your expected verification endpoint is:
   {$domain}/cosmic-sync/sync.php?action=verify
4. Return to Cosmic CMS and choose Connect live site.

The connector accepts only requests with its unique Cosmic deployment secret.
Do not expose config.php or share the connector archive publicly.
TEXT;
    }

    private function receiverScript(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

$configPath = __DIR__ . '/config.php';

if (! file_exists($configPath)) {
    http_response_code(500);
    exit('Missing Cosmic connector configuration.');
}

$config = require $configPath;

function cosmicResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

function cosmicAuthorized(array $config): bool
{
    $expected = (string) ($config['sync_secret'] ?? '');
    $provided = (string) ($_SERVER['HTTP_X_COSMIC_SYNC_SECRET'] ?? '');

    return $expected !== '' && hash_equals($expected, $provided);
}

function cosmicFileName(string $slug): string
{
    $slug = trim($slug);

    if ($slug === '' || $slug === '/' || $slug === 'home') {
        return 'index.html';
    }

    $safeSlug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $slug), '-'));

    return ($safeSlug ?: 'index') . '.html';
}

function cosmicDocument(array $page, array $package): string
{
    $title = htmlspecialchars(($page['title'] ?? 'Live Website') . ' | ' . ($package['website_name'] ?? 'Cosmic CMS'), ENT_QUOTES, 'UTF-8');
    $header = $package['global_header'] ?? '';
    $footer = $package['global_footer'] ?? '';
    $body = trim((string) ($page['html'] ?? ''));

    return "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<meta charset='UTF-8'>\n<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n<title>{$title}</title>\n<script src='https://cdn.tailwindcss.com'></script>\n</head>\n<body class='bg-[#0b0f19] text-slate-100 min-h-screen m-0 p-0 flex flex-col'>\n{$header}\n<main class='w-full flex-grow'>{$body}</main>\n{$footer}\n</body>\n</html>";
}

function cosmicWritePackage(array $package): array
{
    if (($package['status'] ?? null) !== 'success' || ! is_array($package['pages'] ?? null)) {
        throw new RuntimeException('Invalid published package.');
    }

    $siteRoot = dirname(__DIR__);
    $writtenFiles = [];

    foreach ($package['pages'] as $page) {
        if (! is_array($page) || ! isset($page['slug'], $page['html'])) {
            throw new RuntimeException('Published package contains an invalid page.');
        }

        $path = $siteRoot . DIRECTORY_SEPARATOR . cosmicFileName((string) $page['slug']);
        $temporaryPath = $path . '.tmp';

        if (file_put_contents($temporaryPath, cosmicDocument($page, $package), LOCK_EX) === false || ! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to safely write the published page.');
        }

        $writtenFiles[] = basename($path);
    }

    return $writtenFiles;
}

if (! cosmicAuthorized($config)) {
    cosmicResponse(['status' => 'error', 'message' => 'Unauthorized connector request.'], 401);
}

$action = $_GET['action'] ?? null;

if ($action === 'verify' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    cosmicResponse(['status' => 'success', 'message' => 'Cosmic deployment connector is ready.']);
}

if ($action === 'receive_package' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $package = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        cosmicResponse(['status' => 'success', 'files' => cosmicWritePackage($package)]);
    } catch (Throwable $exception) {
        error_log('Cosmic connector failed: ' . $exception->getMessage());
        cosmicResponse(['status' => 'error', 'message' => 'Published files could not be updated.'], 422);
    }
}

cosmicResponse(['status' => 'error', 'message' => 'Unsupported connector action.'], 405);
PHP;
    }
}
