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
        return static::$classInstances[ strtolower( $class ) ] ?? null;
    }

    public function getClassInstanceForSchema( string $schemaClass, string $classId ): ?AbstractJsonClass {
        $schemaClass = strtolower( $schemaClass );
        $classId = strtolower( $classId );

        if( !isset( static::$schemaClassIds[ $schemaClass ] )
            || !isset( static::$schemaClassIds[ $schemaClass ][ $classId ] ) ) {
            return null;
        }

        return $this->getClassInstance( static::$schemaClassIds[ $schemaClass ][ $classId ] );
    }


    public function getClassInstancesForSchema( string $schemaClass ): array {
        $schemaClass = strtolower( $schemaClass );

        $classInstances = [];

        if( isset( static::$schemaClassIds[ $schemaClass ] ) ) {
            $classInstances = array_map( function( string $classId ) {
                return static::getClassInstance( $classId );
            }, static::$schemaClassIds[ $schemaClass ] );
        }

        return $classInstances;
    }

    public function getSchema( string $schemaClass ): ?AbstractSchema {
        return static::$schema[ strtolower( $schemaClass ) ] ?? null;
    }

    public function isClassRegistered( string $class ): bool {
        return isset( static::$classInstances[ strtolower( $class ) ] );
    }

    public function isSchemaRegistered( string $baseClass ): bool {
        return isset( static::$schema[ strtolower( $baseClass ) ] );
    }

    /**
     * @param class-string<AbstractSchema> $schemaClass
     * @return bool
     */
    public function registerSchema( string $schemaClass ): bool {
        $schemaClassKey = strtolower( $schemaClass );

        if( isset( static::$schema[ $schemaClassKey ] ) ) {
            // TODO throw/log error?
            return false;
        }

        if( !class_exists( $schemaClass ) ) {
            // TODO error handling
            return false;
        }

        static::$schema[ $schemaClassKey ] = new $schemaClass();
        static::$schemaClassIds[ $schemaClassKey ] = [];

        return true;
    }

    public function loadClass( string $schemaClass, string $classDefinitionFile ): bool {
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

        static::$classInstances[ strtolower( $classDefinition[ 'class' ] ) ] = $classInstance;

        static::$schemaClassIds[ strtolower( $schemaClass ) ][ strtolower( $classInstance->getId() ) ] = $classDefinition[ 'class' ];

        return true;
    }

    public function loadClassDirectory( string $schemaClass, string $classDirectory, bool $includeSubdirectories = false, bool $recursive = false ): bool {
        $schemaClassKey = strtolower( $schemaClass );

        if( !isset( static::$schema[ $schemaClassKey ] ) ) {
            return false;
        }

        $classDefinitionFileName = static::$schema[ $schemaClassKey ]->getClassDefinitionFileName();
        $classDefinitionFile = $classDirectory . '/' . $classDefinitionFileName;

        if( file_exists( $classDefinitionFile ) ) {
            $this->loadClass( $schemaClass, $classDefinitionFile );
        }

        if( $includeSubdirectories && $classDirectory ) {
            foreach( scandir( $classDirectory ) as $file ) {
                if( $file !== '.' && $file !== '..' && is_dir( $classDirectory . '/' . $file ) ) {
                    $this->loadClassDirectory( $schemaClass,$classDirectory . '/' . $file, $recursive, $recursive );
                }
            }
        }

        return true;
    }
}