<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\Contact;
use App\Support\EmailHtmlPreprocessor;
use App\Support\MergeTagReplacer;
use App\Support\TracksEmailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class CampaignMail extends Mailable
{
    use Queueable, SerializesModels, TracksEmailContent;

    public function __construct(
        public Campaign $campaign,
        public Contact $contact,
        public int $queueId,
        public ?string $abVariant = null
    ) {
    }

    private function effectiveSubject(): string
    {
        if ($this->abVariant === 'b' && !empty($this->campaign->ab_subject_b)) {
            return (string) $this->campaign->ab_subject_b;
        }
        return (string) $this->campaign->subject;
    }

    private function effectiveBody(): string
    {
        if ($this->abVariant === 'b' && !empty($this->campaign->ab_body_b)) {
            return (string) $this->campaign->ab_body_b;
        }
        return (string) $this->campaign->body;
    }

    public function envelope(): Envelope
    {
        $unsubscribeUrl = route('unsubscribe', ['email' => rawurlencode($this->contact->email)]);

        return new Envelope(
            subject: $this->replaceMergeTags($this->effectiveSubject(), $this->contact),
            using: [
                function (\Symfony\Component\Mime\Email $email) use ($unsubscribeUrl) {
                    $email->getHeaders()
                        ->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>')
                        ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                },
            ]
        );
    }

    public function content(): Content
    {
        $body = $this->replaceMergeTags($this->effectiveBody(), $this->contact);
        $processedBody = EmailHtmlPreprocessor::preprocess($body);
        $normalizedHtml = $this->normalizeForEmailClient($processedBody);
        $inlineReadyHtml = $this->inlineCssForEmailClients($normalizedHtml);

        $trackedHtml = $this->buildTrackedHtml(
            $inlineReadyHtml,
            $this->queueId,
            true,
            $this->contact->email
        );

        // Plain-text alternative: strip HTML tags from the body
        $plainText = $this->buildPlainText($body);

        return new Content(
            htmlString: $trackedHtml,
            textString: $plainText,
        );
    }

    private function buildPlainText(string $html): string
    {
        // Replace block-level tags with newlines before stripping
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<\/div>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<\/li>/i', "\n", $text) ?? $text;

        // Replace anchor tags with "text (URL)" format
        $text = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '$2 ($1)', $text) ?? $text;

        // Strip all remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse excessive blank lines (max 2 consecutive)
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        // Add unsubscribe footer
        $unsubscribeUrl = route('unsubscribe', ['email' => rawurlencode($this->contact->email)]);
        $appName = config('app.name', 'Mailer');
        $companyAddress = config('app.company_address', '');

        $text .= "\n\n---\n";
        $text .= "To unsubscribe, visit: {$unsubscribeUrl}\n";
        if ($companyAddress) {
            $text .= "{$appName}, {$companyAddress}\n";
        }

        return trim($text);
    }

    private function replaceMergeTags(string $body, Contact $contact): string
    {
        return MergeTagReplacer::replace($body, $contact);
    }

    private function normalizeForEmailClient(string $html): string
    {
        $trimmed = trim($html);

        if ($trimmed === '') {
            return $trimmed;
        }

        if (stripos($trimmed, '<html') !== false) {
            return $trimmed;
        }

        return '<!doctype html>'
            . '<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background:#ffffff;">'
            . '<div style="margin:0;padding:0;">' . $trimmed . '</div>'
            . '</body></html>';
    }

    private function inlineCssForEmailClients(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        try {
            $sanitized = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
            $inliner = new CssToInlineStyles();
            $inlined = $inliner->convert($sanitized);
            $inlined = $this->sanitizeUnsupportedInlineCss($inlined);

            $inlined = preg_replace(
                '/<body([^>]*)>/i',
                '<body$1 style="margin:0;padding:24px 14px 60px;background:#F5F2F8;color:#18182A;font-family:Arial,Helvetica,sans-serif;line-height:1.6;">',
                $inlined,
                1
            ) ?? $inlined;

            Log::info('Inline HTML sample', [
                'preview' => substr($inlined, 0, 1000),
            ]);

            return $inlined;
        } catch (\Throwable) {
            return $html;
        }
    }

    private function sanitizeUnsupportedInlineCss(string $html): string
    {
        return preg_replace_callback('/style\s*=\s*"([^"]*)"/i', function (array $matches): string {
            $style = $matches[1];

            $filtered = preg_replace('/\bdisplay\s*:\s*(flex|grid)\s*;?/i', '', $style) ?? $style;
            $filtered = preg_replace('/\bposition\s*:\s*[^;"]+;?/i', '', $filtered) ?? $filtered;
            $filtered = preg_replace('/\b[a-z-]+\s*:\s*var\([^)]+\)\s*;?/i', '', $filtered) ?? $filtered;
            $filtered = preg_replace('/\s{2,}/', ' ', trim($filtered)) ?? trim($filtered);
            $filtered = trim($filtered, " ;");

            return $filtered === '' ? '' : 'style="'.$filtered.'"';
        }, $html) ?? $html;
    }

    public function attachments(): array
    {
        if (!empty($this->campaign->attachment_path) && Storage::disk('public')->exists($this->campaign->attachment_path)) {
            return [
                Attachment::fromPath(Storage::disk('public')->path($this->campaign->attachment_path))
                    ->as($this->campaign->attachment_name ?: basename($this->campaign->attachment_path)),
            ];
        }

        return [];
    }

}
