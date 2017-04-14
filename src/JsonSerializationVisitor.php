<?php

/*
 * Copyright 2016 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\Serializer;

use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\Metadata\ClassMetadata;
use JMS\Serializer\Metadata\PropertyMetadata;
use JMS\Serializer\Util\ArrayObject;

class JsonSerializationVisitor extends AbstractVisitor implements SerializationVisitorInterface
{
    private $options = 0;

    private $navigator;
    private $root;
    private $dataStack;
    private $data;

    public function initialize(GraphNavigatorInterface $navigator):void
    {
        $this->navigator = $navigator;
        $this->dataStack = new \SplStack;
    }

    public function serializeNull(TypeDefinition $type, SerializationContext $context)
    {
        return null;
    }

    public function serializeString($data, TypeDefinition $type, SerializationContext $context)
    {
        return (string)$data;
    }

    public function serializeBoolean($data, TypeDefinition $type, SerializationContext $context)
    {
        return (boolean)$data;
    }

    public function serializeInteger($data, TypeDefinition $type, SerializationContext $context)
    {
        return (int)$data;
    }

    public function serializeDouble($data, TypeDefinition $type, SerializationContext $context)
    {
        return (float)$data;
    }

    /**
     * @param array $data
     * @param TypeDefinition $type
     * @param SerializationContext $context
     * @return mixed
     */
    public function serializeArray($data, TypeDefinition $type, SerializationContext $context)
    {
        $this->dataStack->push($data);

        $isHash = $type->hasParam(1);

        $rs = $isHash ? new ArrayObject() : array();

        $isList = $type->hasParam(0) && !$type->hasParam(1);

        foreach ($data as $k => $v) {
            $v = $this->navigator->accept($v, $this->getElementType($type->getArray()), $context);

            if (null === $v && $context->shouldSerializeNull() !== true) {
                continue;
            }

            if ($isList) {
                $rs[] = $v;
            } else {
                $rs[$k] = $v;
            }
        }

        $this->dataStack->pop();

        return $rs;
    }

    public function startSerializingObject(ClassMetadata $metadata, $data, TypeDefinition $type, SerializationContext $context):void
    {
        $this->dataStack->push($this->data);
        $this->data = new ArrayObject();
    }

    public function endSerializingObject(ClassMetadata $metadata, $data, TypeDefinition $type, SerializationContext $context)
    {
        $rs = $this->data;
        $this->data = $this->dataStack->pop();

        return $rs;
    }

    public function serializeProperty(PropertyMetadata $metadata, $data, SerializationContext $context):void
    {
        $v = $this->accessor->getValue($data, $metadata);

        $v = $this->navigator->accept($v, $metadata->type, $context);
        if (null === $v && $context->shouldSerializeNull() !== true) {
            return;
        }

        $k = $this->namingStrategy->translateName($metadata);

        if ($metadata->inline && ($v instanceof ArrayObject)) {
            $this->data->merge($v);

            return;
        }

        $this->data[$k] = $v;
    }

    /**
     * Checks if some data key exists.
     *
     * @param string $key
     * @return boolean
     */
    public function hasData($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Allows you to replace existing data on the current object/root element.
     *
     * @param string $key
     * @param integer|float|boolean|string|array|null $value This value must either be a regular scalar, or an array.
     *                                                       It must not contain any objects anymore.
     */
    public function setData($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function getRoot()
    {
        return $this->root;
    }

    /**
     * @param mixed $data the passed data must be understood by whatever encoding function is applied later.
     * @return string
     */
    public function getSerializationResult($data)
    {
        $result = @json_encode($data, $this->options);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                return $result;

            case JSON_ERROR_UTF8:
                throw new RuntimeException('Your data could not be encoded because it contains invalid UTF8 characters.');

            default:
                throw new RuntimeException(sprintf('An error occurred while encoding your data (error code %d).', json_last_error()));
        }
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions($options)
    {
        $this->options = (integer)$options;
    }



    /**
     * @deprecated
     */
    public function getResult()
    {
        return $this->getSerializationResult($this->root);
    }

    /**
     * @deprecated
     */
    public function visitNull($data, array $type, Context $context)
    {
        return $this->serializeNull(TypeDefinition::fromArray($type), $context);
    }

    /**
     * @deprecated
     */
    public function visitString($data, array $type, Context $context)
    {
        return $this->serializeString($data, TypeDefinition::fromArray($type), $context);
    }

    /**
     * @deprecated
     */
    public function visitBoolean($data, array $type, Context $context)
    {
        return $this->serializeBoolean($data, TypeDefinition::fromArray($type), $context);
    }

    /**
     * @deprecated
     */
    public function visitDouble($data, array $type, Context $context)
    {
        return $this->serializeDouble($data, TypeDefinition::fromArray($type), $context);
    }

    /**
     * @deprecated
     */
    public function visitInteger($data, array $type, Context $context)
    {
        return $this->serializeInteger($data, TypeDefinition::fromArray($type), $context);
    }

    /**
     * @deprecated
     */
    public function visitArray($data, array $type, Context $context)
    {
        return $this->serializeArray($data, TypeDefinition::fromArray($type), $context);
    }

    /**
     * @deprecated
     */
    public function startVisitingObject(ClassMetadata $metadata, $data, array $type, Context $context)
    {
        $this->startSerializingObject($metadata, $data, TypeDefinition::fromArray($type), $context);
    }

    /**
     * @deprecated
     */
    public function visitProperty(PropertyMetadata $metadata, $data, Context $context)
    {
        $this->serializeProperty($metadata, $data, $context);
    }

    /**
     * @deprecated
     */
    public function endVisitingObject(ClassMetadata $metadata, $data, array $type, Context $context)
    {
        return $this->endSerializingObject($metadata, $data, TypeDefinition::fromArray($type), $context);
    }

    /**
     * @deprecated
     */
    public function setNavigator(GraphNavigatorInterface $navigator)
    {
        $this->initialize($navigator);
    }
}
