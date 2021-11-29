<?php

namespace MediaWiki\Extension\JsonSchemaClasses;

use MediaWiki\MediaWikiServices;
use ReflectionClass;

abstract class AbstractJsonSchemaClass {
    protected $localDirectory = '';
    protected $remoteDirectory = '';

    protected $config = [];

    public function __construct() {
        $this->registerSchema();
        $this->loadDefinition();
        $this->setHooks();
    }

    public function getConfig( string $var ) {
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
        return strtolower( $this->getSchemaClass( false ) . '-' . $this->getClass( false ) );
    }

    public function getName(): string {
        return $this->getText(
            'name',
            'namemsg',
            [ $this->getMsgKeyPrefix() . '-name' ],
            $this->getClass( false )
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
        $text = $this->getJsonSchemaClassManager()->getText( $this->getClass(), $textProperty );

        if( is_null( $text ) ) {
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

            $this->getJsonSchemaClassManager()->setText( $this->getClass(), $textProperty, $text );
        }

        return $text;
    }

    public function setConfig( string $var, $value ) {
        $this->config[ $var ] = $value;
    }

    protected function getClass( bool $includeNamespace = true ): string {
        return $this->getJsonSchemaClassManager()->getClass( static::class, $includeNamespace );
    }

    protected function getDefinition( string $property = '' ) {
        $definition = $this->getJsonSchemaClassManager()->getDefinition( $this->getClass() );

        if( $property ) {
            return $definition[ $property ] ?? null;
        }

        return $definition;
    }

    /**
     * @return JsonSchemaClassManager
     */
    protected function getJsonSchemaClassManager() {
        return MediaWikiServices::getInstance()->get( 'JsonSchemaClassManager' );
    }

    /**
     * @return string
     */
    abstract protected function getDefinitionFile(): string;

    /**
     * @param bool $includeNamespace
     * @return string
     */
    abstract protected function getSchemaClass( bool $includeNamespace = true ): string;

    /**
     * @return string
     */
    abstract protected function getSchemaFile(): string;

    protected function getSchema() {
        return $this->getJsonSchemaClassManager()->getSchema( $this->getSchemaClass() );
    }

    protected function loadDefinition(): bool {
        global $wgAutoloadClasses, $wgMessagesDirs;

        if( $this->getJsonSchemaClassManager()->isDefinitionRegistered( $this->getClass() ) ) {
            return true;
        }

        $definitionJson = file_get_contents( $this->getDefinitionFile() );

        if( $definitionJson === false ) {
            // TODO log error definition file not found or not accessible

            return false;
        }

        $definition = json_decode( $definitionJson, true );

        if( !is_array( $definition ) ) {
            // TODO log error json file not valid

            return false;
        }

        $this->preprocessDefinition( $definition );
        $this->processDefinitionProperties( $this->getSchema(), $definition );

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

        return $this->getJsonSchemaClassManager()->registerDefinition( $this->getClass(), $definition );
    }

    protected function postprocessDefinition( array &$definition ) {}

    protected function preprocessDefinition( array &$definition ) {}

    protected function processDefinitionProperties( array $schema, array &$definition ) {
        // Validate and import definition data into static class property
        foreach( $schema[ 'properties' ] as $propertyName => $propertyDefinition ) {
            // Make sure required property defined
            if( isset( $schema[ 'properties' ][ $propertyName ][ 'required' ] ) ) {
                if( $schema[ 'properties' ][ $propertyName ][ 'required' ] &&
                    !isset( $definition[ $propertyName ]) ) {
                    // TODO throw exception required property missing
                    //throw new MWException( 'Required property missing' );

                    return false;
                }
            }

            $propertyValue = null;

            if( isset( $definition[ $propertyName ] ) ) {
                $propertyValue = $definition[ $propertyName ];
            } else {
                if( isset( $schema[ 'properties' ][ $propertyName ][ 'default' ] ) ) {
                    $propertyValue = $schema[ 'properties' ][ $propertyName ][ 'default' ];
                } else {
                    // If the type is unambiguous (i.e. a string and not an array of possible types)
                    // cast null to the appropriate type
                    if( isset( $schema[ 'properties' ][ $propertyName ][ 'type' ] ) ) {
                        // If the type is an array of valid types, use the first type in the array
                        $nullType = gettype( $schema[ 'properties' ][ $propertyName ][ 'type' ] ) === 'array' ?
                            reset( $schema[ 'properties' ][ $propertyName ][ 'type' ] ) :
                            $schema[ 'properties' ][ $propertyName ][ 'type' ];

                        // Since objects are actually imported as arrays, if the type is object, change to array
                        if( $nullType === 'object' ) {
                            $nullType = 'array';
                        }

                        settype($propertyValue, $nullType );
                    }
                }
            }

            if( isset( $schema[ 'properties' ][ $propertyName ][ 'type' ] ) ) {
                $propertyTypes = $schema[ 'properties' ][ $propertyName ][ 'type' ];

                if( !is_array( $propertyTypes ) ) {
                    $propertyTypes = [ $propertyTypes ];
                }

                // Objects will be imported as arrays, so this is a hack to fix that type casting
                if( in_array( 'object', $propertyTypes ) ) {
                    $propertyTypes[] = 'array';
                }

                if( !in_array( gettype( $propertyValue ), $propertyTypes ) ) {
                    // TODO throw exception type mismatch
                    /*
                    echo( $propertyName);
                    var_dump( $propertyValue );
                    echo( gettype($propertyValue));
                    var_dump($propertyTypes);
                    */
                    //throw new \MWException( 'Type mismatch' );

                    return false;
                }
            }

            $definition[ $propertyName ] = $propertyValue;

            if( isset( $schema[ 'properties' ][ $propertyName ][ 'properties' ] ) ) {
                $this->processDefinitionProperties( $schema[ 'properties' ][ $propertyName ], $definition[ $propertyName ] );
            }

            if( isset( $schema[ 'properties' ][ $propertyName ][ 'items' ] ) &&
                isset( $schema[ 'properties' ][ $propertyName ][ 'items' ][ 'properties' ] ) ) {
                foreach( $definition[ $propertyName ] as $itemIndex => $item ) {
                    $this->processDefinitionProperties( $schema[ 'properties' ][ $propertyName ][ 'items' ], $definition[ $propertyName ][ $itemIndex ] );
                }
            }
        }
    }

    protected function registerSchema(): bool {
        if( $this->getJsonSchemaClassManager()->isSchemaRegistered( $this->getSchemaClass() ) ) {
            return true;
        }

        return $this->getJsonSchemaClassManager()->registerSchema( $this->getSchemaClass(), $this->getSchemaFile() );
    }

    protected function setHooks() {
        global $wgHooks;

        foreach( $this->getDefinition( 'Hooks' ) as $hook => $callback ) {
            $wgHooks[ $hook ][] = $callback;
        }
    }
}