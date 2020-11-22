<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Config\Annotation\ModuleConfiguration;

use Ecotone\AnnotationFinder\AnnotationFinder;
use Ecotone\Messaging\Annotation\Asynchronous;
use Ecotone\Messaging\Annotation\EndpointAnnotation;
use Ecotone\Messaging\Annotation\ModuleAnnotation;
use Ecotone\Messaging\Config\Annotation\AnnotatedDefinitionReference;
use Ecotone\Messaging\Config\Annotation\AnnotationModule;
use Ecotone\Messaging\Config\Configuration;
use Ecotone\Messaging\Config\ConfigurationException;
use Ecotone\Messaging\Config\ModuleReferenceSearchService;
use Ecotone\Modelling\Annotation\CommandHandler;
use Ecotone\Modelling\Annotation\EventHandler;
use Ecotone\Modelling\Annotation\QueryHandler;

#[ModuleAnnotation]
class AsynchronousModule extends NoExternalConfigurationModule implements AnnotationModule
{
    private array $asyncEndpoints = [];

    /**
     * ConverterModule constructor.
     *
     * @param array $asyncEndpoints
     */
    private function __construct(array $asyncEndpoints)
    {
        $this->asyncEndpoints = $asyncEndpoints;
    }

    /**
     * @inheritDoc
     */
    public static function create(AnnotationFinder $annotationRegistrationService): static
    {
        $asynchronousClasses = $annotationRegistrationService->findAnnotatedClasses(Asynchronous::class);

        $asynchronousMethods = $annotationRegistrationService->findAnnotatedMethods(Asynchronous::class);
        $endpoints           = array_merge(
            $annotationRegistrationService->findAnnotatedMethods(EndpointAnnotation::class),
            $annotationRegistrationService->findAnnotatedMethods(EventHandler::class)
        );

        $registeredAsyncEndpoints = [];
        foreach ($asynchronousMethods as $asynchronousMethod) {
            /** @var Asynchronous $asyncAnnotation */
            $asyncAnnotation = $asynchronousMethod->getAnnotationForMethod();
            $inputChannel    = $asyncAnnotation->getChannelName();
            foreach ($endpoints as $key => $endpoint) {
                if (($endpoint->getClassName() === $asynchronousMethod->getClassName()) && ($endpoint->getMethodName() === $asynchronousMethod->getMethodName())) {
                    /** @var EndpointAnnotation $annotationForMethod */
                    $annotationForMethod = $endpoint->getAnnotationForMethod();
                    if ($annotationForMethod instanceof QueryHandler) {
                        continue;
                    }
                    if (in_array(get_class($annotationForMethod), [CommandHandler::class, EventHandler::class])) {
                        if ($annotationForMethod->isEndpointIdGenerated()) {
                            throw ConfigurationException::create("{$endpoint} should have endpointId defined for handling asynchronously");
                        }
                    }

                    $registeredAsyncEndpoints[$inputChannel][] = $annotationForMethod->getEndpointId();
                    unset($endpoints[$key]);
                }
            }
        }
        $endpoints = array_values($endpoints);

        foreach ($asynchronousClasses as $asynchronousClass) {
            /** @var Asynchronous $asyncClass */
            $asyncClass = AnnotatedDefinitionReference::getSingleAnnotationForClass($annotationRegistrationService, $asynchronousClass, Asynchronous::class);
            foreach ($endpoints as $endpoint) {
                if ($asynchronousClass === $endpoint->getClassName()) {
                    /** @var EndpointAnnotation $annotationForMethod */
                    $annotationForMethod = $endpoint->getAnnotationForMethod();
                    if ($annotationForMethod instanceof QueryHandler) {
                        continue;
                    }
                    if (in_array(get_class($annotationForMethod), [CommandHandler::class, EventHandler::class])) {
                        if ($annotationForMethod->isEndpointIdGenerated()) {
                            throw ConfigurationException::create("{$endpoint} should have endpointId defined for handling asynchronously");
                        }
                    }

                    $registeredAsyncEndpoints[$asyncClass->getChannelName()][] = $annotationForMethod->getEndpointId();
                }
            }
        }

        return new self($registeredAsyncEndpoints);
    }

    /**
     * @inheritDoc
     */
    public function canHandle($extensionObject): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function prepare(Configuration $configuration, array $extensionObjects, ModuleReferenceSearchService $moduleReferenceSearchService): void
    {
        foreach ($this->asyncEndpoints as $channelName => $asyncEndpointsForChannel) {
            foreach ($asyncEndpointsForChannel as $asyncEndpointForChannel) {
                $configuration->registerAsynchronousEndpoint($channelName, $asyncEndpointForChannel);
            }
        }
    }
}