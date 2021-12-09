<?php

namespace MediaWiki\Extension\JsonSchemaClasses;

use MediaWiki\MediaWikiServices;
use ReflectionClass;

abstract class AbstractJsonSchemaClass {
    protected $localDirectory = '';
    protected $remoteDirectory = '';

    protected $config = [];
    protected $definition = [];
    protected $text = [];

    public function __construct( array $definition ) {
        $this->processDefinition( $definition );
        $this->setHooks();
    }

    public function getConfig( string $var = '' ) {
        return $this->config[ $var ] ?? null;
    }

    public function getDescription(): string {
        return $this->getText(
            'description',
            'descriptionmsg',
            [ $this->getMsgKeyPrefix() . '-desc' ]
        );
    }

    /**
     * @return string
     */
    public function getLocalDirectory(): string {
        if( !$this->localDirectory ) {
            $reflectionClass = new ReflectionClass( static::class );
            $classFilename = $reflectionClass->getFileName();

            $this->localDirectory = str_replace( '/' . $reflectionClass->getShortName() . '.php', '', $classFilename );
        }

        return $this->localDirectory;
    }

    /**
     * @return string
     */
    public function getMsgKeyPrefix(): string {
        return strtolower(
            $this->getClassString( $this->getSchemaClass(), false ) .
            '-' .
            $this->getClassString( static::class, false )
        );
    }

    public function getName(): string {
        return $this->getText(
            'name',
            'namemsg',
            [ $this->getMsgKeyPrefix() . '-name' ],
            $this->getClassString( static::class, false )
        );
    }

    /**
     * @return string
     */
    public function getRemoteDirectory(): string {
        if( !$this->remoteDirectory ) {
            $this->remoteDirectory = str_replace( $_SERVER[ 'DOCUMENT_ROOT' ], '', $this->getLocalDirectory() );
        }

        return $this->remoteDirectory;
    }

    public function getText( string $textProperty = '', string $messageProperty = '', array $extraMessageKeys = [], string $defaultValue = '' ): string {
        if( !isset( $this->text[ $textProperty ] ) ) {
            $text = '';

            $definition = $this->getDefinition();

            $messageKeys = [];

            if( $messageProperty ) {
                $messageKeys[] = $messageProperty;
            }

            $messageKeys = array_merge( $messageKeys, $extraMessageKeys );

            foreach( $messageKeys as $messageKey ) {
                $message = wfMessage( $messageKey );

                if( $message->exists() ) {
                    $text = $message->text();

                    break;
                }
            }

            if( !$text ) {
                if( $definition[ $textProperty ] ) {
                    $text = $definition[ $textProperty ];
                } else {
                    $text = $defaultValue;
                }
            }

            $this->text[ $textProperty ] = $text;
        }

        return $this->text[ $textProperty ];
    }

    public function setConfig( string $var, $value ) {
        $this->config[ $var ] = $value;
    }

    protected function getClassString( string $class, bool $includeNamespace = true ): string {
        return $includeNamespace ?
            $class :
            substr( strrchr( $class, '\\' ), 1 );
    }

    protected function getDefinition( string $property = '' ) {
        if( $property ) {
            return $this->definition[ $property ] ?? null;
        }

        return $this->definition;
    }

    /**
     * @return JsonSchemaClassManager
     */
    protected function getJsonSchemaClassManager(): JsonSchemaClassManager {
        return MediaWikiServices::getInstance()->get( 'JsonSchemaClassManager' );
    }

    /**
     * @param bool $includeNamespace
     * @return string
     */
    abstract protected function getSchemaClass(): string;

    protected function getSchema(): ?AbstractSchema {
        return $this->getJsonSchemaClassManager()->getSchema( $this->getSchemaClass() );
    }

    protected function postprocessDefinition( array &$definition ) {}

    protected function preprocessDefinition( array &$definition ) {}

    protected function processDefinition( array $definition ) {
        global $wgAutoloadClasses, $wgMessagesDirs;

        $this->preprocessDefinition( $definition );

        JsonHelper::processDefinitionProperties( $this->getSchema()->getSchemaDefinition(), $definition );

        // Configuration directives
        if( isset( $definition[ 'config' ] ) ) {
            foreach( $definition[ 'config' ] as $var => $value ) {
                $this->setConfig( $var, $value );
            }
        }

        // Autoload classes
        if( isset( $definition[ 'AutoloadClasses' ] ) ) {
            foreach( $definition[ 'AutoloadClasses' ] as $className => $classFile ) {
                $wgAutoloadClasses[ $className ] = $this->getLocalDirectory() . '/' . $classFile;
            }
        }

        // Message directories
        if( isset( $definition[ 'MessagesDirs' ] ) ) {
            foreach( $definition[ 'MessagesDirs' ] as $messagesDir ) {
                $wgMessagesDirs[ 'JsonSchemaClasses' ][] = $this->getLocalDirectory() . '/' . $messagesDir;
            }
        }

        $this->postprocessDefinition( $definition );

        $this->definition = $definition;
    }

    protected function setHooks() {
        global $wgHooks;

        foreach( $this->getDefinition( 'Hooks' ) as $hook => $callback ) {
            $wgHooks[ $hook ][] = $callback;
        }
    }
}