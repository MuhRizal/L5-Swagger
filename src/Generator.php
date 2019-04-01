<?php

namespace L5Swagger;

use File;
use L5Swagger\Exceptions\L5SwaggerException;
use Symfony\Component\Yaml\Dumper as YamlDumper;

class Generator
{
    /**
     * @var string
     */
    protected $appDir;

    /**
     * @var string
     */
    protected $docDir;

    /**
     * @var string
     */
    protected $docsFile;

    /**
     * @var string
     */
    protected $yamlDocsFile;

    /**
     * @var array
     */
    protected $excludedDirs;

    /**
     * @var array
     */
    protected $constants;

    /**
     * @var \OpenApi\Annotations\Swagger
     */
    protected $swagger;

    /**
     * @var bool
     */
    protected $yamlCopyRequired;

    public function __construct()
    {
		$this->appDir = config('l5-swagger.paths.annotations');
		$this->docDir = config('l5-swagger.paths.docs');
		$this->docsFile = $this->docDir.'/'.config('l5-swagger.paths.docs_json', 'api-docs.json');
		$this->yamlDocsFile = $this->docDir.'/'.config('l5-swagger.paths.docs_yaml', 'api-docs.yaml');
		$this->excludedDirs = config('l5-swagger.paths.excludes');
		$this->constants = config('l5-swagger.constants') ?: [];
		$this->yamlCopyRequired = config('l5-swagger.generate_yaml_copy', false);
    }

    public static function generateDocs($group = '')
    {
        if($group){
			(new static)->prepareDirectory($group)
				->defineConstants($group)
				->scanFilesForDocumentation($group)
				->populateServers($group)
				->saveJson($group)
				->makeYamlCopy($group);
		} else {
			(new static)->prepareDirectory()
				->defineConstants()
				->scanFilesForDocumentation()
				->populateServers()
				->saveJson()
				->makeYamlCopy();
		}
    }

    /**
     * Check directory structure and permissions.
     *
     * @return Generator
     */
    protected function prepareDirectory($group = '')
    {
        if($group){
			$docDir = config('l5-swagger.paths.docs');
			$docDir = config('l5-swagger.doc_groups.'.$group.'.paths.docs', $docDir);
			if (File::exists($docDir) && ! is_writable($docDir)) {
				throw new L5SwaggerException('Documentation storage directory is not writable');
			}

			// delete all existing documentation
			if (File::exists($docDir)) {
				File::deleteDirectory($docDir);
			}

			File::makeDirectory($docDir);
		} else {
			if (File::exists($this->docDir) && ! is_writable($this->docDir)) {
				throw new L5SwaggerException('Documentation storage directory is not writable');
			}

			// delete all existing documentation
			if (File::exists($this->docDir)) {
				File::deleteDirectory($this->docDir);
			}

			File::makeDirectory($this->docDir);
		}

        return $this;
    }

    /**
     * Define constant which will be replaced.
     *
     * @return Generator
     */
    protected function defineConstants($group = '')
    {
		if($group){
			$constants = config('l5-swagger.doc_groups.'.$group.'.constants') ?: [];
			if (! empty($constants)) {
				foreach ($constants as $key => $value) {
					defined($key) || define($key, $value);
				}
			}
		} else {
			if (! empty($this->constants)) {
				foreach ($this->constants as $key => $value) {
					defined($key) || define($key, $value);
				}
			}
		}

        return $this;
    }

