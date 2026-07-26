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
        $recipient = $website->contact_email ?: $website->user?->email;

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

        $zip->addFromString('cosmic-sync/config.php', "<?php\n\nreturn " . var_export([
            'sync_secret' => $secret,
            'contact_email' => $recipient,
        ], true) . ";\n");
        $zip->addFromString('cosmic-sync/sync.php', $this->receiverScript());
        $zip->addFromString('cosmic-sync/contact.php', $this->contactReceiverScript());
        $zip->addFromString('cosmic-sync/.htaccess', "Options -Indexes\n\n<FilesMatch \"^(config\\.php|.*\\.(log|json))$\">\n    Require all denied\n</FilesMatch>\n");
        $zip->addFromString('cosmic-sync/submissions/.htaccess', "Require all denied\n");
        $zip->addFromString('.htaccess', $this->cleanUrlHtaccess());
        $zip->addFromString('cosmic-sync/README.txt', $this->readme($website));
        $zip->close();

        return $archivePath;
    }

    private function readme(Website $website): string
    {
        $domain = rtrim((string) $website->domain, '/');

        return <<<TEXT
Cosmic CMS Deployment Connector

1. Extract this ZIP directly into your website root. It creates:
   - cosmic-sync/ (the protected connector and contact receiver)
   - .htaccess (clean URLs for compiled static pages)
2. Do not rename config.php or sync.php.
3. Your expected verification endpoint is:
   {$domain}/cosmic-sync/sync.php?action=verify
4. Return to Cosmic CMS and choose Connect live site.
5. After your first successful Push live update, Apache clean URLs are enabled automatically:
   /about-us serves about-us.html and requests for /about-us.html redirect to /about-us.

The connector accepts only requests with its unique Cosmic deployment secret.
Do not expose config.php or share the connector archive publicly.

Published contact forms submit to cosmic-sync/contact.php. Each valid inquiry is
stored privately on the live site and emailed to the website owner's account when
the server's PHP mail service is configured.

If your website already has a root .htaccess file, keep its existing rules and
merge the Cosmic CMS clean-URL block instead of overwriting it.
TEXT;
    }

    private function cleanUrlHtaccess(): string
    {
        return <<<'HTACCESS'
# Cosmic CMS clean static URLs
# This managed block is added by cosmic-sync. Keep it if you want extensionless page URLs.
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Keep the homepage at the root URL.
    RewriteCond %{THE_REQUEST} \s/+index\.html[\s?] [NC]
    RewriteRule ^index\.html$ ./ [R=301,L,NE]

    # Redirect direct .html requests to their public extensionless URL.
    RewriteCond %{THE_REQUEST} \s/+(.+?)\.html[\s?] [NC]
    RewriteRule ^(.+)\.html$ /$1 [R=301,L,NE]

    # Keep real files, directories, and the deployment connector untouched.
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule ^cosmic-sync(?:/|$) - [L]

    # Serve a matching compiled HTML page for an extensionless request.
    RewriteCond %{REQUEST_FILENAME}.html -f
    RewriteRule ^(.+?)/?$ $1.html [L]
</IfModule>
HTACCESS;
    }

    private function contactReceiverScript(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

function contactResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

function contactValue(string $key, int $limit): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    $value = trim(strip_tags($value));

    return mb_substr($value, 0, $limit);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    contactResponse(['status' => 'error', 'message' => 'Method not allowed.'], 405);
}

if (contactValue('company', 120) !== '') {
    // Honeypot: acknowledge automated submissions without retaining their data.
    contactResponse(['status' => 'success', 'message' => 'Thanks for your inquiry.']);
}

$name = contactValue('name', 120);
$email = contactValue('email', 254);
$phone = contactValue('phone', 80);
$message = contactValue('message', 4000);

if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
    contactResponse(['status' => 'error', 'message' => 'Please provide your name, a valid email address, and a message.'], 422);
}

