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
use GraphAware\Common\Driver\PipelineInterface;
use GraphAware\Common\Result\ResultCollection;
use GraphAware\Neo4j\Client\Exception\Neo4jException;

class Pipeline implements PipelineInterface
{
    /**
     * @var Session
     */
    protected Session $session;

    /**
     * @var Statement[]
     */
    protected array $statements = [];

    /**
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function push($query, array $parameters = [], $tag = null): void
    {
        $this->statements[] = Statement::create($query, $parameters, $tag);
    }

    /**
     * {@inheritdoc}
     * @throws Neo4jException
     */
    public function run(): ResultCollection
    {
        return $this->session->flush($this);
    }

    /**
     * @return Statement[]
     */
    public function statements(): array
    {
        return $this->statements;
    }

    /**
     * @return int
     */
    public function size(): int
    {
        return count($this->statements);
    }
}
