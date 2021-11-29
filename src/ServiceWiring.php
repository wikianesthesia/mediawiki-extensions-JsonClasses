<?php

namespace MediaWiki\Extension\JsonSchemaClasses;

use MediaWiki\MediaWikiServices;

return [
    'JsonSchemaClassManager' => static function( MediaWikiServices $services ): JsonSchemaClassManager {
        return new JsonSchemaClassManager();
    },
];
