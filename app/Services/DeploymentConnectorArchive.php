<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class DeploymentConnectorArchive
{
    public function __construct(private readonly CommerceCapabilityService $commerce)
    {
    }

    public function create(Website $website): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZIP extension is required to create a deployment connector.');
        }

        $secret = $website->deployment_secret;
        $recipient = $website->contact_email ?: $website->user?->email;
        $commerceSettings = $this->commerce->settingsFor($website);

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

        $zip->addFromString('cosmic-cms/config.php', "<?php\n\nreturn " . var_export([
            'sync_secret' => $secret,
            'contact_email' => $recipient,
            'cms_url' => rtrim(url('/'), '/'),
            'website_id' => $website->id,
            'commerce_public_key' => $commerceSettings->public_key,
            'commerce_manifest_url' => rtrim(url('/'), '/') . '/api/v1/commerce/sites/' . $commerceSettings->public_key . '/manifest',
            'analytics_url' => rtrim(url('/'), '/') . '/api/v1/websites/' . $website->id . '/analytics',
        ], true) . ";\n");
        $zip->addFromString('cosmic-cms/sync.php', $this->receiverScript());
        $zip->addFromString('cosmic-cms/contact.php', $this->contactReceiverScript());
        $zip->addFromString('cosmic-cms/commerce.php', $this->commerceReceiverScript());
        $zip->addFromString('cosmic-cms/analytics.php', $this->analyticsReceiverScript());
        $zip->addFromString('cosmic-cms/.htaccess', "Options -Indexes\n\n<FilesMatch \"^(config\\.php|.*\\.(log|json))$\">\n    Require all denied\n</FilesMatch>\n");
        $zip->addFromString('cosmic-cms/submissions/.htaccess', "Require all denied\n");
        $zip->addFromString('.htaccess', $this->cleanUrlHtaccess());
        $this->addSharedTailwindStylesheet($zip);
        $zip->addFromString('cosmic-cms/README.txt', $this->readme($website));

        $zip->close();

        return $archivePath;
    }

    private function addSharedTailwindStylesheet(ZipArchive $zip): void
    {
        $path = public_path('cosmic/cosmic-tailwind.css');

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException(
                'The shared Cosmic Tailwind stylesheet is missing. Run npm run build before downloading the deployment connector.'
            );
        }

        $size = filesize($path);
        if (! is_int($size) || $size < 10000) {
            throw new RuntimeException(
                'The shared Cosmic Tailwind stylesheet is incomplete. Run npm run build before downloading the deployment connector.'
            );
        }

        $css = file_get_contents($path);
        if (! is_string($css) || (! str_contains($css, '--tw-') && ! str_contains($css, '.flex'))) {
            throw new RuntimeException(
                'The shared Cosmic Tailwind stylesheet is invalid. Run npm run build before downloading the deployment connector.'
            );
        }

        if (! $zip->addFromString('cosmic/cosmic-tailwind.css', $css)) {
            throw new RuntimeException('The shared Cosmic Tailwind stylesheet could not be added to the deployment connector.');
        }
    }

    private function readme(Website $website): string
    {
        $domain = rtrim((string) $website->domain, '/');

        return <<<TEXT
Cosmic CMS Deployment Connector

1. Extract this ZIP directly into your website root. It creates:
   - cosmic-cms/ (the protected deployment/contact connector, analytics relay, and commerce bridge)
   - cosmic/cosmic-tailwind.css (the compiled Tailwind stylesheet used by published pages)
   - .htaccess (clean URLs for compiled static pages)
2. Keep cosmic/cosmic-tailwind.css in that exact path. Do not rename config.php or sync.php.
3. Your expected verification endpoint is:
   {$domain}/cosmic-cms/sync.php?action=verify
4. Return to Cosmic CMS and choose Connect to live.
5. After your first successful Push to live, Apache clean URLs are enabled automatically:
   /about-us serves about-us.html and requests for /about-us.html redirect to /about-us.

The connector accepts only requests with its unique Cosmic deployment secret.
Do not expose config.php or share the connector archive publicly.

All plans receive the commerce-ready connector. cosmic-cms/commerce.php?action=manifest
returns a safe storefront manifest; live store capabilities activate only when the
website owner's Cosmic plan includes commerce and the store is enabled.

Published pages send privacy-light, first-party traffic counters to cosmic-cms/analytics.php.
The relay forwards only aggregate counters to Cosmic CMS; the deployment secret stays
server-side and is never exposed in the generated HTML.

Published contact forms submit to cosmic-cms/contact.php. Each valid inquiry is
stored privately on the live site, forwarded to the Cosmic CMS Inquiry Inbox when
the CMS is reachable, and emailed to the website owner's account when the server's
PHP mail service is configured.

If your website already has a root .htaccess file, keep its existing rules and
merge the Cosmic CMS clean-URL block instead of overwriting it.
TEXT;
    }

    private function cleanUrlHtaccess(): string
    {
        return <<<'HTACCESS'
# Cosmic CMS clean static URLs
# This managed block is added by Cosmic CMS. Keep it if you want extensionless page URLs.
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Keep the homepage at the root URL.
    RewriteCond %{THE_REQUEST} \s/+index\.html[\s?] [NC]
    RewriteRule ^index\.html$ ./ [R=301,L,NE]

    # Redirect direct .html requests to their public extensionless URL.
    RewriteCond %{THE_REQUEST} \s/+(.+?)\.html[\s?] [NC]
    RewriteRule ^(.+)\.html$ /$1 [R=301,L,NE]

    # Backward compatibility for pages previously compiled with /cosmic-sync/.
    # If the legacy connector folder is not installed, route those requests to
    # the current /cosmic-cms/ connector without redirecting POST requests.
    RewriteCond %{DOCUMENT_ROOT}/cosmic-sync !-d
    RewriteRule ^cosmic-sync/(.*)$ cosmic-cms/$1 [L]

    # Keep real files, directories, and the deployment connector untouched.
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule ^(?:cosmic-cms|cosmic-sync)(?:/|$) - [L]

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

function forwardSubmissionToCosmic(array $config, array $submission): void
{
    $cmsUrl = rtrim((string) ($config['cms_url'] ?? ''), '/');
    $websiteId = (int) ($config['website_id'] ?? 0);
    $secret = (string) ($config['sync_secret'] ?? '');

    if ($cmsUrl === '' || $websiteId < 1 || $secret === '' || ! function_exists('curl_init')) {
        return;
    }

    $request = curl_init($cmsUrl . '/api/v1/websites/' . $websiteId . '/contact-submissions');

    if ($request === false) {
        return;
    }

    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($submission, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Cosmic-Sync-Secret: ' . $secret,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
    ]);
    curl_exec($request);
    curl_close($request);
}

