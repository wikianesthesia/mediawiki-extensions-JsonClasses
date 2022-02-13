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



    /**
     * @param array $definition
     */
    public function __construct( array $definition ) {
        $this->processDefinition( $definition );
        $this->setHooks();
    }



    /**
     * @param string $var
     * @return mixed|null
     */
    public function getConfig( string $var = '' ) {
        return $this->config[ $var ] ?? null;
    }



    /**
     * @return string
     */
    public function getDescription(): string {
        return $this->getText(
            'description',
            'descriptionmsg',
            [ $this->getMsgKeyPrefix() . '-desc' ]
        );
    }



    /**
     * @return mixed
     */
    public function getId() {
        return $this->getDefinition( 'id' );
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



    /**
     * @return string
     */
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



    /**
     * @param string $textProperty
     * @param string $messageProperty
     * @param array $extraMessageKeys
     * @param string $defaultValue
     * @return string
     */
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



    /**
     * @param string $var
     * @param $value
     */
    public function setConfig( string $var, $value ) {
        $this->config[ $var ] = $value;
    }



    /**
     * @param string $class
     * @param bool $includeNamespace
     * @return string
     */
    protected function getClassString( string $class, bool $includeNamespace = true ): string {
        return $includeNamespace ?
            $class :
            substr( strrchr( $class, '\\' ), 1 );
    }



    /**
     * @param string $property
     * @return array|mixed|null
     */
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
     * @return string
     */
    abstract protected function getSchemaClass(): string;



    /**
     * @return AbstractSchema|null
     */
    protected function getSchema(): ?AbstractSchema {
        return $this->getJsonSchemaClassManager()->getSchema( $this->getSchemaClass() );
    }



    /**
     * @param array $definition
     */
    protected function postprocessDefinition( array &$definition ) {}



    /**
     * @param array $definition
     */
    protected function preprocessDefinition( array &$definition ) {}



    /**
     * @param array $definition
     */
    protected function processDefinition( array $definition ) {
        global $wgAutoloadClasses, $wgAvailableRights, $wgGroupPermissions, $wgMessagesDirs, $wgResourceModules;

        $this->preprocessDefinition( $definition );

        $schema = $this->getSchema();

        JsonHelper::processDefinitionProperties( $schema->getSchemaDefinition(), $definition );

        // Configuration directives
        if( isset( $definition[ 'config' ] ) ) {
            $globalConfig = $schema->getClassConfig( $definition[ 'id' ] );

            foreach( $definition[ 'config' ] as $var => $value ) {
                $value = $globalConfig[ $var ] ?? $value;

                $this->setConfig( $var, $value );
            }
        }

        // AutoloadClasses
        if( isset( $definition[ 'AutoloadClasses' ] ) ) {
            foreach( $definition[ 'AutoloadClasses' ] as $className => $classFile ) {
                $wgAutoloadClasses[ $className ] = $this->getLocalDirectory() . '/' . $classFile;
            }
        }

        // AvailableRights
        if( isset( $definition[ 'AvailableRights' ] ) ) {
            foreach( $definition[ 'AvailableRights' ] as $availableRight ) {
                $wgAvailableRights[] = $availableRight;
            }
        }

        // GroupPermissions
        if( isset( $definition[ 'GroupPermissions' ] ) ) {
            foreach( $definition[ 'GroupPermissions' ] as $userGroup => $rights ) {
                foreach( $rights as $right => $value ) {
                    $wgGroupPermissions[ $userGroup ][ $right ] = $value;
                }
            }
        }

        // Message directories
        if( isset( $definition[ 'MessagesDirs' ] ) ) {
            foreach( $definition[ 'MessagesDirs' ] as $messagesDir ) {
                $wgMessagesDirs[ 'JsonSchemaClasses' ][] = $this->getLocalDirectory() . '/' . $messagesDir;
            }
        }

        if( isset( $definition[ 'ResourceModules' ] ) ) {
            foreach( $definition[ 'ResourceModules'] as $name => $info ) {
                if( !isset( $info[ 'localBasePath' ] ) && isset( $definition[ 'ResourceFileModulePaths' ][ 'localBasePath' ] ) ) {
                    $info[ 'localBasePath' ] = $this->getLocalDirectory() . '/' . $definition[ 'ResourceFileModulePaths' ][ 'localBasePath' ];
                }

                if( !isset( $info[ 'remoteExtPath' ] ) && isset( $definition[ 'ResourceFileModulePaths' ][ 'remoteExtPath' ] ) ) {
                    $info[ 'remoteExtPath' ] = $definition[ 'ResourceFileModulePaths' ][ 'remoteExtPath' ];
                }

                if( !isset( $info[ 'remoteSkinPath' ] ) && isset( $definition[ 'ResourceFileModulePaths' ][ 'remoteSkinPath' ] ) ) {
                    $info[ 'remoteSkinPath' ] = $definition[ 'ResourceFileModulePaths' ][ 'remoteSkinPath' ];
                }

                $wgResourceModules[ $name ] = $info;
            }
        }

        $this->postprocessDefinition( $definition );

        $this->definition = $definition;
    }



    /**
     *
     */
    protected function setHooks() {
        global $wgHooks;

        foreach( $this->getDefinition( 'Hooks' ) as $hook => $callback ) {
            $wgHooks[ $hook ][] = $callback;
        }
    }
}