<?php

namespace Anper\PredisCollector\Connection;

use Anper\PredisCollector\Profile;
use Predis\Command\CommandInterface;
use Predis\Connection\ConnectionInterface;
use Predis\Response\ErrorInterface;
use Anper\PredisCollector\Connection\ConnectionInterface as CollectorConnectionInterface;

/**
 * Class Connection
 * @package Anper\PredisCollector\Connection
 */
class Connection implements ConnectionInterface, CollectorConnectionInterface
{
    /**
     * @var Profile[]
     */
    protected $profiles = [];

    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @param ConnectionInterface $connection
     * @param string|null $name
     */
    public function __construct(ConnectionInterface $connection, string $name = null)
    {
        $this->connection = $connection;
        $this->name = $name;
    }

    /**
     * @param string|null $name
     * @return Connection
     */
    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return ConnectionInterface
     */
    public function getSourceConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return (string) ($this->name ?? $this->connection);
    }

    /**
     * @inheritDoc
     */
    public function connect()
    {
        $this->connection->connect();
    }

    /**
     * @inheritDoc
     */
    public function disconnect()
    {
        $this->connection->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function isConnected()
    {
        return $this->connection->isConnected();
    }

    /**
     * @inheritDoc
     */
    public function writeRequest(CommandInterface $command)
    {
        $this->connection->writeRequest($command);
    }

    /**
     * @inheritDoc
     */
    public function readResponse(CommandInterface $command)
    {
        return $this->connection->readResponse($command);
    }

    /**
     * @param CommandInterface $command
     * @return mixed
     * @throws \Exception
     */
    public function executeCommand(CommandInterface $command)
    {
        return $this->profileCall($command);
    }

    /**
     * @param mixed $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->connection->$name;
    }

    /**
     * @param mixed $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->connection->$name = $value;
    }

    /**
     * @param mixed $name
     * @param array $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        return \call_user_func_array([$this->connection, $name], $args);
    }

    /**
     * @return Profile[]
     */
    public function getProfiles(): array
    {
        return $this->profiles;
    }

    /**
     * @param Profile $profile
     */
    public function addProfile(Profile $profile): void
    {
        $this->profiles[] = $profile;
    }

    /**
     * @param CommandInterface $command
     * @return Profile
     */
    protected function createProfile(CommandInterface $command): Profile
    {
        return new Profile($command->getId(), $command->getArguments());
    }

    /**
     * @param CommandInterface $command
     * @return mixed
     * @throws \Exception
     */
    protected function profileCall(CommandInterface $command)
    {
        $profile = $this->createProfile($command);
        $profile->start();

        try {
            $result = $this->connection->executeCommand($command);
        } catch (\Exception $exception) {
            $profile->setError($exception->getMessage());

            throw $exception;
        } finally {
            $profile->end();
        }

        if (\is_object($result) && $result instanceof ErrorInterface) {
            $profile->setError($result->getMessage());
        }

        $profile->setResponse($result);

        $this->addProfile($profile);

        return $result;
    }
}