    /**
     * Scan directory and create Swagger.
     *
     * @return Generator
     */
    protected function scanFilesForDocumentation($group = '')
    {
        if($group){
			if ($this->isOpenApi()) {
				$this->swagger = \OpenApi\scan(
					config('l5-swagger.doc_groups.'.$group.'.paths.annotations'),
					['exclude' => config('l5-swagger.doc_groups.'.$group.'.paths.excludes')]
				);
			}

			if (! $this->isOpenApi()) {
				$this->swagger = \Swagger\scan(
					config('l5-swagger.doc_groups.'.$group.'.paths.annotations'),
					['exclude' => config('l5-swagger.doc_groups.'.$group.'.paths.excludes')]
				);
			}
		} else {
			if ($this->isOpenApi()) {
				$this->swagger = \OpenApi\scan(
					$this->appDir,
					['exclude' => $this->excludedDirs]
				);
			}

			if (! $this->isOpenApi()) {
				$this->swagger = \Swagger\scan(
					$this->appDir,
					['exclude' => $this->excludedDirs]
				);
			}
		}

        return $this;
    }

    /**
     * Generate servers section or basePath depending on Swagger version.
     *
     * @return Generator
     */
    protected function populateServers($group = '')
    {
        if($group){
			if (config('l5-swagger.doc_groups.'.$group.'.paths.base') !== null) {
				if ($this->isOpenApi($group)) {
					$this->swagger->servers = [
						new \OpenApi\Annotations\Server(['url' => config('l5-swagger.doc_groups.'.$group.'.paths.base')]),
					];
				}

				if (! $this->isOpenApi($group)) {
					$this->swagger->basePath = config('l5-swagger.doc_groups.'.$group.'..base');
				}
			}
		} else {
			if (config('l5-swagger.paths.base') !== null) {
				if ($this->isOpenApi()) {
					$this->swagger->servers = [
						new \OpenApi\Annotations\Server(['url' => config('l5-swagger.paths.base')]),
					];
				}

				if (! $this->isOpenApi()) {
					$this->swagger->basePath = config('l5-swagger.paths.base');
				}
			}
		}

        return $this;
    }

    /**
     * Save documentation as json file.
     *
     * @return Generator
     */
    protected function saveJson($group = '')
    {
		if($group){
			$docDir = config('l5-swagger.paths.docs');
			$docDir = config('l5-swagger.doc_groups.'.$group.'.paths.docs', $docDir);
            $docsFile = $docDir.'/'.config('l5-swagger.doc_groups.'.$group.'.paths.docs_json', 'api-docs.json');
			
			$this->swagger->saveAs($docsFile);

			$security = new SecurityDefinitions();
			$security->generate($docsFile);
		} else {
			$this->swagger->saveAs($this->docsFile);

			$security = new SecurityDefinitions();
			$security->generate($this->docsFile);
		}
        return $this;
    }

    /**
     * Save documentation as yaml file.
     *
     * @return Generator
     */
    protected function makeYamlCopy($group = '')
    {
        if($group){
			$docDir = config('l5-swagger.paths.docs');
			$yamlCopyRequired = config('l5-swagger.doc_groups.'.$group.'.generate_yaml_copy', false);
			$docDir = config('l5-swagger.doc_groups.'.$group.'.paths.docs', $docDir);
            $docsFile = $docDir.'/'.config('l5-swagger.doc_groups.'.$group.'.paths.docs_json', 'api-docs.json');
			$yamlDocsFile = $docDir.'/'.config('l5-swagger.doc_groups.'.$group.'.docs_yaml', 'api-docs.yaml');
			if ($yamlCopyRequired) {
				file_put_contents(
					$yamlDocsFile,
					(new YamlDumper(2))->dump(json_decode(file_get_contents($docsFile), true), 20)
				);
			}
		} else {
			if ($this->yamlCopyRequired) {
				file_put_contents(
					$this->yamlDocsFile,
					(new YamlDumper(2))->dump(json_decode(file_get_contents($this->docsFile), true), 20)
				);
			}
        }
    }

    /**
     * Check which documentation version is used.
     *
     * @return bool
     */
    protected function isOpenApi($group = '')
    {
		if($group){
			return version_compare(config('l5-swagger.doc_groups.'.$group.'.swagger_version'), '3.0', '>=');
		} else {
			return version_compare(config('l5-swagger.swagger_version'), '3.0', '>=');
		}
    }
}
