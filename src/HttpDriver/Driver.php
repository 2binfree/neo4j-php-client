<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\Client\HttpDriver;

use GraphAware\Common\Connection\BaseConfiguration;
use GraphAware\Common\Driver\ConfigInterface;
use GraphAware\Common\Driver\DriverInterface;
use Http\Adapter\Guzzle7\Client;
use Http\Client\HttpClient;

class Driver implements DriverInterface
{
    const DEFAULT_HTTP_PORT = 7474;

    /**
     * @var string
     */
    protected string $uri;

    /**
     * @var Configuration
     */
    protected Configuration|BaseConfiguration|ConfigInterface $config;

    /**
     * @param string $uri
     * @param ConfigInterface|null $config
     */
    public function __construct(string $uri, ConfigInterface $config = null)
    {
        if (null !== $config && !$config instanceof BaseConfiguration) {
            throw new \RuntimeException(sprintf('Second argument to "%s" must be null or "%s"', __CLASS__, BaseConfiguration::class));
        }

        $this->uri = $uri;
        $this->config = null !== $config ? $config : Configuration::create();
    }

    /**
     * @return Session
     */
    public function session(): Session
    {
        return new Session($this->uri, $this->getHttpClient(), $this->config);
    }

    /**
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     *
     * @return HttpClient
     */
    private function getHttpClient()
    {
        $options = [];
        if ($this->config->hasValue('timeout')) {
            $options['timeout'] = $this->config->getValue('timeout');
        }

        if ($this->config->hasValue('curl_interface')) {
            $options['curl'][10062] = $this->config->getValue('curl_interface');
        }

        if (empty($options)) {
            return $this->config->getValue('http_client');
        }

        // This is to keep BC. Will be removed in 5.0

        $options['curl'][74] = true;
        $options['curl'][75] = true;

        return Client::createWithConfig($options);
    }
}
