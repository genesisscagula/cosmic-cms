<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Services\DeploymentConnectorArchive;
use App\Services\PagePublisher;
use App\Services\WebsiteTemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Illuminate\Support\Str;
use ZipArchive;

class WebsiteController extends Controller
{
	public function index(Request $request)
	{
        $user = $request->user();

        if ($user->isClient()) {
            return Inertia::render('Client/Dashboard', [
                'websites' => Website::query()
                    ->where('user_id', $user->id)
                    ->latest('updated_at')
                    ->get(),
            ]);
        }

        $websites = $user->isPlatformOwner()
            ? Website::query()
                ->whereHas('workspace', fn ($query) => $query->where('owner_user_id', $user->id))
                ->latest('updated_at')
                ->get()
            : Website::query()->where('user_id', $user->id)->latest('updated_at')->get();

	    return Inertia::render('Dashboard/Dashboard', [
		    'websites' => $websites,
            'accessMode' => $user->isPlatformOwner() ? 'platform_owner' : 'customer',
		]);
	}

    public function store(Request $request, WebsiteTemplateCatalog $templates)
	{
	    $request->validate([
	        'name' => 'required|string|max:255',
	        'domain' => 'required|url',
	        'industry' => 'required|string|max:120',
	        'location' => 'required|string|max:255',
	        'business_description' => 'required|string|max:2000',
	        'theme_settings' => 'nullable|array', // I-validate ang array input
	        'template' => 'nullable|string',
	    ]);

	    $template = $request->input('template');

	    if ($template && ! $templates->supports($template)) {
	        return back()->withErrors(['template' => 'The selected website template is not available.']);
	    }

	    $defaults = [
	        'name' => $request->name,
	        'domain' => $request->domain,
	        'industry' => $request->input('industry'),
	        'location' => $request->input('location'),
	        'business_description' => $request->input('business_description'),
	        // Until a dedicated website settings screen is added, new live-form
	        // inquiries go to the account that created the website.
	        'contact_email' => $request->user()->email,
	        'api_token' => Str::random(60),
	        // Keep the current named theme contract for new websites.
	        'theme_settings' => $request->input('theme_settings', [
                'primary' => 'midnight',
	            'secondary' => 'white',
	            'tertiary' => 'stone',
	            'auto' => true,
	        ]),
	        'global_header' => [
	            'type' => 'glassmorphism_header',
	            'logo_text' => $request->name,
	            'cta_label' => 'Get Started',
	            'cta_url' => '#',
	            'menu' => [
	                ['label' => 'Home', 'url' => 'home'],
	                ['label' => 'About', 'url' => '#'],
	                ['label' => 'Services', 'url' => '#'],
	            ],
	        ],
	        'global_footer' => [
	            'type' => 'minimal_footer',
	            'logo_text' => $request->name,
	            'copyright' => '© ' . now()->year . '. All rights reserved.',
	        ],
	    ];

	    $website = DB::transaction(function () use ($request, $template, $templates, $defaults) {
	        $templateAttributes = $template
	            ? $templates->websiteAttributes($template, $request->name)
	            : [];

	        $workspace = $request->user()->ownedWorkspaces()->first();

            $website = Website::create([
                'user_id' => $request->user()->id,
                'workspace_id' => $workspace?->id,
	            ...$defaults,
	            ...$templateAttributes,
	        ]);

	        if ($template) {
	            foreach ($templates->pages($template) as $page) {
	                $website->pages()->create($page);
	            }
	        }

	        return $website;
	    });

	    return redirect()->route('pages.index', $website);
	}

    public function destroy(Website $website)
    {
        $this->authorize('delete', $website);

        $website->delete();

        // Inertia must follow a DELETE response with a GET request. A 302 can
        // preserve the original DELETE method and incorrectly hit /dashboard.
        return redirect()->route('dashboard', [], 303)->with('success', 'Website deleted.');
    }

