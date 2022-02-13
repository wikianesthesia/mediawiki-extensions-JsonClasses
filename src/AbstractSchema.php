<?php

namespace MediaWiki\Extension\JsonSchemaClasses;

use MediaWiki\MediaWikiServices;

abstract class AbstractSchema {
    protected $schema;

    abstract public function getExtensionName(): string;
    abstract public function getSchemaName(): string;
    abstract public function registerClasses( &$classRegistry );

    public function __construct() {
        $this->loadSchemaDefinition();
        $this->initializeClassConfig();
    }

    public function getBaseClass(): string {
        return AbstractJsonSchemaClass::class;
    }

    public function getClassConfig( string $classId ) {
        return $GLOBALS[ $this->getClassConfigVariable() ][ $classId ] ?? [];
    }

    public function getSchemaDefinition() {
        return $this->schema;
    }

    public function getClassDefinitionFileName(): string {
        return 'definition.json';
    }

    public function getClassRegistryClass(): string {
        return ClassRegistry::class;
    }

    public function getSchemaFile(): string {
        return realpath( __DIR__ . '../resources/schema/definition.schema.json' );
    }

    /**
     * @return JsonSchemaClassManager
     */
    protected function getJsonSchemaClassManager(): JsonSchemaClassManager {
        return MediaWikiServices::getInstance()->get( 'JsonSchemaClassManager' );
    }

    protected function getClassConfigVariable(): string {
        return 'wg' . $this->getExtensionName() . $this->getSchemaName() . 'Config';
    }

    protected function initializeClassConfig() {
        if( !isset( $GLOBALS[ $this->getClassConfigVariable() ] ) ) {
            $GLOBALS[ $this->getClassConfigVariable() ] = [];
        }
    }

    protected function loadSchemaDefinition() {
        $this->schema = JsonHelper::decodeJsonFile( $this->getSchemaFile() );
    }
}