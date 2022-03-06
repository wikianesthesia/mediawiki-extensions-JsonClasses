<?php

namespace MediaWiki\Extension\JsonClasses;

use MediaWiki\Extension\JsonClasses\Hook\HookRunner;
use MediaWiki\MediaWikiServices;

return [
    'JsonClassesHookRunner' => static function ( MediaWikiServices $services ): HookRunner {
        return new HookRunner( $services->getHookContainer() );
    },
    'JsonClassManager' => static function( MediaWikiServices $services ): JsonClassManager {
        return new JsonClassManager();
    },
];
