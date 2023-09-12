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

class Stack implements StackInterface
{
    protected ?string $tag;

    protected ?string $connectionAlias;

    protected array $statements = [];

    protected array $preflights = [];

    protected bool $hasWrites = false;

    public function __construct($tag = null, string $connectionAlias = null)
    {
        $this->tag = null !== $tag ? (string) $tag : null;
        $this->connectionAlias = $connectionAlias;
    }

    public static function create(string $tag = null, string $connectionAlias = null): Stack
    {
        return new static($tag, $connectionAlias);
    }

    public function push(string $query, array $parameters = null, array $tag = null): void
    {
        $params = null !== $parameters ? $parameters : [];
        $this->statements[] = Statement::create($query, $params, $tag);
    }

    public function pushWrite(string $query, array $parameters = null, array $tag = null): void
    {
        $params = null !== $parameters ? $parameters : [];
        $this->statements[] = Statement::create($query, $params, $tag);
        $this->hasWrites = true;
    }

    public function addPreflight($query, array $parameters = null, array $tag = null): void
    {
        $params = null !== $parameters ? $parameters : [];
        $this->preflights[] = Statement::create($query, $params, $tag);
    }

    public function hasPreflights(): bool
    {
        return !empty($this->preflights);
    }

    public function getPreflights(): array
    {
        return $this->preflights;
    }

    public function size(): int
    {
        return count($this->statements);
    }

    /**
     * @return Statement[]
     */
    public function statements(): array
    {
        return $this->statements;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function getConnectionAlias(): ?string
    {
        return $this->connectionAlias;
    }

    public function hasWrites(): bool
    {
        return $this->hasWrites;
    }
}
