<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around a vision-capable AI model, used to pre-fill forms from
 * a photographed document instead of hand-typing every field. Every result
 * from this service is meant to populate an editable form field that a human
 * reviews before saving — nothing here writes to the database directly, and
 * a failure here always degrades to "type it in yourself" rather than
 * blocking the underlying create/update flow it assists.
 */
class DocumentIntelligenceService
{
    protected function apiKey(): ?string
    {
        return config('services.anthropic.api_key');
    }

    protected function model(): string
    {
        return config('services.anthropic.vision_model', 'claude-sonnet-4-5-20250929');
    }

    /**
     * Whether an API key has been configured at all — callers use this to
     * decide whether to even offer the "scan with AI" buttons in the UI.
     */
    public function isConfigured(): bool
    {
        return filled($this->apiKey());
    }

    /**
     * Reads an OR (Official Receipt) or CR (Certificate of Registration)
     * photo and returns whatever vehicle identity fields it can confidently
     * read. Any field it can't read comes back null rather than guessed.
     *
     * @return array{plate_number: ?string, make: ?string, model: ?string, year_model: ?int, color: ?string, engine_number: ?string, chassis_number: ?string}
     */
    public function extractVehicleRegistration(string $imagePath): array
    {
        $prompt = <<<'PROMPT'
        You are reading a Philippine vehicle Official Receipt (OR) or Certificate of Registration (CR) photo for a government fleet management system. Extract only what is clearly printed on the document. Respond with ONLY a JSON object (no markdown, no commentary) with exactly these keys:
        {
          "plate_number": string or null,
          "make": string or null,
          "model": string or null,
          "year_model": integer or null,
          "color": string or null,
          "engine_number": string or null,
          "chassis_number": string or null
        }
        If a field is not visible or you are not confident, use null for that field. Never invent a value.
        PROMPT;

        return $this->callVision($imagePath, $prompt, [
            'plate_number', 'make', 'model', 'year_model', 'color', 'engine_number', 'chassis_number',
        ]);
    }

    /**
     * Reads a maintenance/repair shop receipt or invoice photo and returns
     * whatever service details it can confidently read.
     *
     * @return array{service_date: ?string, cost: ?float, performed_by: ?string, description: ?string}
     */
    public function extractMaintenanceReceipt(string $imagePath): array
    {
        $prompt = <<<'PROMPT'
        You are reading a vehicle maintenance/repair receipt or invoice photo for a government fleet management system. Extract only what is clearly printed on the document. Respond with ONLY a JSON object (no markdown, no commentary) with exactly these keys:
        {
          "service_date": "YYYY-MM-DD" or null,
          "cost": number or null,
          "performed_by": string or null,
          "description": string or null
        }
        "performed_by" is the shop/vendor name. "description" is a short summary of the work/parts listed (max 150 characters). If a field is not visible or you are not confident, use null for that field. Never invent a value.
        PROMPT;

        return $this->callVision($imagePath, $prompt, ['service_date', 'cost', 'performed_by', 'description']);
    }

    /**
     * Reads a photo of a vehicle's plate and returns just the plate number —
     * the fallback path when a QR sticker is damaged, faded, or missing.
     *
     * @return array{plate_number: ?string}
     */
    public function extractPlateNumber(string $imagePath): array
    {
        $prompt = <<<'PROMPT'
        You are reading a photo of a Philippine vehicle license plate for a fleet lookup system. Respond with ONLY a JSON object (no markdown, no commentary) with exactly this key:
        {
          "plate_number": string or null
        }
        Return the plate characters exactly as printed, with no spaces or dashes added. If no plate is clearly visible or you are not confident, use null.
        PROMPT;

        return $this->callVision($imagePath, $prompt, ['plate_number']);
    }

    /**
     * Sends the image + prompt to the configured vision model and returns the
     * parsed JSON object, guaranteed to contain exactly $expectedKeys (missing
     * ones filled with null) so callers never have to guard against a
     * partially-shaped response.
     *
     * @param  string[]  $expectedKeys
     * @return array<string, mixed>
     */
    protected function callVision(string $imagePath, string $prompt, array $expectedKeys): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('AI document scanning is not configured. Set ANTHROPIC_API_KEY in the environment to enable it.');
        }

        if (! is_readable($imagePath)) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        $mediaType = $this->detectMediaType($imagePath);
        $base64 = base64_encode((string) file_get_contents($imagePath));

        // OR/CR and receipt uploads are very commonly saved as PDF rather than a
        // photo, so a PDF is sent as a "document" content block instead of an
        // "image" one — Claude reads it directly, no page-to-image conversion
        // needed on our end (which would've meant a new PHP extension we can't
        // install without shell access to the live server).
        $contentBlock = $mediaType === 'application/pdf'
            ? [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => 'application/pdf',
                    'data' => $base64,
                ],
            ]
            : [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $mediaType,
                    'data' => $base64,
                ],
            ];

        $response = Http::withHeaders([
                'x-api-key' => $this->apiKey(),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->timeout(30)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model(),
                'max_tokens' => 1024,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            $contentBlock,
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Document intelligence API call failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('The AI scanning service did not respond successfully. Please enter the details manually.');
        }

        $text = (string) $response->json('content.0.text', '');
        $parsed = $this->parseJson($text);

        $result = [];
        foreach ($expectedKeys as $key) {
            $result[$key] = $parsed[$key] ?? null;
        }

        return $result;
    }

    /**
     * The model is asked for raw JSON, but this strips fenced code blocks
     * defensively in case it wraps the answer in ```json ... ``` anyway.
     *
     * @return array<string, mixed>
     */
    protected function parseJson(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?/i', '', $text) ?? $text;
        $text = preg_replace('/```$/', '', $text) ?? $text;
        $text = trim($text);

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Detects the real media type from the file's own bytes rather than its
     * name/extension. This matters because the path handed in here is
     * `UploadedFile::getRealPath()` — Laravel's PHP-managed temp upload path
     * (e.g. `/tmp/phpXXXXXX`), which has no meaningful extension at all, so an
     * extension-based check would silently mislabel every upload as JPEG
     * regardless of what was actually sent.
     */
    protected function detectMediaType(string $imagePath): string
    {
        $mime = @mime_content_type($imagePath) ?: 'image/jpeg';

        // Anthropic accepts exactly these image types (plus PDF, sent as a
        // separate "document" block above) — anything else reported by
        // finfo (e.g. a bare "image/x-ms-bmp") is normalized to JPEG rather
        // than sent as a media_type the API would just reject outright.
        return match ($mime) {
            'image/png', 'image/webp', 'image/gif', 'application/pdf' => $mime,
            default => 'image/jpeg',
        };
    }
}
