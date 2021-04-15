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

  protected function canCustomizeLintSeverities() {
    return false;
  }

  public function getNodeBinary() {
    return 'spectral';
  }

  public function getNpmPackageName() {
    return '@stoplight/spectral';
  }

  protected function getMandatoryFlags() {
    return array('lint', '--format', 'json', '--quiet');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function getLintSeverityMap() {
    return array(
      0 => ArcanistLintSeverity::SEVERITY_ERROR,
      1 => ArcanistLintSeverity::SEVERITY_WARNING,
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
          "source": "/Users/jon/P/pinboard/api/v5/spec/core/schemas.yaml"
        }
      ]
    */
    $json = json_decode($stdout, true);

    $messages = array();
    foreach ($json as $item) {
      $messages[] = id(new ArcanistLintMessage())
        ->setName($this->getLinterName())
        ->setCode($item['code'])
        ->setPath(idx($item, 'source', $path))
        ->setLine($item['range']['start']['line'] + 1)
        ->setChar($item['range']['start']['character'] + 1)
        ->setDescription($item['message'])
        ->setSeverity($this->getLintMessageSeverity($item['severity']));
    }

    return $messages;
  }
}
