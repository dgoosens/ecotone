<?php

namespace Messaging;

/**
 * Interface Message
 * @package Messaging
 * @author Dariusz Gafka <dgafka.mail@gmail.com>
 */
interface Message
{
    /**
     * @return MessageHeaders
     */
    public function getHeaders() : MessageHeaders;

    /**
     * @return object|string|array
     */
    public function getPayload();
}