<?php

namespace MediaWiki\Extension\JsonClasses;

use MediaWiki\MediaWikiServices;

return [
    'JsonClassManager' => static function( MediaWikiServices $services ): JsonClassManager {
        return new JsonClassManager();
    },
];
