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

use GraphAware\Common\Result\AbstractRecordCursor;
use GraphAware\Common\Result\Result;
use GraphAware\Neo4j\Client\Connection\ConnectionManager;
use GraphAware\Neo4j\Client\Exception\Neo4jException;
use GraphAware\Neo4j\Client\Exception\Neo4jExceptionInterface;
use GraphAware\Neo4j\Client\Result\ResultCollection;
use GraphAware\Neo4j\Client\Transaction\Transaction;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Interface ClientInterface.
 */
interface ClientInterface
{
    /**
     * Run a Cypher statement against the default database or the database specified.
     *
     * @throws Neo4jExceptionInterface
     */
    public function run($query, array $parameters = null, string $tag = null, string $connectionAlias = null): Result;

    /**
     * @throws Neo4jException
     */
    public function runWrite(string $query, array $parameters = null, string $tag = null): AbstractRecordCursor;

    /**
     * @throws Neo4jException
     * @deprecated since 4.0 - will be removed in 5.0 - use <code>$client->runWrite()</code> instead
     */
    public function sendWriteQuery(string $query, array $parameters = null, string $tag = null): AbstractRecordCursor;

    public function stack(string $tag = null, string $connectionAlias = null): Stack;

    public function runStack(StackInterface $stack): ?ResultCollection;

    public function transaction(string $connectionAlias = null): Transaction;

    public function getLabels(string $conn = null): array;

    /**
     *@deprecated since 4.0 - will be removed in 5.0 - use <code>$client->run()</code> instead
     */
    public function sendCypherQuery(string $query, array $parameters = null, string $tag = null, string $connectionAlias = null): AbstractRecordCursor;

    public function getConnectionManager(): ConnectionManager;

    public function getEventDispatcher(): EventDispatcherInterface;
}
