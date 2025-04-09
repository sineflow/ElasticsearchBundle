<?php

namespace Sineflow\ElasticsearchBundle\Client;

use Elastic\Elasticsearch\Client as BaseClient;
use Elastic\Elasticsearch\ClientInterface;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elastic\Elasticsearch\Traits\ClientEndpointsTrait;
use Elastic\Elasticsearch\Traits\EndpointTrait;
use Elastic\Elasticsearch\Traits\NamespaceTrait;
use Elastic\Transport\Transport;
use Http\Promise\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Sineflow\ElasticsearchBundle\Profiler\ProfilerQueryLogCollection;
use Sineflow\ElasticsearchBundle\Profiler\ProfilerQueryLogRecord;

class Client implements ClientInterface
{
    use ClientEndpointsTrait;
    use EndpointTrait;
    use NamespaceTrait;

    public function __construct(
        private readonly BaseClient $inner,
        private readonly ?ProfilerQueryLogCollection $profilerQueryLogCollection = null,
        private readonly bool $profilingBacktraceEnabled = false,
    ) {
    }

    /**
     * @throws ClientResponseException
     */
    public function sendRequest(RequestInterface $request): Elasticsearch|Promise
    {
        if ($this->getAsync()) {
            return $this->inner->sendRequest($request);
        }

        $start = microtime(true);
        try {
            $response = $this->inner->sendRequest($request);
            $this->logQueryProfile($this->getTransport()->getLastRequest(), $response, duration: microtime(true) - $start);
        } catch (ClientResponseException $e) {
            $this->logQueryProfile($this->getTransport()->getLastRequest(), $e->getResponse(), duration: microtime(true) - $start);
            throw $e;
        }

        return $response;
    }

    private function logQueryProfile(RequestInterface $request, ResponseInterface $response, float $duration): void
    {
        if (!$this->profilerQueryLogCollection) {
            return;
        }

        $this->profilerQueryLogCollection->addRecord(new ProfilerQueryLogRecord(
            request: $request,
            response: $response,
            duration: $duration,
            backtrace: $this->profilingBacktraceEnabled ? array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1) : null,
        ));
    }

    public function getTransport(): Transport
    {
        return $this->inner->getTransport();
    }

    public function getLogger(): LoggerInterface
    {
        return $this->inner->getLogger();
    }

    public function setAsync(bool $async): self
    {
        $this->inner->setAsync($async);

        return $this;
    }

    public function getAsync(): bool
    {
        return $this->inner->getAsync();
    }

    public function setElasticMetaHeader(bool $active): self
    {
        $this->inner->setElasticMetaHeader($active);

        return $this;
    }

    public function getElasticMetaHeader(): bool
    {
        return $this->inner->getElasticMetaHeader();
    }

    public function setResponseException(bool $active): self
    {
        $this->inner->setResponseException($active);

        return $this;
    }

    public function getResponseException(): bool
    {
        return $this->inner->getResponseException();
    }
}
