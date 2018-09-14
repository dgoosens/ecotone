<?php
declare(strict_types=1);

namespace Test\SimplyCodedSoftware\IntegrationMessaging\Config\Annotation\ModuleConfiguration;

use Fixture\Annotation\ApplicationContext\ApplicationContextExample;
use Fixture\Annotation\ApplicationContext\ApplicationContextModuleExtensionExample;
use Fixture\Annotation\ApplicationContext\GatewayExample;
use SimplyCodedSoftware\IntegrationMessaging\Annotation\ApplicationContext;
use SimplyCodedSoftware\IntegrationMessaging\Annotation\MessagingComponent;
use SimplyCodedSoftware\IntegrationMessaging\Channel\SimpleChannelInterceptorBuilder;
use SimplyCodedSoftware\IntegrationMessaging\Channel\SimpleMessageChannelBuilder;
use SimplyCodedSoftware\IntegrationMessaging\Config\Annotation\AnnotationModule;
use SimplyCodedSoftware\IntegrationMessaging\Config\Annotation\InMemoryAnnotationRegistrationService;
use SimplyCodedSoftware\IntegrationMessaging\Config\Configuration;
use SimplyCodedSoftware\IntegrationMessaging\Config\ConfigurationException;
use SimplyCodedSoftware\IntegrationMessaging\Config\InMemoryConfigurationVariableRetrievingService;
use SimplyCodedSoftware\IntegrationMessaging\Config\Annotation\ModuleConfiguration\ApplicationContextModule;
use SimplyCodedSoftware\IntegrationMessaging\Config\NullObserver;
use SimplyCodedSoftware\IntegrationMessaging\Handler\Gateway\GatewayProxyBuilder;
use SimplyCodedSoftware\IntegrationMessaging\Handler\InMemoryReferenceSearchService;
use SimplyCodedSoftware\IntegrationMessaging\Handler\Transformer\TransformerBuilder;

/**
 * Class AnnotationApplicationContextConfigurationTest
 * @package Test\SimplyCodedSoftware\IntegrationMessaging\Config\Annotation\Annotation
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class ApplicationContextModuleTest extends AnnotationConfigurationTest
{
    /**
     * @throws ConfigurationException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    public function test_configuring_message_channel_from_application_context()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageChannel(SimpleMessageChannelBuilder::createDirectMessageChannel(ApplicationContextExample::HTTP_INPUT_CHANNEL));

        $this->compareWithConfiguredForMethod("httpEntryChannel", $expectedConfiguration);
    }

    /**
     * @throws ConfigurationException
     */
    public function test_configuring_message_handler_from_application_context()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageHandler(TransformerBuilder::createHeaderEnricher([
                "token" => "abcedfg"
            ])
                ->withInputChannelName(ApplicationContextExample::HTTP_INPUT_CHANNEL)
                ->withOutputMessageChannel(ApplicationContextExample::HTTP_OUTPUT_CHANNEL));

        $this->compareWithConfiguredForMethod("enricherHttpEntry", $expectedConfiguration);
    }

    /**
     * @throws ConfigurationException
     */
    public function test_configuring_gateway_from_application_context()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerGatewayBuilder(GatewayProxyBuilder::create("some-ref", GatewayExample::class, "doSomething", ApplicationContextExample::HTTP_INPUT_CHANNEL));

        $this->compareWithConfiguredForMethod("gateway", $expectedConfiguration);
    }

    /**
     * @throws ConfigurationException
     */
    public function test_configuring_multiple_components_from_application_context()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageChannel(SimpleMessageChannelBuilder::createDirectMessageChannel(ApplicationContextExample::HTTP_INPUT_CHANNEL))
            ->registerMessageHandler(TransformerBuilder::createHeaderEnricher([
                "token" => "abcedfg"
            ])
                ->withInputChannelName(ApplicationContextExample::HTTP_INPUT_CHANNEL)
                ->withOutputMessageChannel(ApplicationContextExample::HTTP_OUTPUT_CHANNEL));

        $this->compareWithConfiguredForMethod("withMultipleMessageComponents", $expectedConfiguration);
    }

    public function test_configuring_with_channel_interceptors()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageChannel(SimpleMessageChannelBuilder::createDirectMessageChannel(ApplicationContextExample::HTTP_INPUT_CHANNEL))
            ->registerChannelInterceptor(SimpleChannelInterceptorBuilder::create(ApplicationContextExample::HTTP_INPUT_CHANNEL, "ref"));

        $this->compareWithConfiguredForMethod("withChannelInterceptors", $expectedConfiguration);
    }

    /**
     * @throws ConfigurationException
     */
    public function test_throwing_exception_if_trying_to_register_not_known_messaging_component()
    {
        $this->checkForWrongConfiguration("wrongMessagingComponent");
    }

    public function test_registering_from_extensions()
    {
        $expectedConfiguration = $this->createMessagingSystemConfiguration()
            ->registerMessageChannel(SimpleMessageChannelBuilder::createDirectMessageChannel("extension"));

        $annotationConfiguration = $this->createAnnotationConfiguration("withStdClassConverterByExtension");

        $configuration = $this->createMessagingSystemConfiguration();
        $annotationConfiguration->prepare($configuration, [ApplicationContextModuleExtensionExample::create(InMemoryAnnotationRegistrationService::createEmpty())], NullObserver::create());

        $this->assertEquals(
            $expectedConfiguration,
            $configuration
        );
    }

    /**
     * @param string $methodName
     * @param Configuration $expectedConfiguration
     * @throws ConfigurationException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    private function compareWithConfiguredForMethod(string $methodName, Configuration $expectedConfiguration): void
    {
        $annotationConfiguration = $this->createAnnotationConfiguration($methodName);

        $configuration = $this->createMessagingSystemConfiguration();

        $annotationConfiguration->prepare($configuration, [], NullObserver::create());

        $this->assertEquals(
            $expectedConfiguration,
            $configuration
        );
    }

    /**
     * @param string $methodName
     * @throws ConfigurationException
     * @throws \SimplyCodedSoftware\IntegrationMessaging\MessagingException
     */
    private function checkForWrongConfiguration(string $methodName) : void
    {
        $this->expectException(ConfigurationException::class);

        $annotationConfiguration = $this->createAnnotationConfiguration($methodName);
        $configuration = $this->createMessagingSystemConfiguration();
        $annotationConfiguration->prepare($configuration, [], NullObserver::create());
    }

    /**
     * @param $methodName
     * @return \SimplyCodedSoftware\IntegrationMessaging\Config\Annotation\AnnotationModule
     */
    private function createAnnotationConfiguration($methodName): \SimplyCodedSoftware\IntegrationMessaging\Config\Annotation\AnnotationModule
    {
        $annotationRegistrationService = InMemoryAnnotationRegistrationService::createEmpty()
            ->addAnnotationToClass(
                ApplicationContextExample::class,
                new ApplicationContext()
            )
            ->addAnnotationToClassMethod(
                ApplicationContextExample::class,
                $methodName,
                new MessagingComponent()
            );

        $annotationConfiguration = ApplicationContextModule::create($annotationRegistrationService);

        return $annotationConfiguration;
    }
}