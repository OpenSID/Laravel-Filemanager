<?php

namespace OpenSID\LaravelFilemanager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use OpenSID\LaravelFilemanager\Exceptions\UnsafePathException;
use OpenSID\LaravelFilemanager\Services\ClipboardManager;
use OpenSID\LaravelFilemanager\Services\FilesystemManager;
use OpenSID\LaravelFilemanager\Services\PathGuard;
use OpenSID\LaravelFilemanager\Support\FilemanagerConfig;

/**
 * Replaces rfm/dialog.php: bootstraps view/sort/filter session state,
 * lists the current directory, and renders the picker/browser UI.
 */
class DialogController extends Controller
{
    public function __construct(
        protected FilesystemManager $files,
        protected ClipboardManager $clipboard,
        protected FilemanagerConfig $config,
    ) {}

    public function show(Request $request)
    {
        $subdir = $this->resolveSubdir($request);
        $context = $this->resolveContext();

        $viewType = $this->rememberedOrRequested($request, 'view', 'filemanager.view_type', (int) config('filemanager.default_view', 0));
        $sortBy = $this->rememberedOrRequested($request, 'sort_by', 'filemanager.sort_by', 'name');
        $descending = (bool) $this->rememberedOrRequested($request, 'descending', 'filemanager.descending', true);
        $filter = (string) $request->query('filter', Session::get('filemanager.filter', ''));

        $fieldId = $this->sanitizeIdentifier((string) $request->query('field_id', ''));
        $editor = $this->sanitizeIdentifier((string) $request->query('editor', ''));
        $callback = $this->sanitizeIdentifier((string) $request->query('callback', ''));
        $type = (int) $request->query('type', 0);
        $multiple = $request->query('multiple');

        $entries = $this->files->list($subdir, $type);
        $entries = $this->sortEntries($entries, $sortBy, $descending);

        // Carried on every in-dialog navigation link (folder/breadcrumb/sort/
        // refresh) so the picker stays configured for the calling context
        // across the whole browsing session — mirrors rfm/dialog.php's
        // $get_params bridge.
        $linkParams = array_filter([
            'type' => $type ?: null,
            'field_id' => $fieldId ?: null,
            'multiple' => $multiple,
            'popup' => $request->query('popup'),
            'crossdomain' => $request->query('crossdomain'),
            'editor' => $editor ?: null,
            'relative_url' => $request->query('relative_url'),
            'callback' => $callback ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        $crossdomain = (bool) $request->query('crossdomain', false);

        $response = response(view('filemanager::dialog', [
            'subdir' => $subdir,
            'breadcrumbs' => $this->breadcrumbs($subdir),
            'entries' => $entries,
            'viewType' => $viewType,
            'sortBy' => $sortBy,
            'descending' => $descending,
            'filter' => $filter,
            'fieldId' => $fieldId,
            'type' => $type,
            'acceptTypes' => $this->config->acceptAttributeForType($type),
            'multiple' => $multiple,
            'popup' => (bool) $request->query('popup', false),
            'crossdomain' => $crossdomain,
            'targetOrigin' => $crossdomain ? $this->resolveTargetOrigin($request) : '',
            'callback' => $callback,
            'editor' => $editor,
            'relativeUrl' => (bool) $request->query('relative_url', false),
            'linkParams' => $linkParams,
            'clipboardHasContent' => $this->clipboard->hasContent(),
            'canUpload' => Gate::allows('filemanager.upload', $context),
            'canDelete' => Gate::allows('filemanager.delete', $context),
            'apply' => $this->resolveApplyFunction($type, $fieldId, $multiple),
        ]));

        return $this->withSecurityHeaders($response);
    }

    /**
     * Anti-clickjacking: only this app (plus config('filemanager.
     * allowed_origins')) may frame the dialog, so a foreign site can't
     * overlay a logged-in admin's picker and harvest the chosen file URL.
     * X-Frame-Options is only sent when no extra origin is allowed —
     * SAMEORIGIN would otherwise override the CSP list in older browsers.
     */
    protected function withSecurityHeaders(Response $response): Response
    {
        $origins = $this->config->allowedOrigins();

        $response->headers->set('Content-Security-Policy', trim("frame-ancestors 'self' ".implode(' ', $origins)));
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        if ($origins === []) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }

    /**
     * targetOrigin include.js passes to postMessage() in crossdomain mode
     * (replaces upstream rfm's "*", which leaked the picked file URL to
     * whatever page happened to host the dialog). The embedding page's
     * origin is taken from Referer and only honoured when it is on the
     * allow-list; with a single allowed origin that one is used even
     * without a Referer. Empty string = include.js falls back to the
     * dialog's own origin, so a non-allowed parent simply never receives
     * the message.
     */
    protected function resolveTargetOrigin(Request $request): string
    {
        $allowed = $this->config->allowedOrigins();
        $referer = (string) $request->headers->get('referer', '');
        $parts = parse_url($referer);

        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            $origin = strtolower($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''));

            if (in_array($origin, $allowed, true)) {
                return $origin;
            }
        }

        return count($allowed) === 1 ? $allowed[0] : '';
    }

