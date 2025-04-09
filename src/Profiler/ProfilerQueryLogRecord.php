<?php

namespace Sineflow\ElasticsearchBundle\Profiler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ProfilerQueryLogRecord
{
    private readonly float $duration;

    public function __construct(
        private readonly RequestInterface $request,
        // @phpstan-ignore-next-line
        private readonly ResponseInterface $response,
        float $duration,
        private readonly ?array $backtrace = null,
    ) {
        // Duration is passed in microseconds, but we don't need such precision, so we save it in ms for the profiler
        $this->duration = $duration * 1000;
    }

    public function asArray(): array
    {
        return [
            'method'        => $this->request->getMethod(),
            'scheme'        => $this->request->getUri()->getScheme(),
            'host'          => $this->request->getUri()->getHost(),
            'port'          => $this->request->getUri()->getPort(),
            'path'          => $this->request->getUri()->getPath(),
            'query'         => $this->request->getUri()->getQuery(),
            'queryDuration' => $this->duration,
            'curlRequest'   => $this->getCurlCommand(),
            'kibanaRequest' => $this->getKibanaRequest(),
            'backtrace'     => $this->backtrace,
        ];
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    private function getKibanaRequest(): string
    {
        $result = $this->request->getMethod().' '.$this->request->getUri()->getPath();
        if ($this->request->getUri()->getQuery()) {
            $result .= '?'.$this->request->getUri()->getQuery();
        }
        $result .= "\n".\trim($this->request->getBody(), " '");

        return $result;
    }

    /**
     * Construct a string cURL command
     */
    private function getCurlCommand(): string
    {
        $method = $this->request->getMethod();
        $uri = (string) $this->request->getUri();
        $body = (string) $this->request->getBody();

        if (!str_contains($uri, '?')) {
            $uri .= '?pretty=true';
        } else {
            str_replace('?', '?pretty=true', $uri);
        }

        $curlCommand = 'curl -X'.strtoupper($method);
        $curlCommand .= " '".$uri."'";

        if ('' !== $body) {
            $curlCommand .= " -d '".$body."'";
        }

        return $curlCommand;
    }
}
