<?php
declare(strict_types=1);

namespace Ecotone\Messaging\Annotation;

use Ramsey\Uuid\Uuid;

/**
 * Class IdentifiedAnnotation
 * @package Ecotone\Messaging\Annotation
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
class IdentifiedAnnotation
{
    /**
     * @var string
     */
    public $endpointId;
    /**
     * @var bool
     */
    private $isGenerated = false;

    /**
     * @param array $values
     * @throws \Exception
     */
    public function __construct(array $values = [])
    {
        foreach ($values as $propertyName => $value) {
            $this->{$propertyName} = $value;
        }

        if (!$this->endpointId) {
            $this->endpointId = Uuid::uuid4()->toString();
            $this->isGenerated = true;
        }
    }

    /**
     * @return bool
     */
    public function isEndpointIdGenerated() : bool
    {
        return $this->isGenerated;
    }
}