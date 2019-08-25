<?php

namespace Ecotone\Modelling;

use Ecotone\Messaging\Handler\Enricher\PropertyPath;
use Ecotone\Messaging\Handler\Enricher\PropertyReaderAccessor;
use Ecotone\Messaging\Handler\TypeDescriptor;
use Ecotone\Messaging\Message;
use Ecotone\Messaging\MessageHeaders;
use Ecotone\Messaging\NullableMessageChannel;
use Ecotone\Messaging\Support\InvalidArgumentException;
use Ecotone\Messaging\Support\MessageBuilder;

/**
 * Class LoadAggregateService
 * @package Ecotone\Modelling
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
 * @internal
 */
class LoadAggregateService
{
    /**
     * @var AggregateRepository
     */
    private $aggregateRepository;
    /**
     * @var bool
     */
    private $isFactoryMethod;
    /**
     * @var string
     */
    private $aggregateClassName;
    /**
     * @var string
     */
    private $aggregateMethod;
    /**
     * @var array
     */
    private $aggregateIdentifierMapping;
    /**
     * @var PropertyReaderAccessor
     */
    private $propertyReaderAccessor;
    /**
     * @var null|string
     */
    private $expectedVersionName;
    /**
     * @var bool
     */
    private $dropMessageOnNotFound;
    /**
     * @var bool
     */
    private $loadForFactoryMethod;

    /**
     * ServiceCallToAggregateAdapter constructor.
     *
     * @param AggregateRepository $aggregateRepository
     * @param string $aggregateClassName
     * @param string $aggregateMethod
     * @param bool $isFactoryMethod
     * @param array $aggregateIdentifierMapping
     * @param null|string $expectedVersionName
     * @param PropertyReaderAccessor $propertyReaderAccessor
     * @param bool $dropMessageOnNotFound
     * @param bool $loadForFactoryMethod
     */
    public function __construct(AggregateRepository $aggregateRepository, string $aggregateClassName, string $aggregateMethod, bool $isFactoryMethod, array $aggregateIdentifierMapping, ?string $expectedVersionName, PropertyReaderAccessor $propertyReaderAccessor, bool $dropMessageOnNotFound, bool $loadForFactoryMethod)
    {
        $this->aggregateRepository          = $aggregateRepository;
        $this->isFactoryMethod = $isFactoryMethod;
        $this->aggregateClassName = $aggregateClassName;
        $this->aggregateMethod = $aggregateMethod;
        $this->aggregateIdentifierMapping = $aggregateIdentifierMapping;
        $this->propertyReaderAccessor = $propertyReaderAccessor;
        $this->expectedVersionName = $expectedVersionName;
        $this->dropMessageOnNotFound = $dropMessageOnNotFound;
        $this->loadForFactoryMethod = $loadForFactoryMethod;
    }

    /**
     * @param Message $message
     *
     * @return Message
     * @throws AggregateNotFoundException
     * @throws \ReflectionException
     * @throws \Ecotone\Messaging\MessagingException
     */
    public function load(Message $message) : ?Message
    {
        $aggregateIdentifiers = [];
        $expectedVersion = null;

        foreach ($this->aggregateIdentifierMapping as $aggregateIdentifierName => $aggregateIdentifierMappingName) {
            $aggregateIdentifiers[$aggregateIdentifierName] = ($this->isFactoryMethod && !$this->loadForFactoryMethod)
                ? null
                : (
                    $this->propertyReaderAccessor->hasPropertyValue(PropertyPath::createWith($aggregateIdentifierMappingName), $message->getPayload())
                    ? $this->propertyReaderAccessor->getPropertyValue(PropertyPath::createWith($aggregateIdentifierMappingName), $message->getPayload())
                    : null
                );
        }

        $aggregate = null;
        if (!$this->isFactoryMethod || $this->loadForFactoryMethod) {

            foreach ($aggregateIdentifiers as $identifierName => $aggregateIdentifier) {
                if (is_null($aggregateIdentifier)) {
                    $messageType = TypeDescriptor::createFromVariable($message->getPayload());
                    throw AggregateNotFoundException::create("Aggregate identifier {$identifierName} definition found in {$messageType->toString()}, but is null. Can't load aggregate {$this->aggregateClassName} to call {$this->aggregateMethod}.");
                }
            }

            $expectedVersion = $this->expectedVersionName
                ? (
                    $this->propertyReaderAccessor->hasPropertyValue(PropertyPath::createWith($this->expectedVersionName), $message->getPayload())
                    ? $this->propertyReaderAccessor->getPropertyValue(PropertyPath::createWith($this->expectedVersionName), $message->getPayload())
                    : null
                )
                : null;
            if ($this->expectedVersionName && !$expectedVersion) {
                throw InvalidArgumentException::create("Aggregate {$this->aggregateClassName}:{$this->aggregateMethod} has defined version locking, but no version during command handling was provided");
            }

            $aggregate = is_null($this->expectedVersionName)
                ? $this->aggregateRepository->findBy($this->aggregateClassName, $aggregateIdentifiers)
                : $this->aggregateRepository->findWithLockingBy($this->aggregateClassName, $aggregateIdentifiers, $expectedVersion);

            if (!$aggregate && $this->dropMessageOnNotFound) {
                return null;
            }

            if (!$aggregate && !$this->loadForFactoryMethod) {
                throw AggregateNotFoundException::create("Aggregate {$this->aggregateClassName} was not found for indentifiers " . \json_encode($aggregateIdentifiers));
            }
        }

        $messageBuilder = MessageBuilder::fromMessage($message);
        if ($aggregate) {
            $messageBuilder = $messageBuilder->setHeader(AggregateMessage::AGGREGATE_OBJECT, $aggregate);
        }
        if ($expectedVersion) {
            $messageBuilder = $messageBuilder->setHeader(AggregateMessage::EXPECTED_VERSION, $expectedVersion);
        }

        if (!$message->getHeaders()->containsKey(MessageHeaders::REPLY_CHANNEL)) {
            $messageBuilder = $messageBuilder
                                ->setReplyChannel(NullableMessageChannel::create());
        }

        return $messageBuilder
            ->setHeader(AggregateMessage::CLASS_NAME, $this->aggregateClassName)
            ->setHeader(AggregateMessage::METHOD_NAME, $this->aggregateMethod)
            ->setHeader(AggregateMessage::AGGREGATE_ID, $aggregateIdentifiers)
            ->setHeader(AggregateMessage::IS_FACTORY_METHOD, $this->isFactoryMethod)
            ->setHeader(AggregateMessage::CALLING_MESSAGE, $message)
            ->build();
    }
}