function forwardAnalyticsConversion(array $config): void
{
    $analyticsUrl = trim((string) ($config['analytics_url'] ?? ''));
    $secret = trim((string) ($config['sync_secret'] ?? ''));

    if ($analyticsUrl === '' || $secret === '' || ! function_exists('curl_init')) {
        return;
    }

    $request = curl_init($analyticsUrl);
    if ($request === false) {
        return;
    }

    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['conversions' => 1], JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Cosmic-Sync-Secret: ' . $secret,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 4,
    ]);
    curl_exec($request);
    curl_close($request);
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
$fields = [];
$requiredNames = array_values(array_filter(array_map('trim', explode(',', contactValue('_cosmic_required', 700))), static fn($name) => preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name)));
$isCustomSparkForm = contactValue('_cosmic_form_name', 120) !== '' || $requiredNames !== [];

foreach ($_POST as $key => $value) {
    if ($key === 'company' || str_starts_with((string) $key, '_cosmic_') || ! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
        continue;
    }

    if (is_array($value)) {
        $items = array_values(array_filter(array_map(static function ($item): string {
            return mb_substr(trim(strip_tags((string) $item)), 0, 1000);
        }, $value)));
        $fields[$key] = $items;
        continue;
    }

    $fields[$key] = contactValue($key, $key === 'message' ? 4000 : 1000);
}

foreach ($requiredNames as $requiredName) {
    $raw = $_POST[$requiredName] ?? null;
    $hasValue = is_array($raw)
        ? count(array_filter(array_map(static fn($value) => trim((string) $value), $raw))) > 0
        : trim((string) ($raw ?? '')) !== '';
    if (! $hasValue) {
        contactResponse(['status' => 'error', 'message' => 'Please complete all required fields.'], 422);
    }
}

if ($name === '') {
    $name = trim((string) ($fields['name'] ?? $fields['full_name'] ?? $fields['first_name'] ?? 'Website visitor'));
}
if ($email === '') {
    $email = trim((string) ($fields['email'] ?? ''));
}
if ($phone === '') {
    $phone = trim((string) ($fields['phone'] ?? $fields['mobile'] ?? ''));
}
if ($message === '') {
    $parts = [];
    foreach ($fields as $fieldKey => $fieldValue) {
        if (str_starts_with((string) $fieldKey, '_cosmic_')) continue;
        $label = ucwords(str_replace('_', ' ', (string) $fieldKey));
        $display = is_array($fieldValue) ? implode(', ', $fieldValue) : (string) $fieldValue;
        if (trim($display) !== '') $parts[] = $label . ': ' . $display;
    }
    $message = implode("\n", $parts);
}
if ($message === '') {
    $message = 'Website form submission';
}
if ($isCustomSparkForm) {
    if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        contactResponse(['status' => 'error', 'message' => 'Please enter a valid email address.'], 422);
    }
} elseif ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || trim((string) ($_POST['message'] ?? '')) === '') {
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
    'fields' => $fields,
];

$filename = $submissionDirectory . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.json';

if (file_put_contents($filename, json_encode($submission, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
    contactResponse(['status' => 'error', 'message' => 'Your inquiry could not be saved. Please try again later.'], 500);
}

$configPath = __DIR__ . '/config.php';
$config = is_file($configPath) ? require $configPath : [];
$recipient = (string) ($config['contact_email'] ?? '');

// The local JSON copy remains the durable fallback if the CMS is temporarily
// unreachable. Forwarding is best-effort and never makes the visitor retry.
forwardSubmissionToCosmic($config, $submission);
forwardAnalyticsConversion($config);

if (filter_var($recipient, FILTER_VALIDATE_EMAIL) && function_exists('mail')) {
    $lines = [];
    foreach ($fields as $key => $value) {
        $label = ucwords(str_replace('_', ' ', $key));
        $displayValue = is_array($value) ? implode(', ', $value) : $value;
        $lines[] = "{$label}: {$displayValue}";
    }
    $body = implode("\n", $lines) . "\n";
    $headers = "Content-Type: text/plain; charset=UTF-8";
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $headers = "Reply-To: {$email}\r\n" . $headers;
    }
    @mail($recipient, 'New website inquiry', $body, $headers);
}

contactResponse(['status' => 'success', 'message' => 'Thanks — your inquiry has been received. We will be in touch soon.']);
PHP;
    }

    private function commerceReceiverScript(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

function commerceResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    commerceResponse(['status' => 'error', 'message' => 'Method not allowed.'], 405);
}

$configPath = __DIR__ . '/config.php';
if (! is_file($configPath)) {
    commerceResponse(['status' => 'error', 'message' => 'Missing Cosmic connector configuration.'], 500);
}

$config = require $configPath;
$action = (string) ($_GET['action'] ?? 'manifest');

if ($action !== 'manifest') {
    commerceResponse(['status' => 'error', 'message' => 'Unsupported commerce action.'], 405);
}

$manifestUrl = trim((string) ($config['commerce_manifest_url'] ?? ''));
$publicKey = trim((string) ($config['commerce_public_key'] ?? ''));

if ($manifestUrl === '' || $publicKey === '') {
    commerceResponse(['status' => 'error', 'message' => 'Commerce connector configuration is incomplete.'], 500);
}

if (! function_exists('curl_init')) {
    commerceResponse([
        'status' => 'error',
        'message' => 'The server PHP cURL extension is required for live commerce.',
    ], 503);
}

$request = curl_init($manifestUrl);
if ($request === false) {
    commerceResponse(['status' => 'error', 'message' => 'Unable to initialize the commerce bridge.'], 503);
}

