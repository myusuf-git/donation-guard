<?php
namespace DonationGuard\Http;

final class Response
{
    /** @var int */ public $status;
    /** @var string */ public $body;
    /** @var string */ public $contentType;
    /** @var float seconds */ public $seconds;

    public function __construct(int $status, string $body, string $contentType = '', float $seconds = 0.0)
    {
        $this->status = $status;
        $this->body = $body;
        $this->contentType = $contentType;
        $this->seconds = $seconds;
    }

    /** @return array<mixed>|null */
    public function json(): ?array
    {
        $d = json_decode($this->body, true);
        return is_array($d) ? $d : null;
    }
}
