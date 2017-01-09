<?php
namespace timgws\test;


use timgws\QBParseException;

/**
 * Class QBPFunctionsTests
 *
 * Uses reflection to get to one particularly
 *
 * @package timgws\test
 */
class QBPFunctionsTests extends CommonQueryBuilderTests
{
    protected static function getMethod($name)
    {
        $class = new \ReflectionClass('\timgws\QueryBuilderParser');
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    /**
     * @expectedException \timgws\QBParseException
     * @expectedExceptionMessage makeQueryWhenArray could not return a value
     */
    public function testOperatorNotValid()
    {
        $method = self::getMethod('makeQueryWhenArray');

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $rule = json_decode($this->makeJSONForInNotInTest('contains'));

        $method->invokeArgs($qb, [
            $builder, $rule->rules[1], ['operator' => 'CONTAINS'], ['AND'], 'AND'
        ]);
    }

    /**
     * @expectedException \timgws\QBParseException
     * @expectedExceptionMessage makeQueryWhenNull was called on an SQL operator that is not null
     */
    public function testOperatorNotValidForNull()
    {
        $method = self::getMethod('makeQueryWhenNull');

        $builder = $this->createQueryBuilder();
        $qb = $this->getParserUnderTest();
        $rule = json_decode($this->makeJSONForInNotInTest('contains'));

        $method->invokeArgs($qb, [
            $builder, $rule->rules[1], ['operator' => 'CONTAINS'], ['AND'], 'AND'
        ]);
    }
}