curl_setopt_array($request, [
    CURLOPT_HTTPGET => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);

$body = curl_exec($request);
$status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
$error = curl_error($request);
curl_close($request);

if (! is_string($body) || $body === '' || $status < 200 || $status >= 300) {
    commerceResponse([
        'status' => 'error',
        'message' => 'Cosmic Commerce is temporarily unavailable.',
        'upstream_status' => $status ?: null,
        'detail' => $error !== '' ? $error : null,
    ], 503);
}

$payload = json_decode($body, true);
if (! is_array($payload)) {
    commerceResponse(['status' => 'error', 'message' => 'Cosmic Commerce returned an invalid response.'], 502);
}

commerceResponse(['status' => 'success', 'commerce' => $payload]);
PHP;
    }

    private function analyticsReceiverScript(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

function analyticsResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    analyticsResponse(['status' => 'error', 'message' => 'Method not allowed.'], 405);
}

$configPath = __DIR__ . '/config.php';
if (! is_file($configPath)) {
    analyticsResponse(['status' => 'error', 'message' => 'Missing Cosmic connector configuration.'], 500);
}

$config = require $configPath;
$analyticsUrl = trim((string) ($config['analytics_url'] ?? ''));
$secret = trim((string) ($config['sync_secret'] ?? ''));

if ($analyticsUrl === '' || $secret === '') {
    analyticsResponse(['status' => 'error', 'message' => 'Analytics relay is not configured.'], 503);
}

$raw = (string) file_get_contents('php://input');
$data = json_decode($raw, true);
if (! is_array($data)) {
    analyticsResponse(['status' => 'error', 'message' => 'Invalid analytics payload.'], 422);
}

$allowed = ['page_views', 'visitors', 'sessions', 'conversions', 'engaged_sessions', 'duration_seconds'];
$payload = [];
foreach ($allowed as $metric) {
    $value = (int) ($data[$metric] ?? 0);
    if ($value > 0) {
        $payload[$metric] = min($value, $metric === 'duration_seconds' ? 864000 : 10000);
    }
}

if ($payload === []) {
    analyticsResponse(['status' => 'accepted'], 202);
}

// Same-origin browser traffic hits this relay. The connector secret remains
// server-side and authenticates the aggregate counters with Cosmic CMS.
if (function_exists('curl_init')) {
    $request = curl_init($analyticsUrl);
    if ($request !== false) {
        curl_setopt_array($request, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Cosmic-Sync-Secret: ' . $secret,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
        ]);
        curl_exec($request);
        curl_close($request);
    }
}

// Analytics is best-effort and must never slow or break the published site.
analyticsResponse(['status' => 'accepted'], 202);
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

function cosmicOutputPath(array $page): string
{
    $requestedPath = trim((string) ($page['output_path'] ?? ''));

    if ($requestedPath === '') {
        return cosmicFileName((string) ($page['slug'] ?? ''));
    }

    $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $requestedPath)), static fn ($part) => $part !== ''));

    // Maximum supported page depth is three folders plus index.html.
    if ($parts === [] || count($parts) > 4) {
        throw new RuntimeException('Published package contains an invalid output path.');
    }

    $safeParts = [];
    foreach ($parts as $part) {
        if ($part === '.' || $part === '..' || ! preg_match('/^[A-Za-z0-9_-]+(?:\.html)?$/', $part)) {
            throw new RuntimeException('Published package contains an unsafe output path.');
        }
        $safeParts[] = $part;
    }

    $path = implode(DIRECTORY_SEPARATOR, $safeParts);
    if (! str_ends_with(strtolower($path), '.html')) {
        throw new RuntimeException('Published package output paths must be HTML files.');
    }

    return $path;
}