    public function updateSettings(Request $request, Website $website)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['nullable', 'url', 'max:2048'],
            'contact_email' => ['nullable', 'email', 'max:254'],
        ]);

        $website->update($validated);

        return response()->json([
            'status' => 'success',
            'website' => $website->fresh(),
        ]);
    }

    public function updateProfile(Request $request, Website $website)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'industry' => ['required', 'string', 'max:120'],
            'location' => ['required', 'string', 'max:255'],
            'business_description' => ['required', 'string', 'max:2000'],
        ]);

        $website->update($validated);

        return response()->json([
            'status' => 'success',
            'website' => $website->fresh(),
        ]);
    }

    public function downloadDeploymentConnector(Website $website, DeploymentConnectorArchive $connector)
    {
        $this->authorize('update', $website);

        if (! filter_var($website->domain, FILTER_VALIDATE_URL)) {
            return back()->withErrors(['domain' => 'Add a valid website domain before downloading its deployment connector.']);
        }

        if (! $website->deployment_secret) {
            $website->deployment_secret = Str::random(64);
            $website->save();
        }

        $archivePath = $connector->create($website);

        return response()
            ->download($archivePath, 'cosmic-sync-' . Str::slug($website->name) . '.zip')
            ->deleteFileAfterSend(true);
    }

    public function verifyDeploymentConnector(Website $website)
    {
        $this->authorize('update', $website);

        if ($error = $this->verifyConnector($website)) {
            return response()->json(['message' => $error], 422);
        }

        return response()->json([
            'status' => 'connected',
            'message' => 'Live site connector verified successfully.',
        ]);
    }

    public function pushLiveUpdate(Website $website, PagePublisher $publisher)
    {
        $this->authorize('update', $website);

        if (! $website->deployment_verified_at && $this->verifyConnector($website)) {
            return response()->json([
                'message' => $website->deployment_error ?? 'The live site connector could not be verified before pushing this update.',
            ], 422);
        }

        $package = $publisher->publishedPackage($website);

        if ($package['pages'] === []) {
            return response()->json(['message' => 'Publish at least one page before pushing a live update.'], 422);
        }

        $endpoint = rtrim((string) $website->domain, '/') . '/cosmic-sync/sync.php?action=receive_package';

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders(['X-Cosmic-Sync-Secret' => $website->deployment_secret])
                ->post($endpoint, $package);

            if (! $response->successful() || $response->json('status') !== 'success') {
                $website->update(['deployment_error' => 'The live site did not accept this update. Your existing live files were not changed.']);

                return response()->json(['message' => $website->deployment_error], 422);
            }
        } catch (\Throwable $exception) {
            report($exception);
            $website->update(['deployment_error' => 'The live site could not be reached. Your existing live files were not changed.']);

            return response()->json(['message' => $website->deployment_error], 422);
        }

        $website->update([
            'last_deployed_at' => now(),
            'deployment_error' => null,
        ]);

        return response()->json([
            'status' => 'deployed',
            'message' => 'Published pages were pushed to the live site.',
            'files' => $response->json('files', []),
        ]);
    }

    /**
     * Verify the installed connector before an explicit connection or a live
     * push. Returning an error message keeps both flows consistent.
     */
    private function verifyConnector(Website $website): ?string
    {
        if (! filter_var($website->domain, FILTER_VALIDATE_URL)) {
            return 'Add a valid website domain before connecting a live site.';
        }

        if (! $website->deployment_secret) {
            return 'Download and install this website\'s deployment connector before connecting it.';
        }

        $endpoint = rtrim((string) $website->domain, '/') . '/cosmic-sync/sync.php?action=verify';

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['X-Cosmic-Sync-Secret' => $website->deployment_secret])
                ->get($endpoint);

            if (! $response->successful() || $response->json('status') !== 'success') {
                $error = 'The deployment connector could not be verified at the configured domain.';
                $website->update([
                    'deployment_verified_at' => null,
                    'deployment_error' => $error,
                ]);

                return $error;
            }
        } catch (\Throwable $exception) {
            report($exception);

            $error = 'The deployment connector could not be reached. Check the domain, Apache, and connector folder.';
            $website->update([
                'deployment_verified_at' => null,
                'deployment_error' => $error,
            ]);

            return $error;
        }

        $website->update([
            'deployment_verified_at' => now(),
            'deployment_error' => null,
        ]);

        return null;
    }

	public function saveFooter(Request $request, Website $website)
	{
	    $this->authorize('update', $website);

	    $request->validate([
	        'footer_block' => 'required|array'
	    ]);

	    $website->global_footer = $request->footer_block;
	    $website->published_global_footer = $request->footer_block;
	    $website->save();
	    
	    return response()->json(['status' => 'success', 'data' => $website->global_footer]);
	}

    public function downloadBridge()
    {
        $zipName = 'cosmic-client-bridge.zip';
        $storageDir = storage_path('app');
        $zipPath = $storageDir . '/' . $zipName;
        
        $tempDir = $storageDir . '/temp_bridge';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Kani ang advanced, dynamic template rendering script sa client side
        $indexPhp = '<?php
		define("CMS_API_URL", "' . url('/api/v1/sync') . '");

		if (file_exists("config.php")) {
		    include "config.php";
		    
		    if (file_exists("content.json")) {
		        $data = json_decode(file_get_contents("content.json"), true);
		        $pages = $data[\'pages\'] ?? [];
		        
		        // 1. Router Logic: Tan-awon unsa nga slug ang gi-request (Default kay ang unang page)
		        $currentSlug = $_GET[\'page\'] ?? ($pages[0][\'slug\'] ?? \'home\');
		        
		        // Find current page data
		        $currentPage = null;
		        foreach ($pages as $p) {
		            if ($p[\'slug\'] === $currentSlug) {
		                $currentPage = $p;
		                break;
		            }
		        }
		        
		        // HTML Frontend Layout Template View
		        echo "<!DOCTYPE html>
		        <html lang=\"en\">
		        <head>
		            <meta charset=\"UTF-8\">
		            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
		            <title>" . htmlspecialchars($data[\'website_name\'] ?? \'Cosmic Site\') . "</title>
		            <link rel=\"preconnect\" href=\"https://fonts.bunny.net\">
		            <link href=\"https://fonts.bunny.net/css?family=manrope:400,500,600,700,800&display=swap\" rel=\"stylesheet\">
		            <script src=\"https://cdn.tailwindcss.com\"></script>
		        </head>
		        <body class=\"bg-slate-50 text-slate-900 font-sans\">";
		        
		        // HEADER / DYNAMIC NAVIGATION BAR
		        echo "<header class=\"bg-white shadow-sm border-b border-slate-200 sticky top-0 z-50\">
		            <div class=\"max-w-6xl mx-auto px-4 py-4 flex justify-between items-center\">
		                <div class=\"font-bold text-xl text-indigo-600\">🚀 " . htmlspecialchars($data[\'website_name\']) . "</div>
		                <nav class=\"flex space-x-2\">";
		                foreach ($pages as $p) {
		                    $activeClass = ($p[\'slug\'] === $currentSlug) ? "bg-indigo-600 text-white" : "text-slate-600 hover:bg-slate-100";
		                    echo "<a href=\"?page=" . $p[\'slug\'] . "\" class=\"px-3 py-1.5 rounded-md text-sm font-medium transition {$activeClass}\">" . htmlspecialchars($p[\'title\']) . "</a>";
		                }
		                echo "<a href=\"?sync=true\" class=\"ml-4 px-3 py-1.5 bg-emerald-600 text-white rounded-md text-sm font-medium hover:bg-emerald-700 transition\">🔄 Sync</a>
		                </nav>
		            </div>
		        </header>";
		        
		        // DYNAMIC BLOCK COMPILER ENGINE
		        if ($currentPage) {
		            $blocks = $currentPage[\'blocks\'] ?? [];
		            foreach ($blocks as $block) {
		                if ($block[\'type\'] === \'hero\') {
		                    echo "<section class=\"py-20 text-center text-white shadow-inner\" style=\"background-color: {$block[\'bg_color\']};\">
		                        <div class=\"max-w-3xl mx-auto px-4\">
		                            <h1 class=\"text-4xl md:text-5xl font-extrabold tracking-tight mb-4\">" . htmlspecialchars($block[\'heading\']) . "</h1>
		                            <p class=\"text-lg md:text-xl text-indigo-200\">" . htmlspecialchars($block[\'subheading\']) . "</p>
		                        </div>
		                    </section>";
		                }
		                if ($block[\'type\'] === \'content\') {
		                    echo "<section class=\"py-16 max-w-3xl mx-auto px-4\">
		                        <div class=\"bg-white p-8 rounded-xl shadow-sm border border-slate-100\">
		                            <p class=\"text-lg leading-relaxed text-slate-700\">" . htmlspecialchars($block[\'text\']) . "</p>
		                        </div>
		                    </section>";
		                }
		            }
		        } else {
		            echo "<div class=\"text-center py-20\"><h1 class=\"text-2xl font-bold text-red-500\">404 - Page Not Found</h1></div>";
		        }
		        
		        echo "<footer class=\"bg-slate-800 text-slate-400 py-8 text-center text-sm border-t border-slate-700 mt-20\"><p>&copy; " . date(\'Y\') . " Powered by Cosmic Headless CMS Pipeline</p></footer></body></html>";
		        
		    } else {
		        echo "<h1>Connected to CMS!</h1><p>Use the sync button below to pull the latest website data.</p>";
		        echo "<br><a href=\"?sync=true\" style=\"padding:10px 20px; background:#10b981; color:#fff; text-decoration:none; border-radius:5px;\">🔄 Sync Content Now</a>";
		    }
		} else {
		    // Installer Form View
		    echo "
		    <div style=\"max-width:400px; margin:50px auto; font-family:Manrope, sans-serif; padding:20px; border:1px solid #ccc; border-radius:8px;\">
		        <h2>Cosmic CMS Client Bridge 🚀</h2>
		        <p style=\"font-size:13px; color:#666;\">Paste the token from your Cosmic Dashboard to sync pages and blocks.</p>
		        <form method=\"POST\">
		            <input type=\"text\" name=\"api_token\" placeholder=\"Paste CMS API Token\" style=\"width:100%; padding:8px; margin-bottom:10px;\" required><br>
		            <button type=\"submit\" style=\"width:100%; padding:10px; background:#4f46e5; color:white; border:none; border-radius:4px; cursor:pointer;\">Save & Initialize</button>
		        </form>
		    </div>";
		}

		if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["api_token"])) {
		    $configContent = "<?php\ndefine(\"API_TOKEN\", \"" . addslashes($_POST["api_token"]) . "\");\n";
		    file_put_contents("config.php", $configContent);
		    header("Location: index.php?sync=true");
		    exit;
		}

		if (isset($_GET["sync"]) && $_GET["sync"] == "true" && defined("API_TOKEN")) {
		    $ch = curl_init(CMS_API_URL);
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		    curl_setopt($ch, CURLOPT_HTTPHEADER, [
		        "X-Cosmic-Token: " . API_TOKEN,
		        "Accept: application/json"
		    ]);
		    $response = curl_exec($ch);
		    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		    curl_close($ch);
		    
		    if ($httpCode == 200 && $response) {
		        file_put_contents("content.json", $response);
		        echo "<script>alert(\"Sync Complete! Data updated successfully.\"); window.location.href=\"index.php\";</script>";
		    } else {
		        echo "<script>alert(\"Sync failed. Please verify your API token.\"); window.location.href=\"index.php\";</script>";
		    }
		}
		?>';

        file_put_contents($tempDir . '/index.php', $indexPhp);

        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        
        $cmd = "powershell -Command \"Compress-Archive -Path '{$tempDir}/*' -DestinationPath '{$zipPath}' -Force\"";
        exec($cmd);

        unlink($tempDir . '/index.php');
        rmdir($tempDir);

        if (file_exists($zipPath)) {
            return response()->download($zipPath)->deleteFileAfterSend(true);
        }

        return back()->with('error', 'Failed to generate ZIP file.');
    }
}