    protected function sanitizeIdentifier(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-\.\[\]]/', '', $value) ?? '';
    }

    /**
     * The calling admin module's slug (e.g. "surat", "web"), used for
     * per-module Gate checks (see AuthServiceProvider::bootFilemanagerGates
     * in the host app). This is intentionally NOT read from the request —
     * a query param here would let anyone claim any module slug and, since
     * every module shares the same underlying disk, inherit upload/delete
     * rights from whichever module they happen to already have edit access
     * to elsewhere in the app. Instead it's stamped into the session
     * server-side by filemanager_authorize(), called from within the
     * embedding page's own Blade view — which only executes because that
     * page's own access control already let the request through — so it
     * can't be forged by editing the filemanager URL directly.
     */
    protected function resolveContext(): ?string
    {
        return Session::get('filemanager.context');
    }

    /**
     * The "apply" function name include.js binds to each file's click
     * handler (apply_img / apply_link / apply_video / apply_multiple /
     * apply / apply_none) — controls what happens when a file is picked
     * (insert into TinyMCE, postMessage to the opener, etc). Ported from
     * rfm/dialog.php's identical $apply/$apply_type computation.
     */
    protected function resolveApplyFunction(int $type, ?string $fieldId, mixed $multiple): string
    {
        if ($multiple) {
            return 'apply_multiple';
        }

        return match (true) {
            $type === 1 => 'apply_img',
            $type === 2 => 'apply_link',
            $type === 0 && ! $fieldId => 'apply_none',
            $type === 3 => 'apply_video',
            default => 'apply',
        };
    }

    protected function resolveSubdir(Request $request): string
    {
        $baseFolder = $this->files->config()->baseFolder();
        $defaultFldr = $baseFolder !== '' ? $baseFolder : '';

        // If fldr is explicitly provided in the query string (e.g. ?fldr= or ?fldr=folder),
        // use it directly rather than falling back to the previous session value.
        if ($request->has('fldr') || array_key_exists('fldr', $request->query())) {
            $fldr = (string) ($request->query('fldr') ?? '');
        } else {
            $fldr = (string) Session::get('filemanager.fldr', $defaultFldr);
        }

        $fldr = trim($fldr, '/');

        if ($fldr === '' && $baseFolder !== '') {
            $fldr = $baseFolder;
        }

        try {
            PathGuard::assertSafe($fldr);
            PathGuard::assertInsideBaseFolder($fldr, $baseFolder);
        } catch (UnsafePathException) {
            $fldr = $defaultFldr;
        }

        if ($fldr !== '' && ! $this->files->isDirectory($fldr)) {
            $fldr = $defaultFldr;
        }

        Session::put('filemanager.fldr', $fldr);

        return $fldr;
    }

    protected function rememberedOrRequested(Request $request, string $queryKey, string $sessionKey, mixed $default): mixed
    {
        if ($request->has($queryKey)) {
            $value = $request->query($queryKey);
            Session::put($sessionKey, $value);

            return $value;
        }

        return Session::get($sessionKey, $default);
    }

    protected function sortEntries(array $entries, string $sortBy, bool $descending): array
    {
        usort($entries, function (array $a, array $b) use ($sortBy, $descending): int {
            if ($a['is_dir'] !== $b['is_dir']) {
                return $a['is_dir'] ? -1 : 1;
            }

            $result = match ($sortBy) {
                'date' => $a['date'] <=> $b['date'],
                'size' => ($a['size'] ?? 0) <=> ($b['size'] ?? 0),
                'extension' => ($a['extension'] ?? '') <=> ($b['extension'] ?? ''),
                default => mb_strtolower($a['name']) <=> mb_strtolower($b['name']),
            };

            return $descending ? -$result : $result;
        });

        return $entries;
    }

    protected function breadcrumbs(string $subdir): array
    {
        $baseFolder = $this->files->config()->baseFolder();

        if ($subdir === '' || $subdir === $baseFolder) {
            return [];
        }

        $relativeSubdir = $baseFolder !== '' && str_starts_with($subdir, $baseFolder.'/')
            ? substr($subdir, strlen($baseFolder) + 1)
            : $subdir;

        $segments = explode('/', $relativeSubdir);
        $crumbs = [];
        $accumulated = $baseFolder;

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $accumulated = trim($accumulated.'/'.$segment, '/');
            $crumbs[] = ['name' => $segment, 'path' => $accumulated];
        }

        return $crumbs;
    }
}