function cosmicDocument(array $page, array $package): string
{
    $title = htmlspecialchars(($page['title'] ?? 'Live Website') . ' | ' . ($package['website_name'] ?? 'Cosmic CMS'), ENT_QUOTES, 'UTF-8');
    $metaDescription = trim((string) ($page['meta_description'] ?? ''));
    $metaTag = $metaDescription !== '' ? "<meta name='description' content='" . htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') . "'>\n" : '';
    $ogType = htmlspecialchars((string) ($page['og_type'] ?? 'website'), ENT_QUOTES, 'UTF-8');
    $ogImage = trim((string) ($page['og_image'] ?? ''));
    $socialMeta = "<meta property='og:type' content='{$ogType}'>\n<meta property='og:title' content='{$title}'>\n";
    if ($metaDescription !== '') $socialMeta .= "<meta property='og:description' content='" . htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') . "'>\n";
    if ($ogImage !== '') $socialMeta .= "<meta property='og:image' content='" . htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') . "'>\n";
    if (! empty($page['structured_content'])) {
        if (! empty($page['published_at'])) $socialMeta .= "<meta property='article:published_time' content='" . htmlspecialchars((string) $page['published_at'], ENT_QUOTES, 'UTF-8') . "'>\n";
        if (! empty($page['updated_at'])) $socialMeta .= "<meta property='article:modified_time' content='" . htmlspecialchars((string) $page['updated_at'], ENT_QUOTES, 'UTF-8') . "'>\n";
    }
    $header = $package['global_header'] ?? '';
    $footer = $package['global_footer'] ?? '';
    $body = trim((string) ($page['html'] ?? ''));
    $pageStyle = strtolower(trim((string) ($page['page_style'] ?? 'balanced')));
    $pageStyle = in_array($pageStyle, ['clean', 'balanced', 'premium'], true) ? $pageStyle : 'balanced';
    $bodyBaseClass = $pageStyle === 'clean' ? 'bg-white text-slate-900' : 'bg-[#0b0f19] text-slate-100';
    $outputPath = str_replace('\\', '/', (string) ($page['output_path'] ?? ''));
    // Posts can live under any parent slug such as /blog or /news.
    $outputDirectory = trim(str_replace('\\', '/', dirname($outputPath)), './');
    $baseTag = $outputDirectory !== ''
        ? "<base href='" . str_repeat('../', substr_count($outputDirectory, '/') + 1) . "'>\n"
        : '';

    $themePalette = is_array($package['theme_palette'] ?? null) ? $package['theme_palette'] : [];
    $escCss = static fn ($value, $fallback) => htmlspecialchars((string) ($value ?: $fallback), ENT_QUOTES, 'UTF-8');
    $themeVars = ':root{--p:'.$escCss($themePalette['primary'] ?? null, '#243447')
        .';--a:'.$escCss($themePalette['accent'] ?? null, '#60A5FA')
        .';--s:'.$escCss($themePalette['surface'] ?? null, '#30475E')
        .';--t:'.$escCss($themePalette['text'] ?? null, '#F8FAFC')
        .';--m:'.$escCss($themePalette['muted'] ?? null, '#64748B')
        .';--b:'.$escCss($themePalette['border'] ?? null, '#E2E8F0')
        .';--bg:'.$escCss($themePalette['background'] ?? null, '#FFFFFF').'}';

    // The first visible image is usually the LCP hero. Preload it so mobile
    // browsers discover it before Tailwind CDN has finished evaluating.
    $heroPreload = '';
    if (preg_match('/<img[^>]+src=[\"\']([^\"\']+)[\"\']/i', $body, $match) === 1) {
        $heroSrc = htmlspecialchars((string) $match[1], ENT_QUOTES, 'UTF-8');
        $heroPreload = "<link rel='preload' as='image' href='{$heroSrc}' fetchpriority='high'>\n";
    }

    // Batch 10 parity contract: every connector already ships the compiled Cosmic
    // stylesheet. Use it as the fast baseline and add Tailwind's browser runtime only
    // when the compiled page contains a schema-backed Spark whose Luna patch may use
    // utilities that could not have existed at build time.
    $hasDynamicTailwindSchema = str_contains($body, "data-cosmic-tailwind-schema='schema_backed'");
    $tailwindAsset = "<link rel='stylesheet' href='/cosmic/cosmic-tailwind.css'>\n";
    if ($hasDynamicTailwindSchema) {
        $tailwindAsset .= "<link rel='preconnect' href='https://cdn.tailwindcss.com' crossorigin>\n<script src='https://cdn.tailwindcss.com'></script>\n";
    }

    // Keep downloadable/live export parity with the staging preview.
    $themeVars .= "body#cosmic-published-page#cosmic-published-page[data-cosmic-page-style='clean'] main :is(section[data-cosmic-block-type^='hero_'],section[data-cosmic-block-type='image_cta_banner']) :is(h1,h2,h3,h4,h5,h6){color:#0f172a!important;-webkit-text-fill-color:#0f172a!important}body#cosmic-published-page#cosmic-published-page[data-cosmic-page-style='clean'] main :is(section[data-cosmic-block-type^='hero_'],section[data-cosmic-block-type='image_cta_banner']) :is(p,small,[data-cosmic-type='eyebrow']){color:#475569!important;-webkit-text-fill-color:#475569!important;opacity:1!important}";

    // Keep responsive text utilities after their unprefixed fallbacks. The legacy
    // inline bundle declared xl:* before .text-*, so the later base rule won at
    // desktop widths and made published Sparks smaller than their Builder render.
    $responsiveTypographyCss = <<<'CSS'
@media(min-width:640px){.sm\:text-sm{font-size:.875rem;line-height:1.25rem}.sm\:text-base{font-size:1rem;line-height:1.5rem}.sm\:text-lg{font-size:1.125rem;line-height:1.75rem}.sm\:text-xl{font-size:1.25rem;line-height:1.75rem}.sm\:text-2xl{font-size:1.5rem;line-height:2rem}.sm\:text-3xl{font-size:1.875rem;line-height:2.25rem}.sm\:text-4xl{font-size:2.25rem;line-height:2.5rem}.sm\:text-5xl{font-size:3rem;line-height:1}.sm\:text-6xl{font-size:3.75rem;line-height:1}.sm\:text-7xl{font-size:4.5rem;line-height:1}}
@media(min-width:768px){.md\:text-4xl{font-size:2.25rem;line-height:2.5rem}.md\:text-5xl{font-size:3rem;line-height:1}.md\:text-6xl{font-size:3.75rem;line-height:1}.md\:text-7xl{font-size:4.5rem;line-height:1}}
@media(min-width:1024px){.lg\:text-4xl{font-size:2.25rem;line-height:2.5rem}.lg\:text-5xl{font-size:3rem;line-height:1}.lg\:text-6xl{font-size:3.75rem;line-height:1}.lg\:text-7xl{font-size:4.5rem;line-height:1}.lg\:text-8xl{font-size:6rem;line-height:1}}
@media(min-width:1280px){.xl\:text-4xl{font-size:2.25rem;line-height:2.5rem}.xl\:text-5xl{font-size:3rem;line-height:1}.xl\:text-6xl{font-size:3.75rem;line-height:1}.xl\:text-7xl{font-size:4.5rem;line-height:1}.xl\:text-8xl{font-size:6rem;line-height:1}.xl\:text-9xl{font-size:8rem;line-height:1}}
CSS;

    return "<!DOCTYPE html>\n<html lang='en'>\n<head>\n<meta charset='UTF-8'>\n<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n{$baseTag}{$metaTag}{$socialMeta}<title>{$title}</title>\n<link rel='preconnect' href='https://fonts.bunny.net' crossorigin>\n{$heroPreload}<link href='https://fonts.bunny.net/css?family=inter:400,500,600,700,800&family=manrope:400,500,600,700,800&display=swap' rel='stylesheet'>\n{$tailwindAsset}<style>{$themeVars}html,body,button,input,select,textarea{font-family:var(--cosmic-font-body,Manrope,ui-sans-serif,system-ui,sans-serif)!important}h1,h2,h3,h4,h5,h6{font-family:var(--cosmic-font-display,var(--cosmic-font-body,Manrope,ui-sans-serif,system-ui,sans-serif))!important}[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] select{color-scheme:light;background:#fff;color:#0f172a}[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] select option{background:#fff;color:#0f172a}[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] select{color-scheme:dark;background:rgba(15,23,42,.38);color:#f8fafc}[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] select option{background:#0f172a;color:#f8fafc}[data-cosmic-contact-form][data-cosmic-contact-scheme='light'] input[type=date]{color-scheme:light}[data-cosmic-contact-form][data-cosmic-contact-scheme='dark'] input[type=date]{color-scheme:dark}[data-cosmic-spark] :is(button,a,[role='button'],[data-cosmic-luna-display='button']):not([aria-label]):not([data-cosmic-preserve-button]){border-radius:var(--cosmic-local-button-radius,var(--cosmic-radius-button,9999px))!important}[data-cosmic-spark] [class~='rounded-full']{border-radius:9999px!important}.cosmic-universal-background-host[data-cosmic-background-state='primary'] .group\/repeatable-section article,[data-cosmic-resolved-theme='primary'] .group\/repeatable-section article{background:color-mix(in srgb,var(--cosmic-primary,#243447) 72%,rgba(255,255,255,.14))!important;border-color:color-mix(in srgb,var(--cosmic-primary,#243447) 54%,rgba(255,255,255,.32))!important}.cosmic-universal-background-host[data-cosmic-universal-background='1'][data-cosmic-background-state='primary'] .group\/repeatable-section article,.cosmic-universal-background-host[data-cosmic-background='1'][data-cosmic-background-state='primary'] .group\/repeatable-section article{background:color-mix(in srgb,var(--cosmic-primary,#243447) 76%,transparent)!important;border-color:rgba(255,255,255,.22)!important;-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);box-shadow:0 18px 44px rgba(2,6,23,.16)}.cosmic-static-header .cosmic-primary-cta{border-radius:var(--cosmic-radius-button,9999px)!important}.cosmic-motion-hero .cosmic-motion-copy h1{font-size:clamp(3rem,7vw,5.4rem)!important;line-height:.95!important}.cosmic-motion2-copy h1{font-size:clamp(2.25rem,4.6vw,4.5rem)!important;line-height:.98!important}.cosmic-motion3-copy h1{font-size:clamp(3rem,7vw,5.4rem)!important;line-height:.95!important}.cosmic-motion4-copy h1{font-size:clamp(3rem,6.2vw,5.75rem)!important;line-height:.94!important}.cosmic-motion4-copy>p{font-weight:400!important}.cosmic-motion4-copywrap{width:100%;max-width:88rem;margin-left:auto;margin-right:auto}.p5-copy h1{font-size:clamp(2.25rem,4.6vw,4.5rem)!important;line-height:.98!important}@media(max-width:639px){.cosmic-motion-hero .cosmic-motion-copy h1,.cosmic-motion3-copy h1,.cosmic-motion4-copy h1{font-size:2.5rem!important}.cosmic-motion2-copy h1,.p5-copy h1{font-size:2.25rem!important}}@media(min-width:1280px){.xl\:text-4xl{font-size:2.25rem;line-height:2.5rem}.xl\:text-5xl{font-size:3rem;line-height:1}.xl\:text-6xl{font-size:3.75rem;line-height:1}.xl\:text-7xl{font-size:4.5rem;line-height:1}.xl\:text-8xl{font-size:6rem;line-height:1}.xl\:text-9xl{font-size:8rem;line-height:1}}.text-xs{font-size:.75rem;line-height:1rem}.text-sm{font-size:.875rem;line-height:1.25rem}.text-base{font-size:1rem;line-height:1.5rem}.text-lg{font-size:1.125rem;line-height:1.75rem}.text-xl{font-size:1.25rem;line-height:1.75rem}.text-2xl{font-size:1.5rem;line-height:2rem}.text-3xl{font-size:1.875rem;line-height:2.25rem}.text-4xl{font-size:2.25rem;line-height:2.5rem}.text-5xl{font-size:3rem;line-height:1}.text-6xl{font-size:3.75rem;line-height:1}.text-7xl{font-size:4.5rem;line-height:1}.text-8xl{font-size:6rem;line-height:1}.text-9xl{font-size:8rem;line-height:1}@media(min-width:640px){.sm\:text-sm{font-size:.875rem;line-height:1.25rem}.sm\:text-base{font-size:1rem;line-height:1.5rem}.sm\:text-lg{font-size:1.125rem;line-height:1.75rem}.sm\:text-xl{font-size:1.25rem;line-height:1.75rem}.sm\:text-2xl{font-size:1.5rem;line-height:2rem}.sm\:text-3xl{font-size:1.875rem;line-height:2.25rem}.sm\:text-4xl{font-size:2.25rem;line-height:2.5rem}.sm\:text-5xl{font-size:3rem;line-height:1}.sm\:text-6xl{font-size:3.75rem;line-height:1}.sm\:text-7xl{font-size:4.5rem;line-height:1}}@media(min-width:768px){.md\:text-4xl{font-size:2.25rem;line-height:2.5rem}.md\:text-5xl{font-size:3rem;line-height:1}.md\:text-6xl{font-size:3.75rem;line-height:1}.md\:text-7xl{font-size:4.5rem;line-height:1}}@media(min-width:1024px){.lg\:text-4xl{font-size:2.25rem;line-height:2.5rem}.lg\:text-5xl{font-size:3rem;line-height:1}.lg\:text-6xl{font-size:3.75rem;line-height:1}.lg\:text-7xl{font-size:4.5rem;line-height:1}.lg\:text-8xl{font-size:6rem;line-height:1}}section[data-cosmic-spark='1']{padding-top:var(--cosmic-section-py-mobile,var(--cosmic-space-section-y-mobile,56px))!important;padding-bottom:var(--cosmic-section-py-mobile,var(--cosmic-space-section-y-mobile,56px))!important;padding-left:var(--cosmic-section-px-mobile,var(--cosmic-space-section-x-mobile,20px))!important;padding-right:var(--cosmic-section-px-mobile,var(--cosmic-space-section-x-mobile,20px))!important;box-sizing:border-box;width:100%;max-width:100%;overflow-x:clip}section[data-cosmic-spark='1'] :is(img,video,iframe,svg,canvas){max-width:100%}section[data-cosmic-spark='1'] :is(h1,h2,h3,h4,h5,h6,p,a,button,label){overflow-wrap:anywhere}section[data-cosmic-spark='1'] .grid>*{min-width:0}section[data-cosmic-spark='1'] :is(input,select,textarea,button){max-width:100%}@media(max-width:639px){section[data-cosmic-spark='1'] table{display:block;width:100%;max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}}main>section[data-cosmic-spark='1']:not(:first-child){content-visibility:auto;contain-intrinsic-size:800px}@media(min-width:768px){section[data-cosmic-spark='1']{padding-top:var(--cosmic-section-py-tablet,var(--cosmic-space-section-y-tablet,80px))!important;padding-bottom:var(--cosmic-section-py-tablet,var(--cosmic-space-section-y-tablet,80px))!important;padding-left:var(--cosmic-section-px-tablet,var(--cosmic-space-section-x-tablet,24px))!important;padding-right:var(--cosmic-section-px-tablet,var(--cosmic-space-section-x-tablet,24px))!important}}@media(min-width:1025px){section[data-cosmic-spark='1']{padding-top:var(--cosmic-section-py,var(--cosmic-space-section-y,100px))!important;padding-bottom:var(--cosmic-section-py,var(--cosmic-space-section-y,100px))!important;padding-left:var(--cosmic-section-px,var(--cosmic-space-section-x,28px))!important;padding-right:var(--cosmic-section-px,var(--cosmic-space-section-x,28px))!important}}.entry-template-runtime{position:relative;isolation:isolate;background:var(--bg)}.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button)[class*='bg-primary'],.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button)[class*='bg-[var(--p)]'],.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button).cosmic-brand-bg{background:var(--p)!important;background-color:var(--p)!important;color:#fff!important;-webkit-text-fill-color:#fff!important;border-color:var(--p)!important}.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button)[class*='bg-primary'] *,.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button)[class*='bg-[var(--p)]'] *,.entry-template-runtime[data-cosmic-dynamic-page-style='clean'] :is(a,button).cosmic-brand-bg *{color:#fff!important;-webkit-text-fill-color:#fff!important}body#cosmic-published-page main{padding-top:0!important}body#cosmic-published-page main>.entry-template-runtime:first-child{margin-top:0!important;padding-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='false'] [data-cosmic-dynamic-single]>:first-child{margin-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='false'] [data-cosmic-mini-hero='true']{margin-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='true'].cosmic-static-overlay-first-spark{padding-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='true'] [data-cosmic-dynamic-single]>:first-child{padding-top:0!important}.entry-template-runtime[data-cosmic-header-overlay='true'] [data-cosmic-mini-hero='true']{padding-top:calc(var(--cosmic-overlay-header-height,80px) + clamp(3.25rem,5vw,5.5rem))!important}.entry-template-runtime[data-cosmic-header-overlay='false'] [data-cosmic-mini-hero='true']{scroll-margin-top:1.5rem}.entry-template-runtime[data-cosmic-first-surface='primary'][data-cosmic-mini-banner-image='false'] [data-cosmic-mini-hero='true']{background:var(--p)!important;background-image:none!important;box-shadow:0 0 0 100vmax var(--p);clip-path:inset(0 -100vmax);border-color:transparent!important;color:#fff!important}.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true']{color:#fff!important}.entry-template-runtime[data-cosmic-first-surface='white'][data-cosmic-mini-banner-image='false'] [data-cosmic-mini-hero='true']{background:var(--bg)!important;background-image:none!important;box-shadow:0 0 0 100vmax var(--bg);clip-path:inset(0 -100vmax)}.entry-template-runtime[data-cosmic-first-surface='surface'][data-cosmic-mini-banner-image='false'] [data-cosmic-mini-hero='true']{background:var(--s)!important;background-image:none!important;box-shadow:0 0 0 100vmax var(--s);clip-path:inset(0 -100vmax)}.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] h3{color:#fff!important}.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] p,.entry-template-runtime[data-cosmic-first-surface='primary'] [data-cosmic-mini-hero='true'] [data-cosmic-tags='true']{color:rgba(255,255,255,.82)!important}.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'],.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true']{color:var(--t)!important}.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-first-surface='white'] [data-cosmic-mini-hero='true'] h3,.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-first-surface='surface'] [data-cosmic-mini-hero='true'] h3{color:var(--t)!important}@media(max-width:640px){.entry-template-runtime[data-cosmic-header-overlay='true'] [data-cosmic-mini-hero='true']{padding-top:calc(var(--cosmic-overlay-header-height,72px) + 2.75rem)!important}}.entry-template-runtime{width:100%;max-width:100%;overflow-x:clip}.entry-template-runtime [data-cosmic-dynamic-single]{width:100%;max-width:100%;overflow:visible}.entry-template-runtime [data-cosmic-mini-hero='true']{box-sizing:border-box}.entry-template-runtime[data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true']{position:relative;isolation:isolate;overflow:hidden;background-image:linear-gradient(color-mix(in srgb,var(--p) 74%,transparent),color-mix(in srgb,var(--p) 74%,transparent)),var(--cosmic-mini-banner-image)!important;background-size:cover!important;background-position:center!important;color:#fff!important;border-radius:0!important;padding-left:clamp(1.25rem,4vw,3.5rem)!important;padding-right:clamp(1.25rem,4vw,3.5rem)!important;padding-bottom:clamp(2.5rem,5vw,5rem)!important}.entry-template-runtime[data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h3,.entry-template-runtime[data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] p{color:#fff!important}[data-cosmic-dynamic-single]{width:100%;overflow:visible}[data-cosmic-dynamic-single] [data-cosmic-richtext]{font-size:1.0625rem;line-height:1.88;text-wrap:pretty}[data-cosmic-dynamic-single] [data-cosmic-richtext]>:first-child{margin-top:0}[data-cosmic-dynamic-single] [data-cosmic-richtext]>:last-child{margin-bottom:0}[data-cosmic-dynamic-single] [data-cosmic-richtext] p{margin:0 0 1.35em}[data-cosmic-dynamic-single] [data-cosmic-richtext] h2{font-size:clamp(1.75rem,3vw,2.35rem);line-height:1.15;margin:1.8em 0 .65em;letter-spacing:-.035em}[data-cosmic-dynamic-single] [data-cosmic-richtext] h3{font-size:clamp(1.35rem,2.4vw,1.75rem);line-height:1.22;margin:1.6em 0 .6em;letter-spacing:-.025em}[data-cosmic-dynamic-single] [data-cosmic-richtext] ul,[data-cosmic-dynamic-single] [data-cosmic-richtext] ol{margin:1.2em 0;padding-left:1.4em}[data-cosmic-dynamic-single] [data-cosmic-richtext] li{margin:.45em 0}[data-cosmic-dynamic-single] [data-cosmic-richtext] blockquote{margin:1.6em 0;padding:1rem 1.25rem;border-left:4px solid currentColor;border-radius:0 1rem 1rem 0;background:rgba(148,163,184,.09);font-size:1.08em;font-weight:500}[data-cosmic-dynamic-single] [data-cosmic-richtext] a{text-decoration:underline;text-decoration-thickness:.08em;text-underline-offset:.18em}[data-cosmic-dynamic-single] [data-cosmic-richtext] img{height:auto;border-radius:1.25rem;margin:1.6rem auto}[data-cosmic-dynamic-single] [data-cosmic-richtext] figcaption{margin-top:.65rem;text-align:center;font-size:.82rem;opacity:.72}[data-cosmic-dynamic-single] [data-cosmic-richtext] pre{overflow:auto;border-radius:1rem;padding:1rem 1.1rem;background:#0f172a;color:#e2e8f0;font-size:.9rem;line-height:1.7}[data-cosmic-dynamic-single] [data-cosmic-richtext] table{display:block;width:100%;overflow-x:auto;border-collapse:collapse;margin:1.7rem 0}[data-cosmic-dynamic-single] [data-cosmic-richtext] th,[data-cosmic-dynamic-single] [data-cosmic-richtext] td{padding:.8rem .9rem;border:1px solid rgba(148,163,184,.28);text-align:left}[data-cosmic-gallery] figure{aspect-ratio:4/3}[data-cosmic-gallery] img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease}[data-cosmic-gallery] figure:hover img{transform:scale(1.025)}@media(max-width:640px){[data-cosmic-dynamic-single] [data-cosmic-richtext]{font-size:1rem;line-height:1.8}}.entry-template-runtime[data-cosmic-dynamic-page-style='clean'][data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true']{background-image:linear-gradient(rgba(255,255,255,.90),rgba(255,255,255,.90)),var(--cosmic-mini-banner-image)!important;color:var(--t)!important}.entry-template-runtime[data-cosmic-dynamic-page-style='clean'][data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h1,.entry-template-runtime[data-cosmic-dynamic-page-style='clean'][data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h2,.entry-template-runtime[data-cosmic-dynamic-page-style='clean'][data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] h3{color:var(--t)!important}.entry-template-runtime[data-cosmic-dynamic-page-style='clean'][data-cosmic-mini-banner-image='true'] [data-cosmic-mini-hero='true'] p{color:var(--m)!important}
body#cosmic-published-page[data-cosmic-page-style='clean']{background:#fff!important;color:#0f172a!important}body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button).cosmic-primary-cta,body#cosmic-published-page[data-cosmic-page-style='clean'] .entry-template-runtime :is(a,button).cosmic-primary-cta,body#cosmic-published-page[data-cosmic-page-style='clean'] [data-cosmic-dynamic-single] :is(a,button).cosmic-primary-cta{background:var(--p,var(--cosmic-primary,#243447))!important;background-color:var(--p,var(--cosmic-primary,#243447))!important;border-color:var(--p,var(--cosmic-primary,#243447))!important;color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important}body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button).cosmic-primary-cta *{color:#fff!important;-webkit-text-fill-color:#fff!important}
/* Page Style owns page content only. Header/footer variants own the website shell. */
body#cosmic-published-page[data-cosmic-page-style='clean'] main{background:#fff!important;color:#0f172a!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button)[class*='bg-[#'],body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button)[class*='bg-primary'],body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button)[class*='bg-[var(--p)]'],body#cosmic-published-page[data-cosmic-page-style='clean'] main :is(a,button).cosmic-brand-bg{background:var(--p)!important;background-color:var(--p)!important;border-color:var(--p)!important;color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner']{background-color:#fff!important;color:#0f172a!important}
/* Clean hero descendant contract: neutralize legacy white-text utilities without changing media/cards. */
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] :is(h1,h2,h3,h4,h5,h6,p,span,small,strong,em)[class*='text-white'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] :is(h1,h2,h3,h4,h5,h6,p,span,small,strong,em)[class*='text-white']{color:#0f172a!important;-webkit-text-fill-color:#0f172a!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] :is(h1,h2,h3,h4,h5,h6),body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] :is(h1,h2,h3,h4,h5,h6){color:#0f172a!important;-webkit-text-fill-color:#0f172a!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] :is(p,small)[class*='text-white'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] :is(p,small)[class*='text-white']{color:#475569!important;-webkit-text-fill-color:#475569!important;opacity:1!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] :is(a,button)[class*='bg-white'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] :is(a,button)[class*='bg-white']{background:var(--p)!important;border-color:var(--p)!important;color:#fff!important;-webkit-text-fill-color:#fff!important;opacity:1!important}body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] :is(a,button)[class*='border-white'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] :is(a,button)[class*='border-white']{background:#fff!important;border-color:#cbd5e1!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important;opacity:1!important}
body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] a[class*='bg-primary'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type^='hero_'] a[class*='bg-[var(--p)]'],body#cosmic-published-page[data-cosmic-page-style='clean'] main section[data-cosmic-block-type='image_cta_banner'] a[class*='bg-primary']{color:#fff!important;-webkit-text-fill-color:#fff!important}\n/* Hotfix: Clean page style must not wash out media-led heroes.\n   Keep Clean's light treatment for content-first heroes, while photo/video heroes\n   retain their contrast-safe dark media overlay and white foreground. */\nbody#cosmic-published-page[data-cosmic-page-style='clean'] main :is(\nsection[data-cosmic-block-type='hero_slider_fade'],\nsection[data-cosmic-block-type='hero_background_image'],\nsection[data-cosmic-block-type='hero_editorial_overlay'],\nsection[data-cosmic-block-type='hero_parallax'],\nsection[data-cosmic-block-type='hero_video_background'],\nsection[data-cosmic-block-type='hero_video_style'],\nsection[data-cosmic-block-type='hero_video_premium'],\nsection[data-cosmic-block-type='hero_luxury_fullscreen']\n){color:#fff!important}\nbody#cosmic-published-page[data-cosmic-page-style='clean'] main :is(\nsection[data-cosmic-block-type='hero_slider_fade'],\nsection[data-cosmic-block-type='hero_background_image'],\nsection[data-cosmic-block-type='hero_editorial_overlay'],\nsection[data-cosmic-block-type='hero_parallax'],\nsection[data-cosmic-block-type='hero_video_background'],\nsection[data-cosmic-block-type='hero_video_style'],\nsection[data-cosmic-block-type='hero_video_premium'],\nsection[data-cosmic-block-type='hero_luxury_fullscreen']\n) :is(h1,h2,h3,h4,h5,h6){color:#fff!important;-webkit-text-fill-color:#fff!important}\nbody#cosmic-published-page[data-cosmic-page-style='clean'] main :is(\nsection[data-cosmic-block-type='hero_slider_fade'],\nsection[data-cosmic-block-type='hero_background_image'],\nsection[data-cosmic-block-type='hero_editorial_overlay'],\nsection[data-cosmic-block-type='hero_parallax'],\nsection[data-cosmic-block-type='hero_video_background'],\nsection[data-cosmic-block-type='hero_video_style'],\nsection[data-cosmic-block-type='hero_video_premium'],\nsection[data-cosmic-block-type='hero_luxury_fullscreen']\n) :is(p,small,span,strong,em)[class*='text-white']{color:rgba(255,255,255,.82)!important;-webkit-text-fill-color:currentColor!important;opacity:1!important}\nbody#cosmic-published-page[data-cosmic-page-style='clean'] main :is(\nsection[data-cosmic-block-type='hero_slider_fade'],\nsection[data-cosmic-block-type='hero_background_image'],\nsection[data-cosmic-block-type='hero_editorial_overlay'],\nsection[data-cosmic-block-type='hero_parallax'],\nsection[data-cosmic-block-type='hero_video_background'],\nsection[data-cosmic-block-type='hero_video_style'],\nsection[data-cosmic-block-type='hero_video_premium'],\nsection[data-cosmic-block-type='hero_luxury_fullscreen']\n) :is(a,button)[class*='bg-white']{background:#fff!important;background-color:#fff!important;border-color:#fff!important;color:#0f172a!important;-webkit-text-fill-color:#0f172a!important}\nbody#cosmic-published-page[data-cosmic-page-style='clean'] main :is(\nsection[data-cosmic-block-type='hero_slider_fade'],\nsection[data-cosmic-block-type='hero_background_image'],\nsection[data-cosmic-block-type='hero_editorial_overlay'],\nsection[data-cosmic-block-type='hero_parallax'],\nsection[data-cosmic-block-type='hero_video_background'],\nsection[data-cosmic-block-type='hero_video_style'],\nsection[data-cosmic-block-type='hero_video_premium'],\nsection[data-cosmic-block-type='hero_luxury_fullscreen']\n) :is(a,button)[class*='border-white']{background:rgba(0,0,0,.20)!important;border-color:rgba(255,255,255,.42)!important;color:#fff!important;-webkit-text-fill-color:#fff!important}\n

