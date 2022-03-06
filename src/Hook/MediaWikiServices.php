<?php

namespace MediaWiki\Extension\JsonClasses\Hook;

class MediaWikiServices {
    public static function callback( \MediaWiki\MediaWikiServices $services ) {
        $services->get( 'JsonClassesHookRunner' )->onJsonClassRegistration( $services->get( 'JsonClassManager' ) );
    }
}