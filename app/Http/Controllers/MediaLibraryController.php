<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MediaLibraryController extends Controller
{
    private const IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'image/avif', 'image/heic', 'image/heif',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'heif'];

    public function index(Request $request, Website $website)
    {
        $this->authorize('view', $website);

        $filters = $request->validate([
            'folder_id' => ['nullable', 'integer'],
            'source' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc', 'size_asc', 'size_desc'])],
            'per_page' => ['nullable', 'integer', 'min:12', 'max:120'],
            'recent' => ['nullable', 'boolean'],
        ]);

        if (! empty($filters['folder_id'])) {
            $this->folderForWebsite($website, (int) $filters['folder_id']);
        }

        $query = $website->mediaAssets()->with('folder:id,name,parent_id');

        if (array_key_exists('folder_id', $filters)) {
            $query->where('folder_id', $filters['folder_id'] ?: null);
        }
        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (! empty($filters['recent'])) {
            $query->where('created_at', '>=', now()->subDays(30));
        }
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $query->where(function ($q) use ($term) {
                $q->where('original_name', 'like', '%'.$term.'%')
                    ->orWhere('alt_text', 'like', '%'.$term.'%')
                    ->orWhere('caption', 'like', '%'.$term.'%');
            });
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'name_asc' => $query->orderBy('original_name'),
            'name_desc' => $query->orderByDesc('original_name'),
            'size_asc' => $query->orderBy('size_bytes'),
            'size_desc' => $query->orderByDesc('size_bytes'),
            default => $query->latest(),
        };

        $assets = $query->paginate((int) ($filters['per_page'] ?? 48));
        $assets->through(fn (MediaAsset $asset) => $this->assetPayload($asset));

        return response()->json([
            'folders' => $website->mediaFolders()
                ->withCount(['children', 'assets'])
                ->orderBy('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (MediaFolder $folder) => $this->folderPayload($folder)),
            'assets' => $assets,
            'stats' => [
                'total' => $website->mediaAssets()->count(),
                'recent' => $website->mediaAssets()->where('created_at', '>=', now()->subDays(30))->count(),
                'uncategorized' => $website->mediaAssets()->whereNull('folder_id')->count(),
                'uploads' => $website->mediaAssets()->where('source', 'upload')->count(),
                'ai_generated' => $website->mediaAssets()->where('source', 'ai')->count(),
                'unsplash' => $website->mediaAssets()->where('source', 'unsplash')->count(),
            ],
        ]);
    }

    public function storeFolder(Request $request, Website $website)
    {
        $this->authorize('editBuilder', $website);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $name = $this->cleanFolderName($data['name']);
        $parentId = ! empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId) {
            $this->folderForWebsite($website, $parentId);
        }
        $this->assertFolderNameAvailable($website, $name, $parentId);

        $folder = $website->mediaFolders()->create([
            'parent_id' => $parentId,
            'created_by' => $request->user()->id,
            'name' => $name,
            'sort_order' => ((int) $website->mediaFolders()->where('parent_id', $parentId)->max('sort_order')) + 10,
        ]);

        return response()->json(['folder' => $this->folderPayload($folder->loadCount(['children', 'assets']))], 201);
    }

    public function updateFolder(Request $request, Website $website, MediaFolder $folder)
    {
        $this->authorize('editBuilder', $website);
        $this->assertFolderWebsite($website, $folder);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $parentId = array_key_exists('parent_id', $data) ? ($data['parent_id'] ? (int) $data['parent_id'] : null) : $folder->parent_id;
        if ($parentId) {
            abort_if($parentId === (int) $folder->id, 422, 'A folder cannot be moved inside itself.');
            $parent = $this->folderForWebsite($website, $parentId);
            abort_if($this->isDescendant($folder, $parent), 422, 'A folder cannot be moved inside one of its child folders.');
        }

        $name = array_key_exists('name', $data) ? $this->cleanFolderName($data['name']) : $folder->name;
        $this->assertFolderNameAvailable($website, $name, $parentId, $folder->id);

        $folder->fill([
            'name' => $name,
            'parent_id' => $parentId,
            'sort_order' => $data['sort_order'] ?? $folder->sort_order,
        ])->save();

        return response()->json(['folder' => $this->folderPayload($folder->fresh()->loadCount(['children', 'assets']))]);
    }

    public function destroyFolder(Request $request, Website $website, MediaFolder $folder)
    {
        $this->authorize('editBuilder', $website);
        $this->assertFolderWebsite($website, $folder);

        if ($folder->children()->exists() || $folder->assets()->exists()) {
            throw ValidationException::withMessages([
                'folder' => 'This folder is not empty. Move or delete its contents before deleting the folder.',
            ]);
        }

        $folder->delete();
        return response()->json(['deleted' => true]);
    }

    public function upload(Request $request, Website $website)
    {
        $this->authorize('editBuilder', $website);
        $data = $request->validate([
            'image' => ['required', 'file', 'max:12288', 'mimetypes:'.implode(',', self::IMAGE_MIMES)],
            'folder_id' => ['nullable', 'integer'],
            'source' => ['nullable', Rule::in(['upload', 'ai', 'unsplash', 'import'])],
            'kind' => ['nullable', 'string', 'max:64'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:2000'],
        ]);

        $folderId = ! empty($data['folder_id']) ? (int) $data['folder_id'] : null;
        if ($folderId) {
            $this->folderForWebsite($website, $folderId);
        }

        $file = $data['image'];
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['image' => 'The uploaded image could not be read. Please choose the file again.']);
        }

        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            throw ValidationException::withMessages(['image' => 'Use JPG, PNG, WebP, GIF, AVIF, HEIC or HEIF images.']);
        }

        $uuid = (string) Str::uuid();
        $filename = $uuid.'.'.$extension;
        $path = $file->storeAs("websites/{$website->id}/media-library", $filename, 'public');
        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages(['image' => 'The image could not be saved. Please try again.']);
        }

        [$width, $height] = $this->dimensions(Storage::disk('public')->path($path));
        $asset = $website->mediaAssets()->create([
            'uuid' => $uuid,
            'folder_id' => $folderId,
            'uploaded_by' => $request->user()->id,
            'source' => $data['source'] ?? 'upload',
            'kind' => $data['kind'] ?? null,
            'disk' => 'public',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName() ?: $filename, 255, ''),
            'filename' => $filename,
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => (int) ($file->getSize() ?: 0),
            'width' => $width,
            'height' => $height,
            'checksum_sha256' => @hash_file('sha256', Storage::disk('public')->path($path)) ?: null,
            'alt_text' => $data['alt_text'] ?? null,
            'caption' => $data['caption'] ?? null,
        ]);

        return response()->json(['asset' => $this->assetPayload($asset->load('folder:id,name,parent_id'))], 201);
    }

    public function updateAsset(Request $request, Website $website, MediaAsset $asset)
    {
        $this->authorize('editBuilder', $website);
        $this->assertAssetWebsite($website, $asset);
        $data = $request->validate([
            'folder_id' => ['sometimes', 'nullable', 'integer'],
            'original_name' => ['sometimes', 'required', 'string', 'max:255'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('folder_id', $data) && $data['folder_id']) {
            $this->folderForWebsite($website, (int) $data['folder_id']);
        }

        $asset->fill($data)->save();
        return response()->json(['asset' => $this->assetPayload($asset->fresh()->load('folder:id,name,parent_id'))]);
    }

    public function destroyAsset(Request $request, Website $website, MediaAsset $asset)
    {
        $this->authorize('editBuilder', $website);
        $this->assertAssetWebsite($website, $asset);

        // Patch 1 uses soft delete first. Physical cleanup can be added with Trash/Restore UI
        // later, preventing an accidental library action from breaking a published page.
        $asset->delete();
        return response()->json(['deleted' => true]);
    }

    public function show(Website $website, MediaAsset $asset)
    {
        $this->assertAssetWebsite($website, $asset);
        abort_unless(Storage::disk($asset->disk)->exists($asset->path), 404);

        return Storage::disk($asset->disk)->response($asset->path, $asset->filename, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function assetPayload(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'uuid' => $asset->uuid,
            'website_id' => $asset->website_id,
            'folder_id' => $asset->folder_id,
            'folder' => $asset->folder ? [
                'id' => $asset->folder->id,
                'name' => $asset->folder->name,
                'parent_id' => $asset->folder->parent_id,
            ] : null,
            'source' => $asset->source,
            'kind' => $asset->kind,
            'original_name' => $asset->original_name,
            'filename' => $asset->filename,
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'size_bytes' => $asset->size_bytes,
            'width' => $asset->width,
            'height' => $asset->height,
            'alt_text' => $asset->alt_text,
            'caption' => $asset->caption,
            'url' => route('media-library.assets.show', ['website' => $asset->website_id, 'asset' => $asset->uuid], false),
            'created_at' => optional($asset->created_at)->toISOString(),
            'updated_at' => optional($asset->updated_at)->toISOString(),
        ];
    }

    private function folderPayload(MediaFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'website_id' => $folder->website_id,
            'parent_id' => $folder->parent_id,
            'name' => $folder->name,
            'sort_order' => $folder->sort_order,
            'children_count' => (int) ($folder->children_count ?? $folder->children()->count()),
            'assets_count' => (int) ($folder->assets_count ?? $folder->assets()->count()),
            'created_at' => optional($folder->created_at)->toISOString(),
            'updated_at' => optional($folder->updated_at)->toISOString(),
        ];
    }

    private function folderForWebsite(Website $website, int $folderId): MediaFolder
    {
        return $website->mediaFolders()->whereKey($folderId)->firstOrFail();
    }

    private function assertFolderWebsite(Website $website, MediaFolder $folder): void
    {
        abort_unless((int) $folder->website_id === (int) $website->id, 404);
    }

    private function assertAssetWebsite(Website $website, MediaAsset $asset): void
    {
        abort_unless((int) $asset->website_id === (int) $website->id, 404);
    }

    private function cleanFolderName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '' || in_array($name, ['.', '..'], true)) {
            throw ValidationException::withMessages(['name' => 'Enter a valid folder name.']);
        }
        return $name;
    }

    private function assertFolderNameAvailable(Website $website, string $name, ?int $parentId, ?int $ignoreId = null): void
    {
        $query = $website->mediaFolders()->where('parent_id', $parentId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => 'A folder with this name already exists here.']);
        }
    }

    private function isDescendant(MediaFolder $folder, MediaFolder $possibleDescendant): bool
    {
        $current = $possibleDescendant;
        $guard = 0;
        while ($current->parent_id && $guard++ < 100) {
            if ((int) $current->parent_id === (int) $folder->id) {
                return true;
            }
            $current = MediaFolder::query()->find($current->parent_id);
            if (! $current) {
                break;
            }
        }
        return false;
    }

    private function dimensions(string $absolutePath): array
    {
        try {
            $size = @getimagesize($absolutePath);
            return is_array($size) ? [(int) ($size[0] ?? 0) ?: null, (int) ($size[1] ?? 0) ?: null] : [null, null];
        } catch (\Throwable) {
            return [null, null];
        }
    }
}
