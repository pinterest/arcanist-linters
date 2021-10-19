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
 * Lints YAML files with yamllint
 */
final class YamlLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return "YamlLint";
  }

  public function getInfoURI() {
    return "https://yamllint.readthedocs.io/";
  }

  public function getInfoDescription() {
    return pht("Verifies the syntax of yaml config files");
  }

  public function getLinterName() {
    return "YamlLint";
  }

  public function getLinterConfigurationName() {
    return "yamllint";
  }

  public function getDefaultBinary() {
    return "yamllint";
  }

  protected function getMandatoryFlags() {
    return array("-f", "auto");
  }

  public function getInstallInstructions() {
    return pht("run `brew install yamllint` on Mac or `sudo apt-get install yamllint` on Debian based Linux or `sudo dnf install yamllint` on RedHat based Linux");
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  protected function canCustomizeLintSeverities() {
    return true;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);

    // 6:22      error    syntax error: mapping values are not allowed here (syntax)
    $regex = '/^(?P<line>\\d+):(?P<offset>\\d+) +(?P<severity>warning|error) +(?P<message>.*) +\\((?P<name>.*)\\)$/';
    $messages = array();
    foreach ($lines as $line) {
      $line = trim($line);
      $matches = null;
      if (!preg_match($regex, $line, $matches)) {
        continue;
      }

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine(trim($matches[1]));
      $message->setName(trim($this->getLinterName()));
      $message->setCode($matches[5]);
      $message->setDescription($matches[4]);
      $message->setSeverity($matches[3]);

      $messages[] = $message;
    }

    return $messages;
  }

}
