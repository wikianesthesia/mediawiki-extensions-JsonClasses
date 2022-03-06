<?php

namespace MediaWiki\Extension\JsonClasses\Hook;

use MediaWiki\HookContainer\HookContainer;

class HookRunner implements JsonClassRegistrationHook {
    /** @var HookContainer */
    private $hookContainer;

    /**
     * @param HookContainer $hookContainer
     */
    public function __construct( HookContainer $hookContainer ) {
        $this->hookContainer = $hookContainer;
    }

    /**
     * @inheritDoc
     */
    public function onJsonClassRegistration( $classManager ) {
        return $this->hookContainer->run(
            'JsonClassRegistration',
            [ &$classManager ]
        );
    }
}