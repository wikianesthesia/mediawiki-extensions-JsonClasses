<?php

namespace MediaWiki\Extension\JsonClasses;

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
        return AbstractJsonClass::class;
    }

    public function getClassConfig( string $classId ) {
        return $GLOBALS[ $this->getClassConfigVariable() ][ $classId ] ?? [];
    }

    public function getSchemaDefinition() {
        return $this->schema;
    }

    public function getClassDefinitionFileName(): string {
        return 'class.json';
    }

    public function getClassRegistryClass(): string {
        return ClassRegistry::class;
    }

    public function getSchemaFile(): string {
        return realpath( __DIR__ . '../resources/schema/class.schema.json' );
    }

    /**
     * @return JsonClassManager
     */
    protected function getJsonClassManager(): JsonClassManager {
        return MediaWikiServices::getInstance()->get( 'JsonClassManager' );
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