$submissionDirectory = __DIR__ . '/submissions';

if (! is_dir($submissionDirectory) && ! mkdir($submissionDirectory, 0750, true) && ! is_dir($submissionDirectory)) {
    contactResponse(['status' => 'error', 'message' => 'Your inquiry could not be saved. Please try again later.'], 500);
}

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateFile = $submissionDirectory . '/.rate-' . hash('sha256', $ip);

if (is_file($rateFile) && (time() - (int) filemtime($rateFile)) < 20) {
    contactResponse(['status' => 'error', 'message' => 'Please wait a moment before sending another inquiry.'], 429);
}

@touch($rateFile);

$submission = [
    'received_at' => gmdate(DATE_ATOM),
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'message' => $message,
];

$filename = $submissionDirectory . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.json';

if (file_put_contents($filename, json_encode($submission, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
    contactResponse(['status' => 'error', 'message' => 'Your inquiry could not be saved. Please try again later.'], 500);
}

$configPath = __DIR__ . '/config.php';
$config = is_file($configPath) ? require $configPath : [];
$recipient = (string) ($config['contact_email'] ?? '');

if (filter_var($recipient, FILTER_VALIDATE_EMAIL) && function_exists('mail')) {
    $body = "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\n\nMessage:\n{$message}\n";
    @mail($recipient, 'New website inquiry', $body, "Reply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8");
}

contactResponse(['status' => 'success', 'message' => 'Thanks — your inquiry has been received. We will be in touch soon.']);
PHP;
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

    return "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<meta charset='UTF-8'>\n<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n<title>{$title}</title>\n<link rel='preconnect' href='https://fonts.bunny.net'>\n<link href='https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap' rel='stylesheet'>\n<script src='https://cdn.tailwindcss.com'></script>\n<style>html { font-family: Figtree, ui-sans-serif, system-ui, sans-serif; }</style>\n</head>\n<body class='bg-[#0b0f19] text-slate-100 min-h-screen m-0 p-0 flex flex-col'>\n{$header}\n<main class='w-full flex-grow'>{$body}</main>\n{$footer}\n</body>\n</html>";
}

function cosmicCleanUrlRules(): string
{
    return <<<'HTACCESS'
# Cosmic CMS clean static URLs
# This managed block is added by cosmic-sync. Keep it if you want extensionless page URLs.
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Keep the homepage at the root URL.
    RewriteCond %{THE_REQUEST} \s/+index\.html[\s?] [NC]
    RewriteRule ^index\.html$ ./ [R=301,L,NE]

    # Redirect direct .html requests to their public extensionless URL.
    RewriteCond %{THE_REQUEST} \s/+(.+?)\.html[\s?] [NC]
    RewriteRule ^(.+)\.html$ /$1 [R=301,L,NE]

    # Keep real files, directories, and the deployment connector untouched.
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule ^cosmic-sync(?:/|$) - [L]

    # Serve a matching compiled HTML page for an extensionless request.
    RewriteCond %{REQUEST_FILENAME}.html -f
    RewriteRule ^(.+?)/?$ $1.html [L]
</IfModule>
HTACCESS;
}

function cosmicInstallCleanUrlRules(string $siteRoot): bool
{
    $path = $siteRoot . DIRECTORY_SEPARATOR . '.htaccess';
    $rules = cosmicCleanUrlRules();

    if (file_exists($path)) {
        $existing = file_get_contents($path);

        if ($existing === false || str_contains($existing, '# Cosmic CMS clean static URLs')) {
            return $existing !== false;
        }

        return file_put_contents($path, "\n\n" . $rules . "\n", FILE_APPEND | LOCK_EX) !== false;
    }

    return file_put_contents($path, $rules . "\n", LOCK_EX) !== false;
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

    if (! cosmicInstallCleanUrlRules($siteRoot)) {
        throw new RuntimeException('Unable to install Apache clean URL rules.');
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
