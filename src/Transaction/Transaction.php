<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\Client\Transaction;

use GraphAware\Bolt\Exception\MessageFailureException;
use GraphAware\Common\Cypher\Statement;
use GraphAware\Common\Result\RecordCursorInterface;
use GraphAware\Common\Result\Result;
use GraphAware\Common\Transaction\TransactionInterface;
use GraphAware\Neo4j\Client\Event\FailureEvent;
use GraphAware\Neo4j\Client\Event\PostRunEvent;
use GraphAware\Neo4j\Client\Event\PreRunEvent;
use GraphAware\Neo4j\Client\Exception\Neo4jException;
use GraphAware\Neo4j\Client\Neo4jClientEvents;
use GraphAware\Neo4j\Client\Result\ResultCollection;
use GraphAware\Neo4j\Client\StackInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Transaction
{
    /**
     * @var TransactionInterface
     */
    private TransactionInterface $driverTransaction;

    /**
     * @var Statement[]
     */
    protected array $queue = [];

    /**
     * @var EventDispatcherInterface
     */
    protected EventDispatcherInterface $eventDispatcher;

    /**
     * @param TransactionInterface $driverTransaction
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(TransactionInterface $driverTransaction, EventDispatcherInterface $eventDispatcher)
    {
        $this->driverTransaction = $driverTransaction;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Push a statement to the queue, without actually sending it.
     *
     * @param string $statement
     * @param array       $parameters
     * @param string|null $tag
     */
    public function push(string $statement, array $parameters = [], string $tag = null): void
    {
        $this->queue[] = Statement::create($statement, $parameters, $tag);
    }

    /**
     * @param string $statement
     * @param array $parameters
     * @param string|null $tag
     *
     * @return RecordCursorInterface|Result|null
     * @throws Neo4jException
     */
    public function run(string $statement, array $parameters = [], string $tag = null): RecordCursorInterface|Result|null
    {
        if (!$this->driverTransaction->isOpen() && !in_array($this->driverTransaction->status(), ['COMMITED', 'ROLLED_BACK'], true)) {
            $this->driverTransaction->begin();
        }
        $stmt = Statement::create($statement, $parameters, $tag);
        $this->eventDispatcher->dispatch(new PreRunEvent([$stmt]), Neo4jClientEvents::NEO4J_PRE_RUN);
        try {
            /** @var RecordCursorInterface $result */
            $result = $this->driverTransaction->run(Statement::create($statement, $parameters, $tag));
            $this->eventDispatcher->dispatch(new PostRunEvent(ResultCollection::withResult($result)), Neo4jClientEvents::NEO4J_POST_RUN);
        } catch (MessageFailureException $e) {
            $exception = new Neo4jException($e->getMessage());
            $exception->setNeo4jStatusCode($e->getStatusCode());

            $event = new FailureEvent($exception);
            $this->eventDispatcher->dispatch($event, Neo4jClientEvents::NEO4J_ON_FAILURE);
            if ($event->shouldThrowException()) {
                throw $exception;
            }
            return null;
        }

        return $result;
    }

    /**
     * Push a statements Stack to the queue, without actually sending it.
     *
     * @param StackInterface $stack
     */
    public function pushStack(StackInterface $stack): void
    {
        $this->queue[] = $stack;
    }

    /**
     * @param StackInterface $stack
     *
     * @return \GraphAware\Common\Result\ResultCollection|Result[]|null
     * @throws Neo4jException
     *
     */
    public function runStack(StackInterface $stack): array|\GraphAware\Common\Result\ResultCollection|null
    {
        if (!$this->driverTransaction->isOpen() && !in_array($this->driverTransaction->status(), ['COMMITED', 'ROLLED_BACK'], true)) {
            $this->driverTransaction->begin();
        }

        $sts = [];

        foreach ($stack->statements() as $statement) {
            $sts[] = $statement;
        }

        $this->eventDispatcher->dispatch(new PreRunEvent($stack->statements()), Neo4jClientEvents::NEO4J_PRE_RUN);
        try {
            $results = $this->driverTransaction->runMultiple($sts);
            $this->eventDispatcher->dispatch(new PostRunEvent($results), Neo4jClientEvents::NEO4J_POST_RUN);
        } catch (MessageFailureException $e) {
            $exception = new Neo4jException($e->getMessage());
            $exception->setNeo4jStatusCode($e->getStatusCode());

            $event = new FailureEvent($exception);
            $this->eventDispatcher->dispatch($event, Neo4jClientEvents::NEO4J_ON_FAILURE);
            if ($event->shouldThrowException()) {
                throw $exception;
            }
            return null;
        }

        return $results;
    }

    public function begin(): void
    {
        $this->driverTransaction->begin();
    }

    /**
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->driverTransaction->isOpen();
    }

    /**
     * @return bool
     */
    public function isCommited(): bool
    {
        return $this->driverTransaction->isCommited();
    }

    /**
     * @return bool
     */
    public function isRolledBack(): bool
    {
        return $this->driverTransaction->isRolledBack();
    }

    /**
     * @return string
     */
    public function status(): string
    {
        return $this->driverTransaction->status();
    }

    /**
     * @return \GraphAware\Common\Result\ResultCollection|array
     */
    public function commit(): \GraphAware\Common\Result\ResultCollection|array
    {
        if (!$this->driverTransaction->isOpen() && !in_array($this->driverTransaction->status(), ['COMMITED', 'ROLLED_BACK'], true)) {
            $this->driverTransaction->begin();
        }
        if (!empty($this->queue)) {
            $stack = [];
            foreach ($this->queue as $element) {
                if ($element instanceof StackInterface) {
                    foreach ($element->statements() as $statement) {
                        $stack[] = $statement;
                    }
                } else {
                    $stack[] = $element;
                }
            }

            $result = $this->driverTransaction->runMultiple($stack);
            $this->driverTransaction->commit();
            $this->queue = [];

            return $result;
        }

        return $this->driverTransaction->commit();
    }

    public function rollback()
    {
        return $this->driverTransaction->rollback();
    }
}
