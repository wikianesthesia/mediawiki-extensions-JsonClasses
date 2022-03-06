<?php

namespace MediaWiki\Extension\JsonClasses\Hook;

use MediaWiki\Extension\JsonClasses\JsonClassManager;

interface JsonClassRegistrationHook {
    /**
     * @param JsonClassManager $classManager
     * @return mixed
     */
    public function onJsonClassRegistration( $classManager );
}