<?php

namespace Amp\DoH;

final class Nameserver
{
    const RFC8484_GET = 0;
    const RFC8484_POST = 1;
    const GOOGLE_JSON = 2;

    private $type;
    private $uri;
    private $host;
    private $headers = [];

    public function __construct(string $uri, int $type = self::RFC8484_POST, array $headers = [])
    {
        $this->uri = $uri;
        $this->type = $type;
        $this->headers = $headers;
        $this->host = parse_url($uri, PHP_URL_HOST);
    }
    public function getUri(): string
    {
        return $this->uri;
    }
    public function getHost(): string
    {
        return $this->host;
    }
    public function getHeaders(): array
    {
        return $this->headers;
    }
    public function getType(): int
    {
        return $this->type;
    }
    public function __toString(): string
    {
        return $this->uri;
        switch ($this->type) {
            case self::RFC8484_GET:
                return "{$this->uri} RFC 8484 GET";
            case self::RFC8484_POST:
                return "{$this->uri} RFC 8484 POST";
            case self::GOOGLE_JSON:
                return "{$this->uri} google JSON";
        }
    }
}
