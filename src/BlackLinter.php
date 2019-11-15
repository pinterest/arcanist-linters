<?php
/**
 * Copyright 2019 Pinterest, Inc.
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
 * Formats Python files using Black
 */
final class BlackLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return 'Black';
  }

  public function getInfoURI() {
    return 'https://black.readthedocs.io/';
  }

  public function getInfoDescription() {
    return pht('Black is an opionionated code formatter for Python');
  }

  public function getLinterName() {
    return 'BLACK';
  }

  public function getLinterConfigurationName() {
    return 'black';
  }

  public function getLinterPriority() {
    return 0.01;
  }
  public function getDefaultBinary() {
    return 'black';
  }

  public function getVersion() {
    list($err, $stdout, $stderr) = exec_manual('%C --version', $this->getExecutableCommand());
    return trim(str_replace('black, version', '', $stdout));
  }

  public function getInstallInstructions() {
    return pht('pip3 install black');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    if ($err == 123 or $stderr) {
      return false;
    }
    $flags = $this->getCommandFlags();
    // Remove --check flag since instructions to user should be to fix lint errors 
    unset($flags[array_search('--check', $flags)]);

    $message = new ArcanistLintMessage();
    $message->setPath($path);
    $message->setName($this->getLinterName());
    $message->setDescription("Please run `black ".join(" ", $flags)." ".$path."`\n");
    $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
    $messages[] = $message;
    return $messages;
  }
}
