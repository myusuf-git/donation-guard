<?php
namespace DonationGuard\Core;

use DonationGuard\Http\ClientInterface;

/**
 * Checks that a payment callback (webhook) URL can be reached the way a gateway reaches it.
 *
 * It sends a harmless, unsigned POST. A healthy handler rejects it quickly (any 2xx/4xx answer
 * from the application). Problems a gateway would also hit: timeouts, 5xx, 404 (wrong URL),
 * redirects (gateways do not follow them) and firewall challenge pages.
 */
final class CallbackProbe
{
    public const OK   = 'ok';
    public const WARN = 'warning';
    public const FAIL = 'failing';

    /** @var ClientInterface */
    private $http;
    /** @var float */
    private $slowSeconds;

    public function __construct(ClientInterface $http, float $slowSeconds = 8.0)
    {
        $this->http = $http;
        $this->slowSeconds = $slowSeconds;
    }

    /** @return array{gateway:string,url:string,state:string,http:int,seconds:float,message:string} */
    public function probe(string $gateway, string $url): array
    {
        $out = ['gateway' => $gateway, 'url' => $url, 'state' => self::OK, 'http' => 0, 'seconds' => 0.0, 'message' => 'Reachable'];
        try {
            $res = $this->http->post($url, '{"donation_guard_probe":true}', ['Content-Type' => 'application/json'], 20);
        } catch (\Throwable $e) {
            $out['state'] = self::FAIL;
            $out['message'] = 'Not reachable: ' . $e->getMessage();
            return $out;
        }
        $out['http'] = $res->status;
        $out['seconds'] = round($res->seconds, 2);
        $isJson = stripos($res->contentType, 'json') !== false || $this->looksJson($res->body);
        $isHtml = stripos($res->contentType, 'html') !== false || stripos($res->body, '<html') !== false;

        if ($res->status >= 500) {
            return $this->set($out, self::FAIL, 'Server error ' . $res->status . ': the gateway will treat callbacks as failed');
        }
        if ($res->status >= 300 && $res->status < 400) {
            return $this->set($out, self::FAIL, 'Redirect ' . $res->status . ': gateways do not follow redirects, use the final URL');
        }
        if ($res->status === 404) {
            return $this->set($out, self::FAIL, 'Not found (404): the callback URL is wrong or the handler is not active');
        }
        if (($res->status === 403 || $res->status === 429 || $res->status === 503) && $isHtml) {
            return $this->set($out, self::FAIL, 'Blocked (' . $res->status . ' HTML page): a firewall/CDN may be blocking the gateway');
        }
        if ($res->seconds > $this->slowSeconds) {
            return $this->set($out, self::WARN, sprintf('Slow answer (%.1fs): gateways may time out and retry', $res->seconds));
        }
        if (!$isJson && in_array($gateway, ['tripay', 'xendit'], true)) {
            return $this->set($out, self::WARN, 'Answer is not JSON: ' . ucfirst($gateway) . ' expects a JSON reply and may keep retrying');
        }
        return $out;
    }

    private function set(array $o, string $state, string $msg): array
    {
        $o['state'] = $state;
        $o['message'] = $msg;
        return $o;
    }

    private function looksJson(string $body): bool
    {
        $b = ltrim($body);
        return $b !== '' && ($b[0] === '{' || $b[0] === '[') && json_decode($b) !== null;
    }
}
