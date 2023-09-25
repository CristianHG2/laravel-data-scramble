<?php

namespace JanusPhoenix\Utility\ScrambleDocs;

use Exception;
use Dedoc\Scramble\Extensions\TypeToSchemaExtension;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use JanusPhoenix\Utility\ScrambleDocs\Traits\CastsDtoToSchema;
use Spatie\LaravelData\Data;

class LaravelDataToSchema extends TypeToSchemaExtension
{
    use CastsDtoToSchema;

    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(Data::class);
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function toSchema(Type $type)
    {
        if ($type instanceof ObjectType) {
            return $this->schemaFromDto($type->name);
        }

        return parent::toSchema($type);
    }
}
