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
 * Lints Python source files using pylint.
 */
class PinterestPylintLinter extends PythonExternalLinter {

  public function getPythonBinary() {
    return 'pylint';
  }

  public function shouldExpectCommandErrors() {
      return true;
  }

  public function getInfoName() {
    return 'pinterest-pylint';
  }

  public function getInfoDescription() {
    return pht("Up-to-date replacement for Arcanist's built-in pylint linter");
  }

  public function getInfoURI() {
    return 'https://docs.pylint.org/en/';
  }

  public function getLinterName() {
    return 'PYLINT';
  }

  public function getLinterConfigurationName() {
    return 'pinterest-pylint';
  }

  public function getInstallInstructions() {
    return pht('Install pylint using `%s`.', 'pip install pylint');
  }

  protected function getDefaultMessageSeverity($code) {
    switch (substr($code, 0, 1)) {
      case 'R':
      case 'C':
        return ArcanistLintSeverity::SEVERITY_ADVICE;
      case 'W':
        return ArcanistLintSeverity::SEVERITY_WARNING;
      case 'E':
      case 'F':
        return ArcanistLintSeverity::SEVERITY_ERROR;
      default:
        return ArcanistLintSeverity::SEVERITY_DISABLED;
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);

    $messages = array();
    foreach ($lines as $line) {
      $matches = explode(':', $line, 5);

      if (count($matches) === 5) {
        $message = (new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($matches[1])
          ->setCode(trim($this->getLinterName()))
          ->setName(trim($this->getLinterName()))
          ->setDescription($matches[4])
          ->setSeverity($this->getLintMessageSeverity(trim($matches[3])));

        $messages[] = $message;
      }
    }

    return $messages;
  }
}
