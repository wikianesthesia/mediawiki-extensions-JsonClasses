<?php

namespace MediaWiki\Extension\JsonSchemaClasses;

class JsonSchemaClassManager {
    /**
     * Definitions describe the implementation of a class (typically a subclass of a class described in $schema).
     * @var array
     */
    protected static $definition = [];

    /**
     * Schema describe a prototype of a class (typically an abstract class).
     * @var array
     */
    protected static $schema = [];

    protected static $text = [];

    public function getClass( string $class, bool $includeNamespace = true ): string {
        return $includeNamespace ?
            $class :
            substr( strrchr( $class, '\\' ), 1 );
    }

    public function getDefinition( string $class ): ?array {
        return static::$definition[ $class ] ?? null;
    }

    public function getSchema( string $class ): ?array {
        return static::$schema[ $class ] ?? null;
    }

    /**
     * @param string $class
     * @param string $var
     * @return string|null
     */
    public function getText( string $class, string $textProperty ): ?string {
        return static::$text[ $class ][ $textProperty ] ?? null;
    }

    public function isDefinitionRegistered( string $class ): bool {
        return isset( static::$definition[ $class ] );
    }

    public function isSchemaRegistered( string $class ): bool {
        return isset( static::$schema[ $class ] );
    }

    public function registerDefinition( string $class, array $definition ): bool {
        static::$definition[ $class ] = $definition;
        static::$text[ $class ] = [];

        return true;
    }

    public function registerSchema( string $class, string $file ): bool {
        if( isset( static::$schema[ $class ] ) ) {
            // TODO throw/log error?
            return false;
        }

        $json = file_get_contents( $file );

        if( $json === false ) {
            // TODO log error schema file not found or not accessible

            return false;
        }

        $schema = json_decode( $json, true );

        if( !is_array( $schema ) ) {
            // TODO log error json file not valid

            return false;
        }

        static::$schema[ $class ] = $schema;

        return true;
    }

    public function setText( string $class, string $textProperty, string $value ) {
        static::$text[ $class ][ $textProperty ] = $value;
    }
}