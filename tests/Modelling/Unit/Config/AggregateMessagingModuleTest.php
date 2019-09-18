<?php

namespace Test\Ecotone\Modelling\Unit\Config;

use Doctrine\Common\Annotations\AnnotationException;
use Ecotone\Messaging\Channel\SimpleMessageChannelBuilder;
use Ecotone\Messaging\Config\Annotation\AnnotationRegistrationService;
use Ecotone\Messaging\Config\Annotation\InMemoryAnnotationRegistrationService;
use Ecotone\Messaging\Config\Configuration;
use Ecotone\Messaging\Config\ConfigurationException;
use Ecotone\Messaging\Config\InMemoryModuleMessaging;
use Ecotone\Messaging\Config\MessagingSystemConfiguration;
use Ecotone\Messaging\Config\ModuleReferenceSearchService;
use Ecotone\Messaging\Handler\Bridge\BridgeBuilder;
use Ecotone\Messaging\Handler\InterfaceToCall;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\AllHeadersBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\PayloadBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\Converter\ReferenceBuilder;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\MethodInterceptor;
use Ecotone\Messaging\Handler\ServiceActivator\ServiceActivatorBuilder;
use Ecotone\Messaging\Handler\TypeDescriptor;
use Ecotone\Messaging\MessagingException;
use Ecotone\Messaging\Support\InvalidArgumentException;
use Ecotone\Modelling\AggregateMessage;
use Ecotone\Modelling\AggregateMessageConversionService;
use Ecotone\Modelling\AggregateMessageConversionServiceBuilder;
use Ecotone\Modelling\AggregateMessageHandlerBuilder;
use Ecotone\Modelling\Annotation\CommandHandler;
use Ecotone\Modelling\Annotation\QueryHandler;
use Ecotone\Modelling\Config\AggregateMessagingModule;
use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use stdClass;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Aggregate\AggregateCommandHandlerExample;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Aggregate\AggregateCommandHandlerWithNoCommandDataExample;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Aggregate\AggregateCommandHandlerWithReferencesExample;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Aggregate\AggregateNoInputChannelAndNoMessage;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Aggregate\DoStuffCommand;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Service\CommandHandlerWithAnnotationClassNameWithMetadataAndService;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Service\CommandHandlerWithAnnotationClassNameWithService;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Service\CommandHandlerWithClassNameInAnnotation;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Service\CommandHandlerWithNoCommandInformationConfiguration;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Service\CommandHandlerWithReturnValue;
use Test\Ecotone\Modelling\Fixture\Annotation\CommandHandler\Service\SomeCommand;
use Test\Ecotone\Modelling\Fixture\Annotation\EventHandler\ExampleEventEventHandler;
use Test\Ecotone\Modelling\Fixture\Annotation\EventHandler\ExampleEventHandlerWithServices;
use Test\Ecotone\Modelling\Fixture\Annotation\QueryHandler\AggregateQueryHandlerExample;
use Test\Ecotone\Modelling\Fixture\Annotation\QueryHandler\AggregateQueryHandlerWithOutputChannelExample;
use Test\Ecotone\Modelling\Fixture\Annotation\QueryHandler\QueryHandlerWithNoReturnValue;
use Test\Ecotone\Modelling\Fixture\Annotation\QueryHandler\SomeQuery;
use Test\Ecotone\Modelling\Fixture\CommandHandler\MultiMethod\MultiMethodAggregateCommandHandlerExample;
use Test\Ecotone\Modelling\Fixture\CommandHandler\MultiMethod\MultiMethodServiceCommandHandlerExample;

/**
 * Class IntegrationMessagingCqrsModule
 * @package Test\Ecotone\Modelling\Config
 * @author  Dariusz Gafka <dgafka.mail@gmail.com>
 */
