<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\Client\Connection;

use GraphAware\Bolt\Configuration as BoltConfiguration;
use GraphAware\Bolt\Driver as BoltDriver;
use GraphAware\Bolt\Exception\MessageFailureException;
use GraphAware\Bolt\GraphDatabase as BoltGraphDB;
use GraphAware\Common\Connection\BaseConfiguration;
use GraphAware\Common\Cypher\Statement;
use GraphAware\Common\Driver\DriverInterface;
use GraphAware\Common\Driver\PipelineInterface;
use GraphAware\Common\Driver\SessionInterface;
use GraphAware\Common\Result\RecordCursorInterface;
use GraphAware\Common\Result\Result;
use GraphAware\Common\Result\ResultCollection;
use GraphAware\Common\Transaction\TransactionInterface;
use GraphAware\Neo4j\Client\Exception\Neo4jException;
use GraphAware\Neo4j\Client\HttpDriver\GraphDatabase as HttpGraphDB;
use GraphAware\Neo4j\Client\StackInterface;
use InvalidArgumentException;
use RuntimeException;

class Connection
{
    private string $alias;

    private string $uri;

    private DriverInterface $driver;

    private array|BaseConfiguration|null $config;

    private ?SessionInterface $session = null;

    public function __construct(string $alias, string $uri, BaseConfiguration $config = null)
    {
        $this->alias = $alias;
        $this->uri = $uri;
        $this->config = $config;

        $this->buildDriver();
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    public function createPipeline($query = null, array $parameters = [], $tag = null): PipelineInterface
    {
        $this->checkSession();
        $parameters = is_array($parameters) ? $parameters : [];

        return $this->session->createPipeline($query, $parameters, $tag);
    }

    /**
     * @throws Neo4jException
     */
    public function run(string $statement, array $parameters = null, string $tag = null): RecordCursorInterface|Result
    {
        $this->checkSession();
        if (empty($statement)) {
            throw new InvalidArgumentException(sprintf('Expected a non-empty Cypher statement, got "%s"', $statement));
        }
        $parameters = (array) $parameters;

        try {
            return $this->session->run($statement, $parameters, $tag);
        } catch (MessageFailureException $e) {
            $exception = new Neo4jException($e->getMessage());
            $exception->setNeo4jStatusCode($e->getStatusCode());

            throw $exception;
        }
    }

    public function runMixed(array $queue): ResultCollection
    {
        $this->checkSession();
        $pipeline = $this->createPipeline();

        foreach ($queue as $element) {
            if ($element instanceof StackInterface) {
                foreach ($element->statements() as $statement) {
                    $pipeline->push($statement->text(), $statement->parameters(), $statement->getTag());
                }
            } elseif ($element instanceof Statement) {
                $pipeline->push($element->text(), $element->parameters(), $element->getTag());
            }
        }

        return $pipeline->run();
    }

    public function getTransaction(): TransactionInterface
    {
        $this->checkSession();

        return $this->session->transaction();
    }

    public function getSession(): SessionInterface
    {
        $this->checkSession();

        return $this->session;
    }

    private function buildDriver(): void
    {
        $params = parse_url($this->uri);

        if (str_contains($this->uri, 'bolt')) {
            $port = isset($params['port']) ? (int) $params['port'] : BoltDriver::DEFAULT_TCP_PORT;
            $uri = sprintf('%s://%s:%d', $params['scheme'], $params['host'], $port);
            $config = null;
            if (isset($params['user']) && isset($params['pass'])) {
                $config = BoltConfiguration::create()->withCredentials($params['user'], $params['pass']);
            }
            $this->driver = BoltGraphDB::driver($uri, $config);
        } elseif (str_contains($this->uri, 'http')) {
            $uri = $this->uri;
            $this->driver = HttpGraphDB::driver($uri, $this->config);
        } else {
            throw new RuntimeException(sprintf('Unable to build a driver from uri "%s"', $this->uri));
        }
    }

    private function checkSession(): void
    {
        if (null === $this->session) {
            $this->session = $this->driver->session();
        }
    }
}
