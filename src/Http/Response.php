<?php
namespace DonationGuard\Http;

final class Response
{
    /** @var int */ public $status;
    /** @var string */ public $body;

    public function __construct(int $status, string $body)
    {
        $this->status = $status;
        $this->body = $body;
    }

    /** @return array<mixed>|null */
    public function json(): ?array
    {
        $d = json_decode($this->body, true);
        return is_array($d) ? $d : null;
    }
}
