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

use GraphAware\Common\Cypher\Statement;
use GraphAware\Common\Driver\PipelineInterface;
use GraphAware\Common\Result\AbstractRecordCursor;
use GraphAware\Common\Result\Record;
use GraphAware\Common\Result\RecordCursorInterface;
use GraphAware\Common\Result\Result;
use GraphAware\Neo4j\Client\Connection\ConnectionManager;
use GraphAware\Neo4j\Client\Event\FailureEvent;
use GraphAware\Neo4j\Client\Event\PostRunEvent;
use GraphAware\Neo4j\Client\Event\PreRunEvent;
use GraphAware\Neo4j\Client\Exception\Neo4jException;
use GraphAware\Neo4j\Client\Exception\Neo4jExceptionInterface;
use GraphAware\Neo4j\Client\Result\ResultCollection;
use GraphAware\Neo4j\Client\Schema\Label;
use GraphAware\Neo4j\Client\Transaction\Transaction;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Client implements ClientInterface
{
    /**
     * @var ConnectionManager
     */
    protected ConnectionManager $connectionManager;

    /**
     * @var EventDispatcherInterface
     */
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(ConnectionManager $connectionManager, EventDispatcherInterface $eventDispatcher = null)
    {
        $this->connectionManager = $connectionManager;
        $this->eventDispatcher = null !== $eventDispatcher ? $eventDispatcher : new EventDispatcher();
    }

    /**
     * Run a Cypher statement against the default database or the database specified.
     *
     * @param $query
     * @param array|null $parameters
     * @param string|null $tag
     * @param string|null $connectionAlias
     *
     * @return Result|null
     *@throws Neo4jExceptionInterface
     *
     */
    public function run($query, array $parameters = null, string $tag = null, string $connectionAlias = null): ?Result
    {
        $connection = $this->connectionManager->getConnection($connectionAlias);
        $params = null !== $parameters ? $parameters : [];
        $statement = Statement::create($query, $params, $tag);
        $this->eventDispatcher->dispatch(new PreRunEvent([$statement]), Neo4jClientEvents::NEO4J_PRE_RUN);

        try {
            /** @var RecordCursorInterface $result */
            $result = $connection->run($query, $parameters, $tag);
            $this->eventDispatcher->dispatch(new PostRunEvent(ResultCollection::withResult($result)), Neo4jClientEvents::NEO4J_POST_RUN);
        } catch (Neo4jException $e) {
            $event = new FailureEvent($e);
            $this->eventDispatcher->dispatch($event, Neo4jClientEvents::NEO4J_ON_FAILURE);

            if ($event->shouldThrowException()) {
                throw $e;
            }

            return null;
        }

        return $result;
    }

    /**
     * @param string $query
     * @param array|null $parameters
     * @param string|null $tag
     *
     * @return Result
     *@throws Neo4jException
     *
     */
    public function runWrite(string $query, array $parameters = null, string $tag = null): Result
    {
        return $this->connectionManager
            ->getMasterConnection()
            ->run($query, $parameters, $tag);
    }

    /**
     * @param string      $query
     * @param array|null $parameters
     * @param string|null $tag
     *
     * @return Result
     * @throws Neo4jException
     *
     * @deprecated since 4.0 - will be removed in 5.0 - use <code>$client->runWrite()</code> instead
     *
     */
    public function sendWriteQuery(string $query, array $parameters = null, string $tag = null): Result
    {
        return $this->runWrite($query, $parameters, $tag);
    }

    /**
     * @param string|null $tag
     * @param string|null $connectionAlias
     *
     * @return StackInterface
     */
    public function stack(string $tag = null, string $connectionAlias = null): StackInterface
    {
        return Stack::create($tag, $connectionAlias);
    }

    /**
     * @param StackInterface $stack
     *
     * @return \GraphAware\Common\Result\ResultCollection|null
     * @throws Neo4jException
     *
     */
    public function runStack(StackInterface $stack): ?\GraphAware\Common\Result\ResultCollection
    {
        $connectionAlias = $stack->hasWrites()
            ? $this->connectionManager->getMasterConnection()->getAlias()
            : $stack->getConnectionAlias();
        $pipeline = $this->pipeline($stack->getTag(), $connectionAlias);

        foreach ($stack->statements() as $statement) {
            $pipeline->push($statement->text(), $statement->parameters(), $statement->getTag());
        }

        $this->eventDispatcher->dispatch(new PreRunEvent($stack->statements()), Neo4jClientEvents::NEO4J_PRE_RUN);

        try {
            $results = $pipeline->run();
            $this->eventDispatcher->dispatch(new PostRunEvent($results), Neo4jClientEvents::NEO4J_POST_RUN);
        } catch (Neo4jException $e) {
            $event = new FailureEvent($e);
            $this->eventDispatcher->dispatch($event, Neo4jClientEvents::NEO4J_ON_FAILURE);

            if ($event->shouldThrowException()) {
                throw $e;
            }

            return null;
        }

        return $results;
    }

    /**
     * @param string|null $connectionAlias
     *
     * @return Transaction
     */
    public function transaction(string $connectionAlias = null): Transaction
    {
        $connection = $this->connectionManager->getConnection($connectionAlias);
        $driverTransaction = $connection->getTransaction();

        return new Transaction($driverTransaction, $this->eventDispatcher);
    }

    /**
     * @param string|null $tag
     * @param string|null $connectionAlias
     *
     * @return PipelineInterface
     */
    private function pipeline(string $tag = null, string $connectionAlias = null): PipelineInterface
    {
        $connection = $this->connectionManager->getConnection($connectionAlias);

        return $connection->createPipeline(null, [], $tag);
    }

    /**
     * @param string|null $conn
     *
     * @return Label[]
     */
    public function getLabels(string $conn = null): array
    {
        $connection = $this->connectionManager->getConnection($conn);
        $result = $connection->getSession()->run('CALL db.labels()');

        return array_map(function (Record $record) {
            return new Label($record->get('label'));
        }, $result->records());
    }

    /**
     * @param string $query
     * @param array|null $parameters
     * @param string|null $tag
     * @param string|null $connectionAlias
     *
     * @return RecordCursorInterface|Result
     * @throws Neo4jException
     * @deprecated since 4.0 - will be removed in 5.0 - use <code>$client->run()</code> instead
     *
     */
    public function sendCypherQuery(string $query, array $parameters = null, string $tag = null, string $connectionAlias = null): RecordCursorInterface|Result
    {
        return $this->connectionManager
            ->getConnection($connectionAlias)
            ->run($query, $parameters, $tag);
    }

    /**
     * @return ConnectionManager
     */
    public function getConnectionManager(): ConnectionManager
    {
        return $this->connectionManager;
    }

    /**
     * @return EventDispatcherInterface|EventDispatcher
     */
    public function getEventDispatcher(): EventDispatcherInterface|EventDispatcher
    {
        return $this->eventDispatcher;
    }
}
