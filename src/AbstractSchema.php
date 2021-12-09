<?php

namespace MediaWiki\Extension\JsonSchemaClasses;

use MediaWiki\MediaWikiServices;

abstract class AbstractSchema {
    protected $classDefinitions = [];
    protected $classRegistry;

    protected $schema;

    public function __construct() {
        $this->loadSchema();
    }

    public function getBaseClass(): string {
        return AbstractJsonSchemaClass::class;
    }

    public function getClassRegistry() {
        return $this->classRegistry;
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

    protected function loadSchema() {
        $this->schema = JsonHelper::decodeJsonFile( $this->getSchemaFile() );
    }

    abstract public function registerClasses( &$classRegistry );
}