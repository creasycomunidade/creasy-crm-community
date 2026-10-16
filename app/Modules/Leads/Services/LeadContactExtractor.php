<?php

namespace App\Modules\Leads\Services;

use App\Modules\Leads\Models\Lead;
use App\Support\PublicHttpUrl;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;

class LeadContactExtractor
{
    private const PHONE = '/(?<![\d\w])(?:\+\d{1,3}[\s.-]*)?(?:\(?\d{2}\)?[\s.-]*)?\d{4,5}[\s.-]?\d{4}(?!\d)/u';

    public function extract(string $content, array $links = []): array
    {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('~(?:https?://|mailto:|tel:|whatsapp://)[^\s<>"\x27)]+~iu', $content, $found);
        $links = array_unique(array_merge($links, $found[0]));
        $whatsapp = $phones = $emails = $other = [];
        foreach ($links as $link) {
            if (! is_string($link)) {
                continue;
            }
            $link = html_entity_decode($link, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $host = strtolower((string) parse_url($link, PHP_URL_HOST));
            $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
            $path = (string) parse_url($link, PHP_URL_PATH);
            parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
            if ($host === 'wa.me' || in_array($host, ['api.whatsapp.com', 'web.whatsapp.com', 'www.whatsapp.com'], true) || $scheme === 'whatsapp') {
                $number = $host === 'wa.me' ? trim($path, '/') : ($query['phone'] ?? '');
                if (is_string($number) && ($phone = $this->phone($number, true))) {
                    $whatsapp[] = $phone;
                }
            } elseif ($scheme === 'tel') {
                if ($phone = $this->phone(rawurldecode($path))) {
                    $phones[] = $phone;
                }
            } elseif ($scheme === 'mailto') {
                $emails = array_merge($emails, $this->emails(rawurldecode($path)));
            }
            if (in_array($scheme, ['http', 'https'], true) && preg_match('~(?:instagram\.com|facebook\.com|linkedin\.com|t\.me|/contato|/contact|/fale-conosco)~i', $link)) {
                $other[] = $link;
            }
        }
        $plain = preg_replace('~<[^>]+>~', ' ', preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $content));
        preg_match_all('/(?:whats\s*app|zap)\s*[:\-]?\s*([+\d() .-]{10,24})/iu', $plain, $tagged);
        foreach ($tagged[1] as $number) {
            if ($phone = $this->phone($number)) {
                $whatsapp[] = $phone;
            }
        }
        // Strip URLs before generic matching so tracking IDs do not become phones.
        $plain = preg_replace('~(?:https?://|mailto:)[^\s<>]+~i', ' ', $plain);
        preg_match_all(self::PHONE, $plain, $matches);
        foreach ($matches[0] as $number) {
            if ($phone = $this->phone($number)) {
                $phones[] = $phone;
            }
        }

        return $this->merge([], [
            'whatsapp' => $whatsapp, 'phones' => array_merge($whatsapp, $phones),
            'emails' => array_merge($emails, $this->emails($plain)), 'links' => $other,
        ]);
    }

    public function phone(string $value, bool $international = false): ?string
    {
        $digits = preg_replace('/\D/', '', $value);
        if (! $international && ! str_starts_with(trim($value), '+') && ! in_array(strlen($digits), [10, 11, 12, 13], true)) {
            return null;
        }
        if (! $international && ! str_starts_with(trim($value), '+') && in_array(strlen($digits), [10, 11], true)) {
            $digits = '55'.$digits;
        }
        if (! preg_match('/^[1-9]\d{6,14}$/', $digits)) {
            return null;
        }
        if (str_starts_with($digits, '55') && ! preg_match('/^55[1-9]\d[2-9]\d{7,8}$/', $digits)) {
            return null;
        }

        return '+'.$digits;
    }

    private function emails(string $content): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $content, $matches);

        return array_values(array_filter(array_map('strtolower', $matches[0]), fn ($email) => strlen($email) <= 254
            && filter_var($email, FILTER_VALIDATE_EMAIL) && ! preg_match('/\.(png|jpg|jpeg|gif|webp|svg)$/i', $email)));
    }

    public function merge(array $existing, array $found): array
    {
        $result = [];
        foreach (['whatsapp', 'phones', 'emails', 'links'] as $key) {
            $result[$key] = array_values(array_unique(array_merge($found[$key] ?? [], $existing[$key] ?? [])));
        }
        $result['phones'] = array_values(array_unique(array_merge($result['whatsapp'], $result['phones'])));

        return $result;
    }

    public function attributes(Lead $lead, array $found): array
    {
        $existing = $this->merge($lead->contact_channels ?? [], [
            'phones' => $lead->phone ? [$lead->phone] : [],
            'emails' => $lead->email ? [$lead->email] : [],
            'whatsapp' => $lead->whatsapp_phone ? [$lead->whatsapp_phone] : [],
        ]);
        $channels = $this->merge($existing, $found);

        return ['contact_channels' => $channels, 'whatsapp_phone' => $channels['whatsapp'][0] ?? null,
            'phone' => $channels['phones'][0] ?? null, 'email' => $channels['emails'][0] ?? null];
    }

    /** Bounded, best-effort enrichment; failure never drops a business. */
    public function website(string $url, float $deadline): array
    {
        $result = [];
        $visited = [];
        $origin = strtolower((string) parse_url($url, PHP_URL_HOST));
        for ($i = 0; $i < 3 && microtime(true) < $deadline; $i++) {
            if (isset($visited[$url])) {
                break;
            }
            $visited[$url] = true;
            try {
                $options = PublicHttpUrl::requestOptions($url);
                $response = Http::timeout(max(1, min(4, (int) ($deadline - microtime(true)))))
                    ->connectTimeout(2)->withOptions($options + ['stream' => true])->get($url);
                if ($response->redirect()) {
                    $url = $this->resolveUrl($url, (string) $response->header('Location'));

                    continue;
                }
                if (! $response->successful()) {
                    break;
                }
                $stream = $response->toPsrResponse()->getBody();
                $html = $stream->read(1024 * 1024);
                $stream->close();
                $result = $this->merge($result, $this->extract($html));
                if (! empty($result['whatsapp']) && ! empty($result['emails'])) {
                    break;
                }
                preg_match_all('~href\s*=\s*["\x27]([^"\x27]+)["\x27]~iu', $html, $anchors);
                $next = null;
                foreach ($anchors[1] as $href) {
                    $candidate = $this->resolveUrl($url, $href);
                    if (strtolower((string) parse_url($candidate, PHP_URL_HOST)) === $origin
                        && preg_match('~/(contato|contact|fale-conosco|atendimento)(?:[/.?#]|$)~i', $candidate)
                        && ! isset($visited[$candidate])) {
                        $next = $candidate;
                        break;
                    }
                }
                if (! $next) {
                    break;
                }
                $url = $next;
            } catch (\Throwable $e) {
                break;
            }
        }

        return $result;
    }

    private function resolveUrl(string $base, string $href): string
    {
        return (string) UriResolver::resolve(new Uri($base), new Uri(html_entity_decode($href)));
    }
}
