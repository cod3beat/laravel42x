<?php

namespace L4\Tests;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

class BackwardCompatibleTestCase extends TestCase
{
    use ProphecyTrait;

    /**
     * Returns a mock object for the specified class.
     *
     * This method is a temporary solution to provide backward compatibility for tests that are still using the old
     * (4.8) getMock() method.
     * We should update the code and remove this method but for now this is good enough.
     *
     *
     * @param string $originalClassName Name of the class to mock.
     * @param array|null $methods                 When provided, only methods whose names are in the array
     *                                            are replaced with a configurable test double. The behavior
     *                                            of the other methods is not changed.
     *                                            Providing null means that no methods will be replaced.
     * @param array $arguments Parameters to pass to the original class' constructor.
     * @param string $mockClassName Class name for the generated test double class.
     * @param bool $callOriginalConstructor Can be used to disable the call to the original class' constructor.
     * @param bool $callOriginalClone Can be used to disable the call to the original class' clone constructor.
     * @param bool $callAutoload Can be used to disable __autoload() during the generation of the test double class.
     * @param bool $cloneArguments
     * @param bool $callOriginalMethods
     * @param object $proxyTarget
     *
     * @return MockObject
     *
     * @throws Exception
     */
    public function getMock($originalClassName, $methods = array(), array $arguments = array(), $mockClassName = '', $callOriginalConstructor = true, $callOriginalClone = true, $callAutoload = true, $cloneArguments = false, $callOriginalMethods = false, $proxyTarget = null)
    {
        $builder = $this->getMockBuilder($originalClassName);

        if (is_array($methods)) {
            $builder->onlyMethods($methods);
        }

        if (is_array($arguments)) {
            $builder->setConstructorArgs($arguments);
        }

        $callOriginalConstructor ? $builder->enableOriginalConstructor() : $builder->disableOriginalConstructor();
        $callOriginalClone ? $builder->enableOriginalClone() : $builder->disableOriginalClone();
        $callAutoload ? $builder->enableAutoload() : $builder->disableAutoload();
        $cloneArguments ? $builder->enableOriginalClone() : $builder->disableOriginalClone();
        $callOriginalMethods ? $builder->enableProxyingToOriginalMethods() : $builder->disableProxyingToOriginalMethods();

        if ($mockClassName) {
            $builder->setMockClassName($mockClassName);
        }

        if ($proxyTarget) {
            $builder->setProxyTarget($proxyTarget);
        }

        return $builder->getMock();
    }
}