{$responsiveTypographyCss}</style>\n</head>\n<body id='cosmic-published-page' data-cosmic-page-style='{$pageStyle}' class='{$bodyBaseClass} min-h-screen m-0 p-0 flex flex-col'>\n{$header}\n<main class='w-full flex-grow'>{$body}</main>\n{$footer}
<script>
(function(){
  if (window.__cosmicAnalyticsLoaded) return;
  window.__cosmicAnalyticsLoaded = true;
  var endpoint = '/cosmic-cms/analytics.php';
  var startedAt = Date.now();
  var sentDuration = false;
  function send(payload){
    try {
      var body = JSON.stringify(payload || {});
      if (navigator.sendBeacon) {
        navigator.sendBeacon(endpoint, new Blob([body], {type:'application/json'}));
        return;
      }
      fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:body,keepalive:true,credentials:'same-origin'}).catch(function(){});
    } catch(e) {}
  }
  var initial = {page_views:1};
  try {
    var visitorKey = 'cosmic_visitor_v1';
    if (!localStorage.getItem(visitorKey)) { localStorage.setItem(visitorKey, String(Date.now())); initial.visitors = 1; }
  } catch(e) {}
  try {
    var sessionKey = 'cosmic_session_v1';
    if (!sessionStorage.getItem(sessionKey)) { sessionStorage.setItem(sessionKey, String(Date.now())); initial.sessions = 1; }
  } catch(e) { initial.sessions = 1; }
  send(initial);
  function finish(){
    if (sentDuration) return;
    sentDuration = true;
    var seconds = Math.max(1, Math.min(86400, Math.round((Date.now()-startedAt)/1000)));
    send({duration_seconds:seconds, engaged_sessions:seconds>=10?1:0});
  }
  window.addEventListener('pagehide', finish, {once:true});
  document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'hidden') finish(); });
})();
</script>
</body>
</html>";
}

