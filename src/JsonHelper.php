<?php

namespace MediaWiki\Extension\JsonSchemaClasses;

class JsonHelper {
    public static function decodeJsonFile( string $jsonFile ) {
        $json = file_get_contents( $jsonFile );

        if( $json === false ) {
            // TODO log error schema file not found or not accessible

            return false;
        }

        $decodedJson = json_decode( $json, true );

        if( !is_array( $decodedJson ) ) {
            // TODO log error json file not valid

            return false;
        }

        return $decodedJson;
    }

    public static function processDefinitionProperties( array $schema, array &$definition ) {
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
                static::processDefinitionProperties( $schema[ 'properties' ][ $propertyName ], $definition[ $propertyName ] );
            }

            if( isset( $schema[ 'properties' ][ $propertyName ][ 'items' ] ) &&
                isset( $schema[ 'properties' ][ $propertyName ][ 'items' ][ 'properties' ] ) ) {
                foreach( $definition[ $propertyName ] as $itemIndex => $item ) {
                    static::processDefinitionProperties( $schema[ 'properties' ][ $propertyName ][ 'items' ], $definition[ $propertyName ][ $itemIndex ] );
                }
            }
        }
    }
}