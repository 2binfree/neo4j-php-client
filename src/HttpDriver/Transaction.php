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

use GraphAware\Common\Cypher\Statement;
use GraphAware\Common\Result\RecordCursorInterface;
use GraphAware\Common\Result\ResultCollection;
use GraphAware\Common\Transaction\TransactionInterface;
use GraphAware\Neo4j\Client\Exception\Neo4jException;
use GraphAware\Neo4j\Client\Exception\Neo4jExceptionInterface;

class Transaction implements TransactionInterface
{
    const OPENED = 'OPEN';

    const COMMITED = 'COMMITED';

    const ROLLED_BACK = 'ROLLED_BACK';

    protected string $state;

    /**
     * @var Session
     */
    protected Session $session;

    /**
     * @var bool
     */
    protected bool $closed = false;

    /**
     * @var int|null
     */
    protected ?int $transactionId;

    protected $expires;

    protected array $pending = [];

    /**
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
        $this->session->transaction = $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isOpen(): bool
    {
        return $this->state === self::OPENED;
    }

    /**
     * {@inheritdoc}
     */
    public function isCommited(): bool
    {
        return $this->state === self::COMMITED;
    }

    /**
     * {@inheritdoc}
     */
    public function isRolledBack(): bool
    {
        return $this->state === self::ROLLED_BACK;
    }

    /**
     * {@inheritdoc}
     * @throws Neo4jException
     */
    public function rollback(): void
    {
        $this->assertNotClosed();
        $this->assertStarted();
        $this->session->rollbackTransaction($this->transactionId);
        $this->closed = true;
        $this->state = self::ROLLED_BACK;
        $this->session->transaction = null;
    }

    /**
     * {@inheritdoc}
     */
    public function status(): string
    {
        return $this->state;
    }

    /**
     * {@inheritdoc}
     * @throws Neo4jException
     */
    public function commit(): void
    {
        $this->success();
    }

    /**
     * {@inheritdoc}
     */
    public function push($statement, array $parameters = [], $tag = null)
    {
    }

    public function getStatus(): string
    {
        return $this->state;
    }

    /**
     * @throws Neo4jException
     */
    public function begin(): void
    {
        $this->assertNotStarted();
        $response = $this->session->begin();
        $body = json_decode($response->getBody(), true);
        $parts = explode('/', $body['commit']);
        $this->transactionId = (int) $parts[count($parts) - 2];
        $this->state = self::OPENED;
        $this->session->transaction = $this;
    }

    /**
     * @param Statement $statement
     *
     * @throws Neo4jException
     *
     * @return RecordCursorInterface
     */
    public function run(Statement $statement)
    {
        $this->assertStarted();
        try {
            $results = $this->session->pushToTransaction($this->transactionId, [$statement]);

            return $results->results()[0];
        } catch (Neo4jException $e) {
            if ($e->effect() === Neo4jExceptionInterface::EFFECT_ROLLBACK) {
                $this->closed = true;
                $this->state = self::ROLLED_BACK;
            }

            throw $e;
        }
    }

    /**
     * @param array $statements
     *
     * @throws Neo4jException
     *
     * @return ResultCollection
     */
    public function runMultiple(array $statements): ResultCollection
    {
        try {
            return $this->session->pushToTransaction($this->transactionId, $statements);
        } catch (Neo4jException $e) {
            if ($e->effect() === Neo4jExceptionInterface::EFFECT_ROLLBACK) {
                $this->closed = true;
                $this->state = self::ROLLED_BACK;
            }

            throw $e;
        }
    }

    /**
     * @throws Neo4jException
     */
    public function success(): void
    {
        $this->assertNotClosed();
        $this->assertStarted();
        try {
            $this->session->commitTransaction($this->transactionId);
        } catch (Neo4jException $e) {
            if ($e->effect() === Neo4jExceptionInterface::EFFECT_ROLLBACK) {
                $this->state = self::ROLLED_BACK;
            }

            throw $e;
        }
        $this->state = self::COMMITED;
        $this->closed = true;
        $this->session->transaction = null;
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    private function assertStarted(): void
    {
        if ($this->state !== self::OPENED) {
            throw new \RuntimeException('This transaction has not been started');
            //$this->begin();
        }
    }

    private function assertNotStarted(): void
    {
        if (null !== $this->state) {
            throw new \RuntimeException(sprintf('Can not begin transaction, Transaction State is "%s"', $this->state));
        }
    }

    private function assertNotClosed(): void
    {
        if (false !== $this->closed) {
            throw new \RuntimeException('This Transaction is closed');
        }
    }
}
