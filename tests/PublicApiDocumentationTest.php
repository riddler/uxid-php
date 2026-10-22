<?php

declare(strict_types=1);

namespace Riddler\Uxid\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Every public class, constant, method and property in src/ carries a PHPDoc
 * block. The members PHP itself adds to an enum are not declared there.
 */
#[CoversNothing]
final class PublicApiDocumentationTest extends TestCase
{
    # sabotage: delete the PHPDoc block above Size::parse -> assertSame failed: the list of undocumented declarations held 'Riddler\Uxid\Size::parse()'
    public function testEveryPublicDeclarationHasADocBlock(): void
    {
        $files = glob(\dirname(__DIR__) . '/src/*.php');
        self::assertIsArray($files);
        self::assertNotSame([], $files);

        $missing = [];
        foreach ($files as $file) {
            /** @var class-string $name */
            $name = 'Riddler\\Uxid\\' . basename($file, '.php');
            $class = new \ReflectionClass($name);

            if ($class->getDocComment() === false) {
                $missing[] = $name;
            }

            foreach ($class->getReflectionConstants() as $constant) {
                if ($constant->isPublic() && $constant->getDocComment() === false) {
                    $missing[] = $name . '::' . $constant->getName();
                }
            }

            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isUserDefined() && $method->getDocComment() === false) {
                    $missing[] = $name . '::' . $method->getName() . '()';
                }
            }

            foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if (!$class->isEnum() && $property->getDocComment() === false) {
                    $missing[] = $name . '::$' . $property->getName();
                }
            }
        }

        self::assertSame([], $missing);
    }
}
