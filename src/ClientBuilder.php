<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\Client;

use GraphAware\Common\Driver\ConfigInterface;
use GraphAware\Neo4j\Client\Connection\ConnectionManager;
use GraphAware\Neo4j\Client\HttpDriver\Configuration;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ClientBuilder
{
    const PREFLIGHT_ENV_DEFAULT = 'NEO4J_DB_VERSION';

    const DEFAULT_TIMEOUT = 5;

    const TIMEOUT_CONFIG_KEY = 'timeout';

    /**
     * @var array
     */
    protected array $config = [];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config['connection_manager']['preflight_env'] = self::PREFLIGHT_ENV_DEFAULT;
        $this->config['client_class'] = Client::class;

        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * Creates a new Client factory.
     *
     * @param array $config
     *
     * @return ClientBuilder
     */
    public static function create(array $config = []): ClientBuilder
    {
        return new static($config);
    }

    /**
     * Add a connection to the handled connections.
     *
     * @param string $alias
     * @param string $uri
     * @param ConfigInterface|null $config
     *
     * @return ClientBuilder
     */
    public function addConnection(string $alias, string $uri, ConfigInterface $config = null): static
    {
        //small hack for drupal
        if (str_starts_with($uri, 'bolt://')) {
            $parts = explode('bolt://', $uri );
            if (count($parts) === 2) {
                $splits = explode('@', $parts[1]);
                $split = $splits[count($splits)-1];
                if (str_starts_with($split, 'ssl+')) {
                    $up = count($splits) > 1 ? $splits[0] : '';
                    $ups = explode(':', $up);
                    $u = $ups[0];
                    $p = $ups[1];
                    $uri = 'bolt://'.str_replace('ssl+', '', $split);
                    $config = \GraphAware\Bolt\Configuration::newInstance()
                        ->withCredentials($u, $p)
                        ->withTLSMode(\GraphAware\Bolt\Configuration::TLSMODE_REQUIRED);
                }
            }
        }

        $this->config['connections'][$alias]['uri'] = $uri;

        return $this;
    }

    /**
     * @param string $variable
     */
    public function preflightEnv(string $variable): void
    {
        $this->config['connection_manager']['preflight_env'] = $variable;
    }

    /**
     * @param string $connectionAlias
     *
     * @return $this
     */
    public function setMaster(string $connectionAlias): static
    {
        if (!isset($this->config['connections']) || !array_key_exists($connectionAlias, $this->config['connections'])) {
            throw new \InvalidArgumentException(sprintf('The connection "%s" is not registered', (string) $connectionAlias));
        }

        $this->config['connections'] = array_map(function ($connectionSettings) {
            $connectionSettings['is_master'] = false;

            return $connectionSettings;
        }, $this->config['connections']);

        $this->config['connections'][$connectionAlias]['is_master'] = true;

        return $this;
    }

    /**
     * @param int $timeout
     *
     * @return $this
     */
    public function setDefaultTimeout(int $timeout): static
    {
        $this->config[static::TIMEOUT_CONFIG_KEY] = (int) $timeout;

        return $this;
    }

    /**
     * @param string $eventName
     * @param mixed  $callback
     *
     * @return $this
     */
    public function registerEventListener(string $eventName, mixed $callback): static
    {
        $this->config['event_listeners'][$eventName][] = $callback;

        return $this;
    }

    /**
     * Builds a Client based on the connections given.
     *
     * @return ClientInterface
     */
    public function build(): ClientInterface
    {
        $connectionManager = new ConnectionManager();

        foreach ($this->config['connections'] as $alias => $conn) {
            $config =
                $this->config['connections'][$alias]['config'] ?? Configuration::create()
                ->withTimeout($this->getDefaultTimeout());
            $connectionManager->registerConnection(
                $alias,
                $conn['uri'],
                $config
            );

            if (isset($conn['is_master']) && $conn['is_master'] === true) {
                $connectionManager->setMaster($alias);
            }
        }

        $ev = null;

        if (isset($this->config['event_listeners'])) {
            $ev = new EventDispatcher();

            foreach ($this->config['event_listeners'] as $k => $callbacks) {
                foreach ($callbacks as $callback) {
                    $ev->addListener($k, $callback);
                }
            }
        }

        return new $this->config['client_class']($connectionManager, $ev);
    }

    /**
     * @return int
     */
    private function getDefaultTimeout(): int
    {
        return array_key_exists(static::TIMEOUT_CONFIG_KEY, $this->config) ? $this->config[static::TIMEOUT_CONFIG_KEY] : self::DEFAULT_TIMEOUT;
    }
}