class AggregateMessagingModuleTest extends TestCase
{
    /**
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws MessagingException
     */
    public function test_throwing_configuration_exception_if_command_handler_has_no_information_about_command()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->prepareConfiguration(
            InMemoryAnnotationRegistrationService::createFrom([
                CommandHandlerWithNoCommandInformationConfiguration::class
            ])
        );
    }

    /**
     * @param AnnotationRegistrationService $annotationRegistrationService
     * @return MessagingSystemConfiguration
     * @throws MessagingException
     */
    private function prepareConfiguration(AnnotationRegistrationService $annotationRegistrationService): MessagingSystemConfiguration
    {
        $cqrsMessagingModule = AggregateMessagingModule::create($annotationRegistrationService);

        $extendedConfiguration = $this->createMessagingSystemConfiguration();
        $cqrsMessagingModule->prepare(
            $extendedConfiguration,
            [],
            ModuleReferenceSearchService::createEmpty()
        );

        return $extendedConfiguration;
    }

    /**
     * @return MessagingSystemConfiguration
     * @throws MessagingException
     */
    protected function createMessagingSystemConfiguration(): Configuration
    {
        return MessagingSystemConfiguration::prepare(InMemoryModuleMessaging::createEmpty());
    }

    /**
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws MessagingException
     */
    public function test_throwing_exception_if_query_handler_has_no_return_value()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->prepareConfiguration(
            InMemoryAnnotationRegistrationService::createFrom([
                QueryHandlerWithNoReturnValue::class
            ])
        );
    }

    public function test_resulting_in_exception_when_registering_commands_handlers_for_same_input_channel()
    {
        $this->expectException(ConfigurationException::class);

        $commandHandlerAnnotation = new CommandHandler();

        $this->prepareConfiguration(
            InMemoryAnnotationRegistrationService::createFrom([
                AggregateCommandHandlerExample::class
            ])
                ->addAnnotationToClassMethod(AggregateCommandHandlerExample::class, "doAnotherAction", $commandHandlerAnnotation)
        );
    }

    public function test_resulting_in_exception_when_registering_query_handlers_for_same_input_channel()
    {
        $this->expectException(ConfigurationException::class);

        $queryHandlerAnnotation = new QueryHandler();

        $this->prepareConfiguration(
            InMemoryAnnotationRegistrationService::createFrom([
                AggregateQueryHandlerExample::class
            ])
                ->addAnnotationToClassMethod(AggregateQueryHandlerExample::class, "doAnotherAction", $queryHandlerAnnotation)
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws AnnotationException
     * @throws Exception
     * @throws ReflectionException
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function test_registering_aggregate_command_handler()
    {
        $commandHandler = AggregateMessageHandlerBuilder::createAggregateCommandHandlerWith(AggregateCommandHandlerExample::class, "doAction", DoStuffCommand::class)
            ->withMethodParameterConverters([
                PayloadBuilder::create("command")
            ])
            ->withInputChannelName("command-id")
            ->withEndpointId('command-id');

        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler($commandHandler)
            ->registerMessageHandler(
                BridgeBuilder::create()
                    ->withEndpointId(DoStuffCommand::class . ".command-id")
                    ->withInputChannelName(DoStuffCommand::class)
                    ->withOutputMessageChannel("command-id")
            )
            ->registerDefaultChannelFor(SimpleMessageChannelBuilder::createPublishSubscribeChannel(DoStuffCommand::class))
            ->registerBeforeMethodInterceptor(
                MethodInterceptor::create(
                    "",
                    InterfaceToCall::create(AggregateMessageConversionService::class, "convert"),
                    AggregateMessageConversionServiceBuilder::createWith(DoStuffCommand::class),
                    AggregateMessage::BEFORE_CONVERTER_INTERCEPTOR_PRECEDENCE,
                    AggregateCommandHandlerExample::class . "::doAction"
                )
            );

        $this->createModuleAndAssertConfiguration(
            [
                AggregateCommandHandlerExample::class
            ],
            $expectedConfiguration,
            [
                DoStuffCommand::class => DoStuffCommand::class
            ]
        );
    }


    /**
     * @param array $annotationClassesToRegister
     * @param Configuration $expectedConfiguration
     * @param array $messageMapping
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws MessagingException
     */
    private function createModuleAndAssertConfiguration(array $annotationClassesToRegister, Configuration $expectedConfiguration, array $messageMapping): void
    {
        $this->assertEquals(
            $expectedConfiguration,
            $this->prepareConfiguration(InMemoryAnnotationRegistrationService::createFrom($annotationClassesToRegister))
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws AnnotationException
     * @throws Exception
     * @throws ReflectionException
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function test_registering_aggregate_command_handler_with_no_command_data()
    {
        $commandHandler = AggregateMessageHandlerBuilder::createAggregateCommandHandlerWith(AggregateCommandHandlerWithNoCommandDataExample::class, "doAction", null)
            ->withMethodParameterConverters([
                ReferenceBuilder::create("class", \stdClass::class)
            ])
            ->withInputChannelName("command-id")
            ->withEndpointId('command-id');

        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler($commandHandler)
            ->registerMessageHandler(
                BridgeBuilder::create()
                    ->withEndpointId("doActionChannel.command-id")
                    ->withInputChannelName("doActionChannel")
                    ->withOutputMessageChannel("command-id")
            )
            ->registerDefaultChannelFor(SimpleMessageChannelBuilder::createPublishSubscribeChannel("doActionChannel"))
            ->registerBeforeMethodInterceptor(
                MethodInterceptor::create(
                    "",
                    InterfaceToCall::create(AggregateMessageConversionService::class, "convert"),
                    AggregateMessageConversionServiceBuilder::createWith(TypeDescriptor::ARRAY),
                    AggregateMessage::BEFORE_CONVERTER_INTERCEPTOR_PRECEDENCE,
                    AggregateCommandHandlerWithNoCommandDataExample::class . "::doAction"
                )
            );

        $this->createModuleAndAssertConfiguration(
            [
                AggregateCommandHandlerWithNoCommandDataExample::class
            ],
            $expectedConfiguration,
            [
                "doActionChannel" => "doActionChannel"
            ]
        );
    }

    public function test_registering_service_command_handler_with_return_value()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler(
                ServiceActivatorBuilder::create(CommandHandlerWithReturnValue::class, "execute")
                    ->withMethodParameterConverters([
                        PayloadBuilder::create("command"),
                        ReferenceBuilder::create("service1", stdClass::class)
                    ])
                    ->withInputChannelName('command-id')
                    ->withEndpointId('command-id')
            )
            ->registerMessageHandler(
                BridgeBuilder::create()
                    ->withEndpointId("input.command-id")
                    ->withInputChannelName("input")
                    ->withOutputMessageChannel("command-id")
            )
            ->registerDefaultChannelFor(SimpleMessageChannelBuilder::createPublishSubscribeChannel("input"));

        $this->createModuleAndAssertConfiguration(
            [
                CommandHandlerWithReturnValue::class
            ],
            $expectedConfiguration,
            [
                SomeCommand::class => "input"
            ]
        );
    }

    public function test_registering_service_two_command_handler_under_same_channel()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler(
                ServiceActivatorBuilder::create(MultiMethodServiceCommandHandlerExample::class, "doAction1")
                    ->withMethodParameterConverters([
                        PayloadBuilder::create("data")
                    ])
                    ->withInputChannelName("1")
                    ->withEndpointId("1")
            )
            ->registerMessageHandler(
                BridgeBuilder::create()
                    ->withEndpointId("register.1")
                    ->withInputChannelName("register")
                    ->withOutputMessageChannel("1")
            )
            ->registerMessageHandler(
                ServiceActivatorBuilder::create(MultiMethodServiceCommandHandlerExample::class, "doAction2")
                    ->withMethodParameterConverters([
                        PayloadBuilder::create("data")
                    ])
                    ->withInputChannelName("2")
                    ->withEndpointId("2")
            )
            ->registerMessageHandler(
                BridgeBuilder::create()
                    ->withEndpointId("register.2")
                    ->withInputChannelName("register")
                    ->withOutputMessageChannel("2")
            )
            ->registerDefaultChannelFor(SimpleMessageChannelBuilder::createPublishSubscribeChannel("register"));

        $this->createModuleAndAssertConfiguration(
            [
                MultiMethodServiceCommandHandlerExample::class
            ],
            $expectedConfiguration,
            [
                SomeCommand::class => "input"
            ]
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws AnnotationException
     * @throws Exception
     * @throws ReflectionException
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function test_registering_aggregate_two_command_handler_under_same_channel()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler(
                AggregateMessageHandlerBuilder::createAggregateCommandHandlerWith(MultiMethodAggregateCommandHandlerExample::class, "doAction1", null)
                    ->withMethodParameterConverters([
                        PayloadBuilder::create("data")
                    ])
                    ->withInputChannelName("1")
                    ->withEndpointId('1')
            )
            ->registerBeforeMethodInterceptor(
                MethodInterceptor::create(
                    "",
                    InterfaceToCall::create(AggregateMessageConversionService::class, "convert"),
                    AggregateMessageConversionServiceBuilder::createWith(TypeDescriptor::ARRAY),
                    AggregateMessage::BEFORE_CONVERTER_INTERCEPTOR_PRECEDENCE,
                    MultiMethodAggregateCommandHandlerExample::class . "::doAction1"
                )
            )
            ->registerMessageHandler(
                BridgeBuilder::create()
                    ->withEndpointId("register.1")
                    ->withInputChannelName("register")
                    ->withOutputMessageChannel("1")
            )
            ->registerMessageHandler(
                AggregateMessageHandlerBuilder::createAggregateCommandHandlerWith(MultiMethodAggregateCommandHandlerExample::class, "doAction2", null)
                    ->withMethodParameterConverters([
                        PayloadBuilder::create("data")
                    ])
                    ->withInputChannelName("2")
                    ->withEndpointId('2')
            )
            ->registerBeforeMethodInterceptor(
                MethodInterceptor::create(
                    "",
                    InterfaceToCall::create(AggregateMessageConversionService::class, "convert"),
                    AggregateMessageConversionServiceBuilder::createWith(TypeDescriptor::ARRAY),
                    AggregateMessage::BEFORE_CONVERTER_INTERCEPTOR_PRECEDENCE,
                    MultiMethodAggregateCommandHandlerExample::class . "::doAction2"
                )
            )
            ->registerMessageHandler(
                BridgeBuilder::create()
                    ->withEndpointId("register.2")
                    ->withInputChannelName("register")
                    ->withOutputMessageChannel("2")
            )
            ->registerDefaultChannelFor(SimpleMessageChannelBuilder::createPublishSubscribeChannel("register"));

        $this->createModuleAndAssertConfiguration(
            [
                MultiMethodAggregateCommandHandlerExample::class
            ],
            $expectedConfiguration,
            [
                DoStuffCommand::class => DoStuffCommand::class
            ]
        );
    }

    public function test_registering_handler_with_class_name_in_annotation()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler(
                ServiceActivatorBuilder::create(CommandHandlerWithClassNameInAnnotation::class, "execute")
                    ->withInputChannelName("command-id")
                    ->withEndpointId('command-id')
            )
            ->registerMessageHandler(
                BridgeBuilder::create()
                    ->withEndpointId("input.command-id")
                    ->withInputChannelName("input")
                    ->withOutputMessageChannel("command-id")
            )
            ->registerDefaultChannelFor(SimpleMessageChannelBuilder::createPublishSubscribeChannel("input"));

        $this->createModuleAndAssertConfiguration(
            [
                CommandHandlerWithClassNameInAnnotation::class
            ],
            $expectedConfiguration,
            [
                SomeCommand::class => "input"
            ]
        );
    }

    public function test_registering_handler_with_ignore_message_in_annotation_and_metadata_and_service_injected()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler(
                ServiceActivatorBuilder::create(CommandHandlerWithAnnotationClassNameWithMetadataAndService::class, "execute")
                    ->withMethodParameterConverters([
                        AllHeadersBuilder::createWith("metadata"),
                        ReferenceBuilder::create("service", stdClass::class)
                    ])
                    ->withInputChannelName("command-id")
                    ->withEndpointId('command-id')
            )
            ->registerMessageHandler(
                BridgeBuilder::create()
                    ->withEndpointId("input.command-id")
                    ->withInputChannelName("input")
                    ->withOutputMessageChannel("command-id")
            )
            ->registerDefaultChannelFor(SimpleMessageChannelBuilder::createPublishSubscribeChannel("input"));

        $this->createModuleAndAssertConfiguration(
            [
                CommandHandlerWithAnnotationClassNameWithMetadataAndService::class
            ],
            $expectedConfiguration,
            [
                SomeCommand::class => "input"
            ]
        );
    }

    public function test_throwing_exception_if_no_message_defined_and_no_input_channel_passed()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->prepareConfiguration(
            InMemoryAnnotationRegistrationService::createFrom([
                AggregateNoInputChannelAndNoMessage::class
            ])
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws AnnotationException
     * @throws Exception
     * @throws ReflectionException
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function test_registering_aggregate_command_handler_with_extra_services()
    {
        $commandHandler = AggregateMessageHandlerBuilder::createAggregateCommandHandlerWith(AggregateCommandHandlerWithReferencesExample::class, "doAction", DoStuffCommand::class)
            ->withInputChannelName("command-id-with-references")
            ->withMethodParameterConverters([
                PayloadBuilder::create("command"),
                ReferenceBuilder::create("injectedService", stdClass::class)
            ])
            ->withEndpointId('command-id-with-references');

        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler($commandHandler)
            ->registerMessageHandler(
                BridgeBuilder::create()
                    ->withEndpointId("input.command-id-with-references")
                    ->withInputChannelName("input")
                    ->withOutputMessageChannel("command-id-with-references")
            )
            ->registerDefaultChannelFor(SimpleMessageChannelBuilder::createPublishSubscribeChannel("input"))
            ->registerBeforeMethodInterceptor(
                MethodInterceptor::create(
                    "",
                    InterfaceToCall::create(AggregateMessageConversionService::class, "convert"),
                    AggregateMessageConversionServiceBuilder::createWith(DoStuffCommand::class),
                    AggregateMessage::BEFORE_CONVERTER_INTERCEPTOR_PRECEDENCE,
                    AggregateCommandHandlerWithReferencesExample::class . "::doAction"
                )
            );

        $this->createModuleAndAssertConfiguration(
            [
                AggregateCommandHandlerWithReferencesExample::class
            ],
            $expectedConfiguration,
            [
                DoStuffCommand::class => "input"
            ]
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws AnnotationException
     * @throws Exception
     * @throws ReflectionException
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function test_registering_aggregate_query_handler()
    {
        $commandHandler = AggregateMessageHandlerBuilder::createAggregateQueryHandlerWith(AggregateQueryHandlerExample::class, "doStuff", SomeQuery::class)
            ->withMethodParameterConverters([
                PayloadBuilder::create("query")
            ])
            ->withInputChannelName(SomeQuery::class)
            ->withEndpointId('some-id');

        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler($commandHandler)
            ->registerBeforeMethodInterceptor(
                MethodInterceptor::create(
                    "",
                    InterfaceToCall::create(AggregateMessageConversionService::class, "convert"),
                    AggregateMessageConversionServiceBuilder::createWith(SomeQuery::class),
                    AggregateMessage::BEFORE_CONVERTER_INTERCEPTOR_PRECEDENCE,
                    AggregateQueryHandlerExample::class . "::doStuff"
                )
            );

        $this->createModuleAndAssertConfiguration(
            [
                AggregateQueryHandlerExample::class
            ],
            $expectedConfiguration,
            [
                SomeQuery::class => SomeQuery::class
            ]
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws AnnotationException
     * @throws Exception
     * @throws ReflectionException
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function test_registering_aggregate_query_handler_with_output_channel()
    {
        $commandHandler = AggregateMessageHandlerBuilder::createAggregateQueryHandlerWith(AggregateQueryHandlerWithOutputChannelExample::class, "doStuff", SomeQuery::class)
            ->withMethodParameterConverters([
                PayloadBuilder::create("query")
            ])
            ->withInputChannelName(SomeQuery::class)
            ->withEndpointId("some-id")
            ->withOutputMessageChannel("outputChannel");

        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler($commandHandler)
            ->registerBeforeMethodInterceptor(MethodInterceptor::create(
                "",
                InterfaceToCall::create(AggregateMessageConversionService::class, "convert"),
                AggregateMessageConversionServiceBuilder::createWith(SomeQuery::class),
                AggregateMessage::BEFORE_CONVERTER_INTERCEPTOR_PRECEDENCE,
                AggregateQueryHandlerWithOutputChannelExample::class . "::doStuff"
            ));

        $this->createModuleAndAssertConfiguration(
            [
                AggregateQueryHandlerWithOutputChannelExample::class
            ],
            $expectedConfiguration,
            [
                SomeQuery::class => SomeQuery::class
            ]
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws AnnotationException
     * @throws Exception
     * @throws ReflectionException
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function test_registering_aggregate_with_custom_input_channel()
    {
        $commandHandler = AggregateMessageHandlerBuilder::createAggregateQueryHandlerWith(AggregateQueryHandlerWithOutputChannelExample::class, "doStuff", SomeQuery::class)
            ->withMethodParameterConverters([
                PayloadBuilder::create("query")
            ])
            ->withInputChannelName("inputChannel")
            ->withEndpointId("some-id");

        $customQueryHandler = new QueryHandler();
        $customQueryHandler->endpointId = "some-id";
        $customQueryHandler->inputChannelName = "inputChannel";

        $this->createModuleWithCustomConfigAndAssertConfiguration(
            InMemoryAnnotationRegistrationService::createFrom([
                AggregateQueryHandlerWithOutputChannelExample::class
            ])
                ->addAnnotationToClassMethod(AggregateQueryHandlerWithOutputChannelExample::class, "doStuff", $customQueryHandler),
            $this->createMessagingSystemConfiguration()
                ->registerMessageHandler($commandHandler)
                ->registerBeforeMethodInterceptor(MethodInterceptor::create(
                    "",
                    InterfaceToCall::create(AggregateMessageConversionService::class, "convert"),
                    AggregateMessageConversionServiceBuilder::createWith(SomeQuery::class),
                    AggregateMessage::BEFORE_CONVERTER_INTERCEPTOR_PRECEDENCE,
                    AggregateQueryHandlerWithOutputChannelExample::class . "::doStuff"
                )),
            [
                SomeQuery::class => "inputChannel"
            ]
        );
    }

    /**
     * @param AnnotationRegistrationService $annotationRegistrationService
     * @param Configuration $expectedConfiguration
     * @param array $messageMapping
     * @throws MessagingException
     */
    private function createModuleWithCustomConfigAndAssertConfiguration(AnnotationRegistrationService $annotationRegistrationService, Configuration $expectedConfiguration, array $messageMapping): void
    {
        $this->assertEquals(
            $expectedConfiguration,
            $this->prepareConfiguration($annotationRegistrationService)
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws AnnotationException
     * @throws Exception
     * @throws ReflectionException
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function test_registering_aggregate_without_query_class_with_only_input_channel()
    {
        $commandHandler = AggregateMessageHandlerBuilder::createAggregateQueryHandlerWith(AggregateQueryHandlerWithOutputChannelExample::class, "doStuff", SomeQuery::class)
            ->withMethodParameterConverters([
                PayloadBuilder::create("query")
            ])
            ->withInputChannelName("inputChannel")
            ->withEndpointId("some-id");

        $customQueryHandler = new QueryHandler();
        $customQueryHandler->endpointId = "some-id";
        $customQueryHandler->inputChannelName = "inputChannel";

        $this->createModuleWithCustomConfigAndAssertConfiguration(
            InMemoryAnnotationRegistrationService::createFrom([
                AggregateQueryHandlerWithOutputChannelExample::class
            ])
                ->addAnnotationToClassMethod(AggregateQueryHandlerWithOutputChannelExample::class, "doStuff", $customQueryHandler),
            $this->createMessagingSystemConfiguration()
                ->registerMessageHandler($commandHandler)
                ->registerBeforeMethodInterceptor(MethodInterceptor::create(
                    "",
                    InterfaceToCall::create(AggregateMessageConversionService::class, "convert"),
                    AggregateMessageConversionServiceBuilder::createWith(SomeQuery::class),
                    AggregateMessage::BEFORE_CONVERTER_INTERCEPTOR_PRECEDENCE,
                    AggregateQueryHandlerWithOutputChannelExample::class . "::doStuff"
                )),
            [
                SomeQuery::class => "inputChannel"
            ]
        );
    }

    /**
     * @throws InvalidArgumentException
     * @throws AnnotationException
     * @throws Exception
     * @throws ReflectionException
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function test_registering_service_event_handler()
    {
        $commandHandler = ServiceActivatorBuilder::create(ExampleEventEventHandler::class, "doSomething")
            ->withInputChannelName('some-id')
            ->withEndpointId('some-id');

        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler($commandHandler);

        $this->createModuleAndAssertConfiguration(
            [
                ExampleEventEventHandler::class
            ],
            $expectedConfiguration,
            [
                DoStuffCommand::class => "someInput"
            ]
        );
    }

    /**
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws ConfigurationException
     * @throws MessagingException
     */
    public function test_registering_service_event_handler_with_extra_services()
    {
        $commandHandler = ServiceActivatorBuilder::create(ExampleEventHandlerWithServices::class, "doSomething")
            ->withInputChannelName('some-id')
            ->withMethodParameterConverters([
                PayloadBuilder::create("command"),
                ReferenceBuilder::create("service1", stdClass::class),
                ReferenceBuilder::create("service2", stdClass::class)
            ])
            ->withEndpointId('some-id');

        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler($commandHandler);

        $this->createModuleAndAssertConfiguration(
            [
                ExampleEventHandlerWithServices::class
            ],
            $expectedConfiguration,
            [
                DoStuffCommand::class => "'some-id"
            ]
        );
    }
}