function cosmicCleanUrlRules(): string
{
    return <<<'HTACCESS'
# Cosmic CMS clean static URLs
# This managed block is added by Cosmic CMS. Keep it if you want extensionless page URLs.
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Keep the homepage at the root URL.
    RewriteCond %{THE_REQUEST} \s/+index\.html[\s?] [NC]
    RewriteRule ^index\.html$ ./ [R=301,L,NE]

    # Redirect direct .html requests to their public extensionless URL.
    RewriteCond %{THE_REQUEST} \s/+(.+?)\.html[\s?] [NC]
    RewriteRule ^(.+)\.html$ /$1 [R=301,L,NE]

    # Backward compatibility for pages previously compiled with /cosmic-sync/.
    # If the legacy connector folder is not installed, route those requests to
    # the current /cosmic-cms/ connector without redirecting POST requests.
    RewriteCond %{DOCUMENT_ROOT}/cosmic-sync !-d
    RewriteRule ^cosmic-sync/(.*)$ cosmic-cms/$1 [L]

    # Keep real files, directories, and the deployment connector untouched.
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule ^(?:cosmic-cms|cosmic-sync)(?:/|$) - [L]

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

        $relativePath = cosmicOutputPath($page);
        $path = $siteRoot . DIRECTORY_SEPARATOR . $relativePath;
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the published page directory.');
        }
        $temporaryPath = $path . '.tmp';

        if (file_put_contents($temporaryPath, cosmicDocument($page, $package), LOCK_EX) === false || ! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to safely write the published page.');
        }

        $writtenFiles[] = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
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
    cosmicResponse(['status' => 'success', 'message' => 'Cosmic deployment connector is ready.', 'version' => '2.12.0-shared-local-tailwind']);
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
