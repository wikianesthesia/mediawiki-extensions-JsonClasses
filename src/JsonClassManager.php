<?php

namespace MediaWiki\Extension\JsonClasses;

use MediaWiki\MediaWikiServices;

class JsonClassManager {

    /**
     * @var AbstractJsonClass[]
     */
    protected static $classInstances = [];

    /**
     * Schema describe a prototype of a class (typically an abstract class).
     * @var AbstractSchema[]
     */
    protected static $schema = [];

    /**
     * @var string[][]
     */
    protected static $schemaClassIds = [];

    public function getClassInstance( string $class ): ?AbstractJsonClass {
        return static::$classInstances[ $class ] ?? null;
    }

    public function getClassInstanceForSchema( string $schemaClass, string $classId ): ?AbstractJsonClass {
        if( !isset( static::$schemaClassIds[ $schemaClass ] )
            || !isset( static::$schemaClassIds[ $schemaClass ][ $classId ] ) ) {
            return null;
        }

        return $this->getClassInstance( static::$schemaClassIds[ $schemaClass ][ $classId ] );
    }


    public function getClassInstancesForSchema( string $schemaClass ): array {
        $classInstances = [];

        if( isset( static::$schemaClassIds[ $schemaClass ] ) ) {
            $classInstances = array_map( function( string $classId ) {
                return static::getClassInstance( $classId );
            }, static::$schemaClassIds[ $schemaClass ] );
        }

        return $classInstances;
    }

    public function getSchema( string $schemaClass ): ?AbstractSchema {
        return static::$schema[ $schemaClass ] ?? null;
    }

    public function isClassRegistered( string $class ): bool {
        return isset( static::$classInstances[ $class ] );
    }

    public function isSchemaRegistered( string $baseClass ): bool {
        return isset( static::$schema[ $baseClass ] );
    }

    /**
     * @param class-string<AbstractSchema> $schemaClass
     * @return bool
     */
    public function registerSchema( string $schemaClass ): bool {
        if( isset( static::$schema[ $schemaClass ] ) ) {
            // TODO throw/log error?
            return false;
        }

        $schema = new $schemaClass();

        /**
         * @var ClassRegistry
         */
        $classRegistryClass = $schema->getClassRegistryClass();

        if( !class_exists( $classRegistryClass ) ) {
            // TODO error handling
            return false;
        }

        static::$schema[ $schemaClass ] = $schema;
        static::$schemaClassIds[ $schemaClass ] = [];

        $classRegistry = new $classRegistryClass( $schema->getClassDefinitionFileName() );

        static::$schema[ $schemaClass ]->registerClasses( $classRegistry );

        foreach( $classRegistry->getRegisteredClassDefinitionFiles() as $classDefinitionFile ) {
            $this->loadClass( $schemaClass, $classDefinitionFile );
        }

        return true;
    }

    protected function loadClass( string $schemaClass, string $classDefinitionFile ): bool {
        $classDefinition = JsonHelper::decodeJsonFile( $classDefinitionFile );

        if( !isset( $classDefinition[ 'class' ] ) ) {
            // TODO error handling

            return false;
        }

        $shortClass = substr( strrchr( $classDefinition[ 'class' ], '\\' ), 1 );

        if( !isset( $classDefinition[ 'id' ] ) ) {
            $classDefinition[ 'id' ] = $shortClass;
        }

        // Import the class's php file
        if( !isset( $classDefinition[ 'classfile' ] ) ) {
            $classDefinition[ 'classfile' ] = $shortClass . '.php';
        }

        // Convert classfile from relative to absolute path
        $classFile = dirname( $classDefinitionFile ) . '/' . $classDefinition[ 'classfile' ];

        require_once( $classFile );

        /**
         * @var AbstractJsonClass
         */
        $classInstance = new $classDefinition[ 'class' ]( $classDefinition );

        static::$classInstances[ $classDefinition[ 'class' ] ] = $classInstance;

        static::$schemaClassIds[ $schemaClass ][ $classInstance->getId() ] = $classDefinition[ 'class' ];


        return true;
    }
}