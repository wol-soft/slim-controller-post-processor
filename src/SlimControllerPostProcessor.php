<?php

declare(strict_types = 1);

namespace SlimControllerPostProcessor;

use PHPMicroTemplate\Exception\FileSystemException;
use PHPMicroTemplate\Exception\SyntaxErrorException;
use PHPMicroTemplate\Exception\UndefinedSymbolException;
use PHPMicroTemplate\Render;
use PHPModelGenerator\Model\GeneratorConfiguration;
use PHPModelGenerator\Model\Schema;
use PHPModelGenerator\SchemaProcessor\PostProcessor\PostProcessorInterface;
use plejus\PhpPluralize\Inflector;

/**
 * Class SlimControllerPostProcessor
 *
 * @package SlimControllerPostProcessor
 */
class SlimControllerPostProcessor implements PostProcessorInterface
{
    /** @var string */
    private static $destinationDirectory;
    /** @var string */
    private static $namespacePrefix;

    /** @var Render */
    private $render;
    /** @var Inflector */
    private $inflector;

    /** @var array */
    private static $controllers = [];
    /** @var array */
    private static $shutdownFunctionRegistered = false;

    public function __construct(string $destinationDirectory, string $namespacePrefix = '')
    {
        self::$destinationDirectory = $destinationDirectory;
        self::$namespacePrefix = trim($namespacePrefix, '\\');

        $this->render = new Render(__DIR__ . '/templates/');
        $this->inflector = new Inflector();

        if (!self::$shutdownFunctionRegistered) {
            register_shutdown_function([$this, 'renderBootstrapFile']);
        }
    }

    /**
     * @param Schema $schema
     * @param GeneratorConfiguration $generatorConfiguration
     *
     * @throws FileSystemException
     * @throws SyntaxErrorException
     * @throws UndefinedSymbolException
     */
    public function process(Schema $schema, GeneratorConfiguration $generatorConfiguration): void
    {
        /*if (!$schema->isInitialClass()) {
            return;
        }*/

        $repositoryFQCN = $this->renderRepositoryInterface($schema, $generatorConfiguration);
        $this->renderController($schema, $generatorConfiguration, $repositoryFQCN);
    }

    /**
     * @param Schema $schema
     * @param GeneratorConfiguration $generatorConfiguration
     * @param string $repositoryFQCN
     *
     * @throws FileSystemException
     * @throws SyntaxErrorException
     * @throws UndefinedSymbolException
     */
    private function renderController(
        Schema $schema,
        GeneratorConfiguration $generatorConfiguration,
        string $repositoryFQCN
    ): void {
        $namespace = trim(join('\\', array_filter([self::$namespacePrefix, $schema->getClassPath()])), '\\');

        $path = $this->generateDirectory($schema->getClassPath());

        file_put_contents(
            $path . '/' . ucfirst($schema->getClassName()) . 'Controller.php',
            $this->render->renderTemplate(
                'Controller.phptpl',
                [
                    'namespace' => $namespace,
                    'entityName' => ucfirst($schema->getClassName()),
                    'modelClass' => $this->getModelClassFQCN($schema, $generatorConfiguration),
                    'repositoryFQCN' => $repositoryFQCN,
                ]
            )
        );

        $fqcn = "\\$namespace\\" . ucfirst($schema->getClassName()) . 'Controller';

        self::$controllers[$this->inflector->pluralize($schema->getClassName(), 2)] = $fqcn;

        echo "Rendered class $fqcn\n";
    }

    /**
     * @param Schema $schema
     * @param GeneratorConfiguration $generatorConfiguration
     *
     * @return string
     *
     * @throws FileSystemException
     * @throws SyntaxErrorException
     * @throws UndefinedSymbolException
     */
    private function renderRepositoryInterface(Schema $schema, GeneratorConfiguration $generatorConfiguration): string
    {
        $namespace = trim(
            join('\\', array_filter([self::$namespacePrefix, 'Repository', $schema->getClassPath()])),
            '\\'
        );

        $path = $this->generateDirectory('Repository\\' . $schema->getClassPath());

        file_put_contents(
            $path . '/' . ucfirst($schema->getClassName()) . 'RepositoryInterface.php',
            $this->render->renderTemplate(
                'RepositoryInterface.phptpl',
                [
                    'namespace' => $namespace,
                    'entityName' => ucfirst($schema->getClassName()),
                    'modelClass' => $this->getModelClassFQCN($schema, $generatorConfiguration),
                ]
            )
        );

        $fqcn = "\\$namespace\\" . ucfirst($schema->getClassName()) . "RepositoryInterface";

        echo "Rendered class $fqcn\n";

        return $fqcn;
    }

    /**
     * Generate the directory structure for saving a generated class
     *
     * @param string $classPath
     *
     * @throws FileSystemException
     */
    protected function generateDirectory(string $classPath): string
    {
        $fullPath = self::$destinationDirectory;

        foreach (explode('\\', $classPath) as $directory) {
            $fullPath .= "/$directory";

            if (!is_dir($fullPath) && !mkdir($fullPath)) {
                throw new FileSystemException("Can't create path $fullPath");
            }
        }

        return $fullPath;
    }

    /**
     * @throws FileSystemException
     * @throws SyntaxErrorException
     * @throws UndefinedSymbolException
     */
    public function renderBootstrapFile()
    {
        if (empty(self::$controllers)) {
            return;
        }

        file_put_contents(
            self::$destinationDirectory . '/BootstrapGeneratedControllers.php',
            $this->render->renderTemplate(
                'Bootstrap.phptpl',
                [
                    'namespace' => self::$namespacePrefix,
                    'controllers' => self::$controllers,
                ]
            )
        );

        echo 'Rendered class ' . self::$namespacePrefix . '\BootstrapGeneratedControllers.php';
    }

    /**
     * @param Schema $schema
     * @param GeneratorConfiguration $generatorConfiguration
     *
     * @return string
     */
    private function getModelClassFQCN(Schema $schema, GeneratorConfiguration $generatorConfiguration): string
    {
        return '\\' . trim(join(
            '\\',
            array_filter([
                $generatorConfiguration->getNamespacePrefix(),
                $schema->getClassPath(),
                $schema->getClassName()
            ])
        ), '\\');
    }
}
