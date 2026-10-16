<?php

namespace App\Modules\AI\Jobs;

use App\Modules\AI\Models\AiKbChunk;
use App\Modules\AI\Models\AiKbDocument;
use App\Modules\AI\Services\EmbeddingStore;
use App\Modules\AI\Services\Llm\LlmManager;
use App\Modules\AI\Services\LlmGateway;
use App\Modules\Integrations\Services\GoogleDriveService;
use App\Services\StorageManager;
use App\Support\PublicHttpUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\HTMLToMarkdown\HtmlConverter;
use Smalot\PdfParser\Parser;

class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $documentId) {}

    public function handle(
        LlmGateway $llm,
        EmbeddingStore $store,
        StorageManager $storage,
        ?GoogleDriveService $googleDrive = null,
    ): void {
        $doc = AiKbDocument::with(['chunks', 'knowledgeBase', 'workspaceIntegration'])->find($this->documentId);
        if (! $doc) {
            return;
        }

        $doc->update(['status' => 'indexing']);

        try {
            $text = $this->extractText($doc, $storage, $googleDrive ?? app(GoogleDriveService::class));
            if (trim($text) === '') {
                throw new \RuntimeException('O documento não possui conteúdo de texto que possa ser indexado.');
            }
            $chunks = $this->chunk($text);

            // Remove old chunks
            $doc->chunks()->delete();

            $kb = $doc->knowledgeBase ?? $doc->load('knowledgeBase')->knowledgeBase;
            $kbId = $kb?->id ?? 0;

            $chunkModels = [];
            foreach ($chunks as $i => $chunkText) {
                $chunkModels[] = AiKbChunk::create([
                    'kb_id' => $kbId,
                    'document_id' => $doc->id,
                    'ord' => $i,
                    'content' => $chunkText,
                    'tokens' => (int) (strlen($chunkText) / 4),
                ]);
            }

            // Embed all chunks.
            //
            // A missing embedding provider (Anthropic-only or none configured) is a
            // non-fatal condition: the document is still indexed as plain text and we
            // log it so operators can see RAG won't work until a provider is added.
            //
            // A transient embedding API error, by contrast, is allowed to propagate so
            // the queue retries — rather than silently marking the document "indexed"
            // with no vectors.
            $workspaceId = $kb?->workspace_id ?? 0;

            if ($workspaceId && ! empty($chunkModels)) {
                if ($this->embedProviderAvailable($workspaceId)) {
                    foreach (array_chunk($chunkModels, 20) as $batch) {
                        $texts = array_map(fn ($c) => $c->content, $batch);
                        $embeddings = $llm->embed($workspaceId, $texts);

                        foreach ($batch as $j => $chunk) {
                            if (isset($embeddings[$j])) {
                                $store->storeEmbedding($chunk, $embeddings[$j]);
                            }
                        }
                    }
                } else {
                    Log::warning('IndexDocumentJob: indexed without embeddings — no embedding-capable provider configured', [
                        'document_id' => $doc->id,
                        'kb_id' => $kbId,
                        'workspace_id' => $workspaceId,
                    ]);
                }
            }

            $updates = [
                'status' => 'indexed',
                'last_indexed_at' => now(),
                'tokens' => array_sum(array_map(fn ($c) => $c->tokens, $chunkModels)),
            ];
            if ($doc->external_provider === 'google_drive') {
                $updates['last_synced_at'] = now();
                $updates['sync_error'] = null;
            }
            $doc->update($updates);
        } catch (\Throwable $e) {
            $updates = ['status' => 'error'];
            if ($doc->external_provider === 'google_drive') {
                $updates['sync_error'] = mb_substr($e->getMessage(), 0, 2000);
            }
            $doc->update($updates);
            throw $e;
        }
    }

    /** True when the workspace has an embedding-capable provider (OpenAI/Gemini). */
    private function embedProviderAvailable(int $workspaceId): bool
    {
        // Any failure to RESOLVE a provider (none configured, orphaned workspace,
        // malformed config) is treated as "no embeddings" — a non-fatal condition,
        // so the document still indexes as plain text. This deliberately does NOT
        // swallow errors from the actual embed() call below, which must still
        // propagate so the queue retries rather than indexing with no vectors.
        try {
            LlmManager::forWorkspaceEmbed($workspaceId);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function extractText(
        AiKbDocument $doc,
        StorageManager $storage,
        GoogleDriveService $googleDrive,
    ): string {
        // A user-edited copy is the source of truth for the AI. The original file,
        // URL or external document remains untouched and can be restored at any time.
        if ($doc->content_override !== null) {
            return $doc->content_override;
        }

        if ($doc->external_provider === 'google_drive') {
            return $this->extractGoogleDriveText($doc, $googleDrive);
        }

        return match ($doc->source_type) {
            'text' => $doc->source_ref ?? '',
            'url' => $this->fetchUrl($doc->source_ref ?? ''),
            'file' => $this->readFile($doc->source_ref ?? '', $storage),
            'faq' => $this->formatFaq($doc->source_ref ?? ''),
            'sitemap' => $this->processSitemap($doc),
            default => '',
        };
    }

    private function extractGoogleDriveText(AiKbDocument $doc, GoogleDriveService $googleDrive): string
    {
        $connection = $doc->workspaceIntegration;
        $workspaceId = (int) ($doc->knowledgeBase?->workspace_id ?? 0);
        if (! $connection
            || $connection->provider !== 'google_drive'
            || (int) $connection->workspace_id !== $workspaceId) {
            throw new \RuntimeException('A conexão do Google Drive deste documento não está disponível neste espaço.');
        }

        $metadata = $googleDrive->metadata($connection, (string) $doc->source_ref);
        $text = $googleDrive->extractText($connection, (string) $doc->source_ref, $metadata);
        $doc->update([
            'title' => (string) ($metadata['name'] ?? $doc->title),
            'source_metadata' => $metadata,
            'source_version' => $googleDrive->version($metadata),
        ]);

        return $text;
    }

    private function fetchUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }
        $resp = Http::retry(2, 500)
            ->timeout(30)
            ->withOptions(PublicHttpUrl::requestOptions($url))
            ->get($url);
        if (! $resp->successful()) {
            return '';
        }
        $html = $resp->body();
        // Convert HTML to Markdown using league/html-to-markdown, then strip remaining tags
        if (class_exists(HtmlConverter::class)) {
            $converter = new HtmlConverter(['strip_tags' => true]);

            return $converter->convert($html);
        }

        return strip_tags($html);
    }

    /**
     * Read an uploaded document back from the active storage disk.
     *
     * The stored source_ref is a disk-relative key (e.g. "uploads/kb-docs/x.pdf"),
     * NOT an absolute local path, so it must be read through the Storage disk —
     * which works for both the local "public" disk and cloud disks (S3/Spaces/Wasabi).
     */
    private function readFile(string $path, StorageManager $storage): string
    {
        if ($path === '') {
            return '';
        }

        $disk = $storage->disk();
        if (! $disk->exists($path)) {
            return '';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            // Smalot's parser needs a real local file, so stream the (possibly remote)
            // object into a temp file before parsing, then clean it up.
            $tmp = tempnam(sys_get_temp_dir(), 'kbpdf_');
            try {
                file_put_contents($tmp, $disk->get($path));
                $parser = new Parser;

                return $parser->parseFile($tmp)->getText();
            } finally {
                @unlink($tmp);
            }
        }

        return (string) ($disk->get($path) ?? '');
    }

    /**
     * Turn the FAQ payload (a JSON array of {question, answer} pairs produced by the
     * UI) into clean, embeddable text. Falls back to the raw string if it isn't JSON.
     */
    private function formatFaq(string $raw): string
    {
        if (trim($raw) === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            // Not JSON — treat as plain text.
            return $raw;
        }

        $parts = [];
        foreach ($decoded as $pair) {
            if (! is_array($pair)) {
                continue;
            }
            $q = trim((string) ($pair['question'] ?? ''));
            $a = trim((string) ($pair['answer'] ?? ''));
            if ($q === '' && $a === '') {
                continue;
            }
            $parts[] = "Q: {$q}\nA: {$a}";
        }

        return implode("\n\n", $parts);
    }

    /**
     * Parse a sitemap and fan out one lightweight child job per page URL.
     *
     * This job stays cheap on purpose: it only fetches + parses the XML and
     * enqueues child "url" documents. The actual page crawling/embedding happens
     * in those child jobs on the queue, so the originating web request never
     * blocks on hundreds of HTTP fetches (which previously caused a 502 when the
     * queue ran synchronously).
     *
     * Handles both <urlset> (a flat list of pages) and <sitemapindex> (a list of
     * nested sitemaps, e.g. Yoast/WordPress) — for the latter, each nested sitemap
     * is enqueued as its own "sitemap" child and expanded recursively.
     */
    private function processSitemap(AiKbDocument $doc): string
    {
        $sitemapUrl = $doc->source_ref ?? '';
        if (empty($sitemapUrl)) {
            return '';
        }
        $resp = Http::retry(2, 500)
            ->timeout(20)
            ->withOptions(PublicHttpUrl::requestOptions($sitemapUrl))
            ->get($sitemapUrl);
        if (! $resp->successful()) {
            return '';
        }

        try {
            $xml = simplexml_load_string($resp->body());
            if ($xml === false) {
                throw new \RuntimeException('Unparseable sitemap XML');
            }

            // <sitemapindex> → nested sitemaps; <urlset> → page URLs.
            $isIndex = isset($xml->sitemap);
            $childType = $isIndex ? 'sitemap' : 'url';

            $locs = [];
            foreach (($isIndex ? $xml->sitemap : $xml->url) as $node) {
                $loc = trim((string) $node->loc);
                if ($loc !== '') {
                    try {
                        PublicHttpUrl::inspect($loc);
                    } catch (\InvalidArgumentException) {
                        continue;
                    }
                    $locs[$loc] = true; // dedupe by URL
                }
            }

            foreach (array_slice(array_keys($locs), 0, 200) as $loc) {
                $child = AiKbDocument::create([
                    'kb_id' => $doc->kb_id,
                    'title' => $loc,
                    'source_type' => $childType,
                    'source_ref' => $loc,
                    'status' => 'pending',
                ]);
                static::dispatch($child->id)->onQueue('ai');
            }
        } catch (\Throwable) {
            // Malformed sitemap; fall back to fetching the URL as HTML
            return $this->fetchUrl($sitemapUrl);
        }

        return '';
    }

    private function chunk(string $text, int $size = 800, int $overlap = 100): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $chunks = [];
        $i = 0;

        while ($i < count($words)) {
            $slice = array_slice($words, $i, $size);
            $chunks[] = implode(' ', $slice);
            $i += ($size - $overlap);
        }

        return array_values(array_filter($chunks));
    }
}
