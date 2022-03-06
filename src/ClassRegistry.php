<?php

namespace MediaWiki\Extension\JsonClasses;

class ClassRegistry {
    protected $classDefinitionFileName;
    protected $registeredClassDefinitionFiles = [];

    public function __construct( $classDefinitionFileName ) {
        $this->classDefinitionFileName = $classDefinitionFileName;
    }

    public function register( string $classDirectory, bool $includeSubdirectories = false, bool $recursive = false ) {
        $classDefinitionFile = $classDirectory . '/' . $this->classDefinitionFileName;

        if( file_exists( $classDefinitionFile ) ) {
            $this->registeredClassDefinitionFiles[] = $classDefinitionFile;
        }

        if( $includeSubdirectories ) {
            foreach( scandir( $classDirectory ) as $file ) {
                if( $file !== '.' && $file !== '..' && is_dir( $classDirectory . '/' . $file ) ) {
                    $this->register( $classDirectory . '/' . $file, $recursive, $recursive );
                }
            }
        }
    }

    public function getRegisteredClassDefinitionFiles(): array {
        return $this->registeredClassDefinitionFiles;
    }
}