<?php
/**
 * Copyright 2020 Pinterest, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Lints OpenAPI specification files using openapi-validator
 */
final class OpenApiLinter extends NodeExternalLinter {

  private $config = null;
  private $debug = false;
  private $errors_only = false;

  public function getInfoName() {
    return 'OpenAPI Validator';
  }

  public function getInfoURI() {
    return 'https://github.com/IBM/openapi-validator';
  }

  public function getInfoDescription() {
    return 'Validate OpenAPI specification against a set of rules';
  }

  public function getLinterName() {
    return 'OPENAPI';
  }

  public function getLinterConfigurationName() {
    return 'openapi-spec';
  }

  public function getVersion() {
    list($err, $stdout, $stderr) = exec_manual('%C --version', $this->getExecutableCommand());
    return trim($stdout);
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'openapi-spec.config' => array(
        'type' => 'optional string',
        'help' => pht('Config file that defines the validation rules (https://github.com/IBM/openapi-validator#configuration-file)'),
      ),
      'openapi-spec.debug' => array(
        'type' => 'optional bool',
        'help' => pht('Enable debugging output'),
      ),
      'openapi-spec.errors_only' => array(
        'type' => 'optional bool',
        'help' => pht('Only print the errors, ignore the warnings'),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'openapi-spec.config':
        $this->config = $value;
        return;
      case 'openapi-spec.debug':
        $this->debug = $value;
        return;
      case 'openapi-spec.errors_only':
        $this->errors_only = $value;
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  public function getNodeBinary() {
    return 'lint-openapi';
  }

  public function getNpmPackageName() {
    return 'ibm-openapi-validator';
  }

  protected function getMandatoryFlags() {
    return array('--json', '--verbose', '--no_colors');
  }

  protected function getDefaultFlags() {
    $flags = array();
    if ($this->config) {
      $flags[] = '--config='.$this->config;
    }
    if ($this->debug) {
      $flags[] = '--debug';
    }
    if ($this->errors_only) {
      $flags[] = '--errors_only';
    }
    return $flags;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $messages = array();

    if ($err) {
      throw new Exception(pht("While reading %s, an error occurred:\n%s\n%s", $path, $stderr, $stdout));
    }
    /* Sample Output
    {
      "errors": {
        "spectral": [
          {
            "path": [
              "servers",
              "0",
              "url"
            ],
            "message": "Server URL should not have a trailing slash.",
            "rule": "operation_id_naming_convention",
            "line": 9
          },
        ]
      },
      "warnings": {
        "spectral": [
          {
            "path": [],
            "message": "OpenAPI object should have non-empty `tags` array.",
            "rule": "operation_id_naming_convention",
            "line": 0
          }
         ]
      },
      "error": true,
      "warning": true
    }
    */
    $json = json_decode($stdout, true);
    if (!is_array($json)) {
      throw new Exception(pht("While reading %s, an error occurred:\n%s", $path, $stderr));
    }
    if (array_key_exists('errors', $json)) {
      $errors = $json['errors'];
      $messages = $this->processOutput($path, $errors, ArcanistLintSeverity::SEVERITY_ERROR);
    }
    if (array_key_exists('warnings', $json)) {
      $warnings = $json['warnings'];
      $messages += $this->processOutput($path, $warnings, ArcanistLintSeverity::SEVERITY_WARNING);
    }
    return $messages;
  }

  private function processOutput($path, $output_categories, $severity) {
    $messages = array();
    foreach ($output_categories as $output_category) {
      foreach ($output_category as $output) {
        $message = new ArcanistLintMessage();
        $message->setPath($path)
          ->setCode(nonempty(idx($output, 'rule'), 'unknown'))
          ->setName($this->getLinterName())
          ->setLine($output['line'])
          ->setDescription($output['message'])
          ->setSeverity($severity);
        $messages[] = $message;
      }
    }
    return $messages;
  }
}
