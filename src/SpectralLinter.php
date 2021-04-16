<?php
/**
 * Copyright 2021 Pinterest, Inc.
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
 * Lints OpenAPI specification files using Spectral
 */
final class SpectralLinter extends NodeExternalLinter {

  private $ruleset = null;

  public function getInfoName() {
    return 'Spectral';
  }

  public function getInfoURI() {
    return 'https://stoplight.io/spectral';
  }

  public function getInfoDescription() {
    return 'Lint OpenAPI specification against a set of rules';
  }

  public function getLinterName() {
    return 'SPECTRAL';
  }

  public function getLinterConfigurationName() {
    return 'spectral';
  }

  public function getVersion() {
    list($err, $stdout, $stderr) = exec_manual('%C --version', $this->getExecutableCommand());
    return trim($stdout);
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'spectral.ruleset' => array(
        'type' => 'optional string',
        'help' => pht('Path/URL to a ruleset file'),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'spectral.ruleset':
        $this->ruleset = $value;
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getNodeBinary() {
    return 'spectral';
  }

  public function getNpmPackageName() {
    return '@stoplight/spectral';
  }

  protected function getMandatoryFlags() {
    $flags = array('lint', '--format', 'json', '--quiet');

    if ($this->ruleset) {
      $flags[] = '--ruleset';
      $flags[] = $this->ruleset;
    }

    return $flags;
  }

  protected function getDefaultFlags() {
    return array('--ignore-unknown-format');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function getLintSeverityMap() {
    return array(
      0 => ArcanistLintSeverity::SEVERITY_ERROR,
      1 => ArcanistLintSeverity::SEVERITY_WARNING,
      2 => ArcanistLintSeverity::SEVERITY_ADVICE,
      3 => ArcanistLintSeverity::SEVERITY_ADVICE,
    );
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    /*
      [
        {
          "code": "oas3-valid-schema-example",
          "path": [
            "Pin",
            "properties",
            "images",
            "additionalProperties"
          ],
          "message": "`example` property type should be object",
          "severity": 0,
          "range": {
            "start": {
              "line": 53,
              "character": 27
            },
            "end": {
              "line": 54,
              "character": 30
            }
          },
          "source": "/api/core/schemas.yaml"
        }
      ]
    */
    $json = json_decode($stdout, true);

    $messages = array();
    foreach ($json as $item) {
      $description = $item['message'];
      if (array_key_exists('path', $item)) {
        $path = implode('.', $item['path']);
        $description.=" ($path)";
      }

      $messages[] = id(new ArcanistLintMessage())
        ->setName($this->getLinterName())
        ->setCode($item['code'])
        ->setPath(idx($item, 'source', $path))
        ->setLine($item['range']['start']['line'] + 1)
        ->setChar($item['range']['start']['character'] + 1)
        ->setDescription($description)
        ->setSeverity($this->getLintMessageSeverity($item['severity']));
    }

    return $messages;
  }
}
